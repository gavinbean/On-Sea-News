<?php
/**
 * Authentication Functions
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/captcha.php';
require_once __DIR__ . '/../config/database.php';

/**
 * Register new user
 */
function registerUser($data) {
    $db = getDB();
    
    // Validate required fields
    $required = ['username', 'email', 'password', 'password_confirm', 'name', 'surname', 'telephone', 'street_name', 'town', 'terms_accepted', 'captcha'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            return ['success' => false, 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required.'];
        }
    }
    
    // Verify CAPTCHA
    if (!verifyCaptcha($data['captcha'])) {
        return ['success' => false, 'message' => 'Invalid CAPTCHA code.'];
    }
    
    // Check terms acceptance
    if ($data['terms_accepted'] != '1') {
        return ['success' => false, 'message' => 'You must accept the terms and conditions.'];
    }
    
    // Validate password match
    if ($data['password'] !== $data['password_confirm']) {
        return ['success' => false, 'message' => 'Passwords do not match.'];
    }
    
    // Validate password strength
    if (strlen($data['password']) < 8) {
        return ['success' => false, 'message' => 'Password must be at least 8 characters long.'];
    }
    
    // Validate email format
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Invalid email address.'];
    }
    
    // Check if username exists
    if (usernameExists($data['username'])) {
        return ['success' => false, 'message' => 'Username already exists.'];
    }
    
    // Email uniqueness check removed - same email can be used on different profiles
    
    try {
        // Geocode address using components
        require_once __DIR__ . '/geocoding.php';
        $geocodeResult = validateAndGeocodeAddress([
            'street_number' => $data['street_number'] ?? '',
            'street_name' => $data['street_name'] ?? '',
            'suburb' => $data['suburb'] ?? '',
            'town' => $data['town'] ?? ''
        ]);
        
        // Only fail if validation failed (missing required fields), not if geocoding failed
        if (!$geocodeResult['success'] && isset($geocodeResult['message']) && strpos($geocodeResult['message'], 'required') !== false) {
            return ['success' => false, 'message' => $geocodeResult['message']];
        }
        
        // If geocoding failed but validation passed, continue with null coordinates
        if (!$geocodeResult['success']) {
            $geocodeResult = [
                'success' => true,
                'latitude' => null,
                'longitude' => null,
                'formatted_address' => ($data['street_name'] ?? '') . ', ' . ($data['town'] ?? '')
            ];
        }
        
        $db->beginTransaction();
        
        // Generate email verification token
        $verificationToken = generateToken();
        $verificationExpires = date('Y-m-d H:i:s', time() + EMAIL_VERIFICATION_EXPIRY);
        
        // Insert user
        $stmt = $db->prepare("
            INSERT INTO " . TABLE_PREFIX . "users 
            (username, email, password_hash, name, surname, telephone, street_number, street_name, suburb, town, latitude, longitude, terms_accepted, terms_accepted_date, email_verified, email_verification_token, email_verification_expires)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), 0, ?, ?)
        ");
        $stmt->execute([
            $data['username'],
            $data['email'],
            hashPassword($data['password']),
            $data['name'],
            $data['surname'],
            $data['telephone'],
            $data['street_number'] ?? null,
            $data['street_name'],
            $data['suburb'] ?? null,
            $data['town'],
            $geocodeResult['latitude'],
            $geocodeResult['longitude'],
            $verificationToken,
            $verificationExpires
        ]);
        
        $userId = $db->lastInsertId();
        
        // Assign USER role
        $roleStmt = $db->prepare("SELECT role_id FROM " . TABLE_PREFIX . "roles WHERE role_name = 'USER'");
        $roleStmt->execute();
        $role = $roleStmt->fetch();
        
        if ($role) {
            $userRoleStmt = $db->prepare("INSERT INTO " . TABLE_PREFIX . "user_roles (user_id, role_id) VALUES (?, ?)");
            $userRoleStmt->execute([$userId, $role['role_id']]);
        }
        
        $db->commit();
        
        // Save water responses if provided
        if (isset($data['water_responses']) && !empty($data['water_responses'])) {
            require_once __DIR__ . '/water-questions.php';
            $waterResponses = json_decode($data['water_responses'], true);
            if ($waterResponses) {
                saveWaterResponses($userId, $waterResponses);
            }
        }
        
        // Send verification email
        require_once __DIR__ . '/email.php';
        $fullName = $data['name'] . ' ' . $data['surname'];
        sendVerificationEmail($userId, $data['email'], $fullName, $verificationToken);
        
        return ['success' => true, 'message' => 'Registration successful! Please check your email to verify your account before logging in.', 'user_id' => $userId];
        
    } catch (PDOException $e) {
        $db->rollBack();
        error_log("Registration Error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Registration failed. Please try again.'];
    }
}

/**
 * Login user
 */
function loginUser($username, $password, $rememberMe = false) {
    $db = getDB();
    
    $stmt = $db->prepare("SELECT * FROM " . TABLE_PREFIX . "users WHERE (username = ? OR email = ?) AND is_active = 1");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    
    if (!$user || !verifyPassword($password, $user['password_hash'])) {
        return ['success' => false, 'message' => 'Invalid username or password.'];
    }
    
    // Check if email is verified
    if (!$user['email_verified']) {
        return ['success' => false, 'message' => 'Please verify your email address before logging in. Check your inbox for the verification email.'];
    }
    
    startSession();
    
    // CRITICAL SECURITY: Regenerate session ID to prevent session fixation attacks
    // This ensures the old session ID is invalidated and a new one is created
    session_regenerate_id(true);
    
    // Clear any existing session data before setting new user data
    $_SESSION = array();
    
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['login_time'] = time(); // Track login time for additional security
    
    // Handle Remember Me functionality
    if ($rememberMe) {
        // Generate a secure remember me token
        $token = generateToken();
        $expires = time() + (30 * 24 * 60 * 60); // 30 days
        
        // Store token in database
        $tokenHash = hash('sha256', $token);
        $stmt = $db->prepare("
            INSERT INTO " . TABLE_PREFIX . "remember_tokens 
            (user_id, token_hash, expires_at, created_at) 
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE token_hash = ?, expires_at = ?, created_at = NOW()
        ");
        $stmt->execute([
            $user['user_id'],
            $tokenHash,
            date('Y-m-d H:i:s', $expires),
            $tokenHash,
            date('Y-m-d H:i:s', $expires)
        ]);
        
        // Set cookie (30 days) - use BASE_PATH for cookie path
        $cookiePath = defined('BASE_PATH') && BASE_PATH !== '' ? BASE_PATH : '/';
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                    (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
        setcookie('remember_token', $token, $expires, $cookiePath, '', $isSecure, true); // HttpOnly and Secure flags
    }
    
    return ['success' => true, 'message' => 'Login successful.', 'user' => $user];
}

/**
 * Logout user
 */
function logoutUser() {
    startSession();
    
    // Clear remember me token if exists
    if (isset($_COOKIE['remember_token'])) {
        $db = getDB();
        $tokenHash = hash('sha256', $_COOKIE['remember_token']);
        $stmt = $db->prepare("DELETE FROM " . TABLE_PREFIX . "remember_tokens WHERE token_hash = ?");
        $stmt->execute([$tokenHash]);
        $cookiePath = defined('BASE_PATH') && BASE_PATH !== '' ? BASE_PATH : '/';
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                    (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
        setcookie('remember_token', '', time() - 3600, $cookiePath, '', $isSecure, true);
    }
    
    $_SESSION = array();
    
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    session_destroy();
}

/**
 * Request password reset
 */
function requestPasswordReset($email) {
    $db = getDB();
    
    $stmt = $db->prepare("SELECT user_id, username FROM " . TABLE_PREFIX . "users WHERE email = ? AND is_active = 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        // Don't reveal if email exists for security
        return ['success' => true, 'message' => 'If the email exists, a password reset link has been sent.'];
    }
    
    $token = generateToken();
    $expires = date('Y-m-d H:i:s', time() + PASSWORD_RESET_EXPIRY);
    
    $stmt = $db->prepare("
        UPDATE " . TABLE_PREFIX . "users 
        SET password_reset_token = ?, password_reset_expires = ?
        WHERE user_id = ?
    ");
    $stmt->execute([$token, $expires, $user['user_id']]);
    
    // Send password reset email
    require_once __DIR__ . '/email.php';
    sendPasswordResetEmail($email, $user['username'], $token);
    
    return [
        'success' => true, 
        'message' => 'Password reset link has been sent to your email.'
    ];
}

/**
 * Reset password with token
 */
function resetPassword($token, $newPassword, $confirmPassword) {
    $db = getDB();
    
    if ($newPassword !== $confirmPassword) {
        return ['success' => false, 'message' => 'Passwords do not match.'];
    }
    
    if (strlen($newPassword) < 8) {
        return ['success' => false, 'message' => 'Password must be at least 8 characters long.'];
    }
    
    $stmt = $db->prepare("
        SELECT user_id FROM " . TABLE_PREFIX . "users 
        WHERE password_reset_token = ? 
        AND password_reset_expires > NOW()
        AND is_active = 1
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if (!$user) {
        return ['success' => false, 'message' => 'Invalid or expired reset token.'];
    }
    
    $stmt = $db->prepare("
        UPDATE " . TABLE_PREFIX . "users 
        SET password_hash = ?, password_reset_token = NULL, password_reset_expires = NULL
        WHERE user_id = ?
    ");
    $stmt->execute([hashPassword($newPassword), $user['user_id']]);
    
    return ['success' => true, 'message' => 'Password reset successful. You can now login.'];
}

/**
 * Get user by username or email (for username recovery)
 */
function getUserByEmail($email) {
    $db = getDB();
    $stmt = $db->prepare("SELECT username FROM " . TABLE_PREFIX . "users WHERE email = ? AND is_active = 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    return $user ? $user['username'] : null;
}

/**
 * Update user profile
 */
function updateUserProfile($userId, $data) {
    $db = getDB();
    
    // Validate required fields
    $required = ['name', 'surname', 'telephone', 'street_name', 'town'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            return ['success' => false, 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required.'];
        }
    }
    
    try {
        // Geocode address using components
        require_once __DIR__ . '/geocoding.php';
        $geocodeResult = validateAndGeocodeAddress([
            'street_number' => $data['street_number'] ?? '',
            'street_name' => $data['street_name'] ?? '',
            'suburb' => $data['suburb'] ?? '',
            'town' => $data['town'] ?? ''
        ]);
        
        // Only fail if validation failed (missing required fields), not if geocoding failed
        if (!$geocodeResult['success'] && isset($geocodeResult['message']) && strpos($geocodeResult['message'], 'required') !== false) {
            return ['success' => false, 'message' => $geocodeResult['message']];
        }
        
        // If geocoding failed but validation passed, continue with null coordinates
        if (!$geocodeResult['success']) {
            $geocodeResult = [
                'success' => true,
                'latitude' => null,
                'longitude' => null,
                'formatted_address' => ($data['street_name'] ?? '') . ', ' . ($data['town'] ?? '')
            ];
        }
        
        // Update user profile
        $stmt = $db->prepare("
            UPDATE " . TABLE_PREFIX . "users 
            SET name = ?, surname = ?, telephone = ?, street_number = ?, street_name = ?, suburb = ?, town = ?, latitude = ?, longitude = ?
            WHERE user_id = ? AND is_active = 1
        ");
        $stmt->execute([
            $data['name'],
            $data['surname'],
            $data['telephone'],
            $data['street_number'] ?? null,
            $data['street_name'],
            $data['suburb'] ?? null,
            $data['town'],
            $geocodeResult['latitude'],
            $geocodeResult['longitude'],
            $userId
        ]);
        
        if ($stmt->rowCount() > 0) {
            return ['success' => true, 'message' => 'Profile updated successfully.'];
        } else {
            return ['success' => false, 'message' => 'No changes were made or user not found.'];
        }
        
    } catch (PDOException $e) {
        error_log("Profile Update Error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Profile update failed. Please try again.'];
    }
}

