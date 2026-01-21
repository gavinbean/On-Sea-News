<?php
/**
 * Common Functions
 */

require_once __DIR__ . '/db.php';

/**
 * Start session if not already started
 * Configure secure session settings
 */
function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        // Configure secure session settings
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_secure', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ? 1 : 0);
        ini_set('session.cookie_samesite', 'Strict');
        
        session_start();
    }
}

/**
 * Check if user is logged in
 * Also checks for remember me cookie
 */
function isLoggedIn() {
    startSession();
    
    // Check session first
    if (isset($_SESSION['user_id'])) {
        return true;
    }
    
    // Check remember me cookie
    if (isset($_COOKIE['remember_token'])) {
        $db = getDB();
        $tokenHash = hash('sha256', $_COOKIE['remember_token']);
        
        $stmt = $db->prepare("
            SELECT u.*, rt.token_hash 
            FROM " . TABLE_PREFIX . "remember_tokens rt
            JOIN " . TABLE_PREFIX . "users u ON rt.user_id = u.user_id
            WHERE rt.token_hash = ? 
            AND rt.expires_at > NOW() 
            AND u.is_active = 1
        ");
        $stmt->execute([$tokenHash]);
        $result = $stmt->fetch();
        
        if ($result) {
            // Update last_used_at timestamp
            $updateStmt = $db->prepare("
                UPDATE " . TABLE_PREFIX . "remember_tokens 
                SET last_used_at = NOW() 
                WHERE token_hash = ?
            ");
            $updateStmt->execute([$tokenHash]);
            
            // CRITICAL SECURITY: Regenerate session ID when auto-logging in via remember me
            // This prevents session fixation attacks
            session_regenerate_id(true);
            
            // Clear any existing session data before setting new user data
            $_SESSION = array();
            
            // Auto-login user
            $_SESSION['user_id'] = $result['user_id'];
            $_SESSION['username'] = $result['username'];
            $_SESSION['login_time'] = time(); // Track login time for additional security
            return true;
        } else {
            // Invalid or expired token, clear cookie
            $cookiePath = defined('BASE_PATH') && BASE_PATH !== '' ? BASE_PATH : '/';
            $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                        (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
            setcookie('remember_token', '', time() - 3600, $cookiePath, '', $isSecure, true);
        }
    }
    
    return false;
}

/**
 * Get current user ID
 */
function getCurrentUserId() {
    startSession();
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user data
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM " . TABLE_PREFIX . "users WHERE user_id = ? AND is_active = 1");
    $stmt->execute([getCurrentUserId()]);
    return $stmt->fetch();
}

/**
 * Check if user has a specific role
 */
function hasRole($roleName) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $db = getDB();
    $stmt = $db->prepare("
        SELECT ur.user_role_id 
        FROM " . TABLE_PREFIX . "user_roles ur
        JOIN " . TABLE_PREFIX . "roles r ON ur.role_id = r.role_id
        WHERE ur.user_id = ? AND r.role_name = ?
    ");
    $stmt->execute([getCurrentUserId(), $roleName]);
    return $stmt->fetch() !== false;
}

/**
 * Check if user has any of the specified roles
 */
function hasAnyRole($roleNames) {
    if (!isLoggedIn() || empty($roleNames)) {
        return false;
    }
    
    $db = getDB();
    $placeholders = str_repeat('?,', count($roleNames) - 1) . '?';
    $stmt = $db->prepare("
        SELECT ur.user_role_id 
        FROM " . TABLE_PREFIX . "user_roles ur
        JOIN " . TABLE_PREFIX . "roles r ON ur.role_id = r.role_id
        WHERE ur.user_id = ? AND r.role_name IN ($placeholders)
    ");
    $stmt->execute(array_merge([getCurrentUserId()], $roleNames));
    return $stmt->fetch() !== false;
}

/**
 * Require login - redirect if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        $url = defined('BASE_PATH') && BASE_PATH !== '' ? BASE_PATH . '/login.php' : '/login.php';
        header("Location: $url");
        exit;
    }
}

/**
 * Require role - redirect if user doesn't have required role
 */
function requireRole($roleName) {
    requireLogin();
    if (!hasRole($roleName)) {
        $url = defined('BASE_PATH') && BASE_PATH !== '' ? BASE_PATH . '/index.php' : '/index.php';
        header("Location: $url");
        exit;
    }
}

/**
 * Require any of the specified roles
 */
function requireAnyRole($roleNames) {
    requireLogin();
    if (!hasAnyRole($roleNames)) {
        $url = defined('BASE_PATH') && BASE_PATH !== '' ? BASE_PATH . '/index.php' : '/index.php';
        header("Location: $url");
        exit;
    }
}

/**
 * Get base URL path (for use in links)
 */
function baseUrl($path = '') {
    $base = defined('BASE_PATH') && BASE_PATH !== '' ? BASE_PATH : '';
    if ($path && strpos($path, '/') !== 0) {
        $path = '/' . $path;
    }
    return $base . ($path ?: '');
}

/**
 * Sanitize output
 */
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Generate random token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Hash password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verify password
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Format date
 */
function formatDate($date, $format = 'Y-m-d H:i:s') {
    if (empty($date)) return '';
    $dt = new DateTime($date);
    return $dt->format($format);
}

/**
 * Redirect
 */
function redirect($url) {
    // If URL starts with /, prepend BASE_PATH if needed
    if (strpos($url, '/') === 0 && defined('BASE_PATH') && BASE_PATH !== '') {
        $url = BASE_PATH . $url;
    }
    header("Location: $url");
    exit;
}

/**
 * Get user roles
 */
function getUserRoles($userId) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT r.role_name, r.role_description
        FROM " . TABLE_PREFIX . "user_roles ur
        JOIN " . TABLE_PREFIX . "roles r ON ur.role_id = r.role_id
        WHERE ur.user_id = ?
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/**
 * Check if email exists
 */
function emailExists($email, $excludeUserId = null) {
    $db = getDB();
    if ($excludeUserId) {
        $stmt = $db->prepare("SELECT user_id FROM " . TABLE_PREFIX . "users WHERE email = ? AND user_id != ?");
        $stmt->execute([$email, $excludeUserId]);
    } else {
        $stmt = $db->prepare("SELECT user_id FROM " . TABLE_PREFIX . "users WHERE email = ?");
        $stmt->execute([$email]);
    }
    return $stmt->fetch() !== false;
}

/**
 * Check if username exists
 */
function usernameExists($username, $excludeUserId = null) {
    $db = getDB();
    if ($excludeUserId) {
        $stmt = $db->prepare("SELECT user_id FROM " . TABLE_PREFIX . "users WHERE username = ? AND user_id != ?");
        $stmt->execute([$username, $excludeUserId]);
    } else {
        $stmt = $db->prepare("SELECT user_id FROM " . TABLE_PREFIX . "users WHERE username = ?");
        $stmt->execute([$username]);
    }
    return $stmt->fetch() !== false;
}

