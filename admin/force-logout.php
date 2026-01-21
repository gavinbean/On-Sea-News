<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('ADMIN');

$pageTitle = 'Force Logout All Users';
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'force_logout') {
    startSession();

    try {
        $db = getDB();
        // Clear remember-me tokens
        $db->exec("TRUNCATE TABLE " . TABLE_PREFIX . "remember_tokens");

        // Attempt to delete session files (file-based PHP sessions)
        $sessionPathRaw = ini_get('session.save_path');
        $sessionPath = $sessionPathRaw;
        if (strpos($sessionPathRaw, ';') !== false) {
            $parts = explode(';', $sessionPathRaw);
            $sessionPath = end($parts);
        }
        if (empty($sessionPath)) {
            $sessionPath = sys_get_temp_dir();
        }

        $deleted = 0;
        $errors = 0;
        if (is_dir($sessionPath) && is_readable($sessionPath)) {
            foreach (glob(rtrim($sessionPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'sess_*') as $file) {
                if (@unlink($file)) {
                    $deleted++;
                } else {
                    $errors++;
                }
            }
        }

        // Destroy current session
        $_SESSION = [];
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        session_destroy();

        $message = "All users have been logged out. Session files deleted: $deleted" . ($errors ? " (failed to delete $errors files)" : "") . ". Remember-me tokens cleared.";
    } catch (Exception $e) {
        $error = 'Failed to force logout: ' . $e->getMessage();
        error_log('Force logout error: ' . $e->getMessage());
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="content-area">
        <h1>Force Logout All Users</h1>
        <p>This will immediately log out every user (including remember-me cookies) and clear PHP session files.</p>

        <?php if (!empty($message)): ?>
            <div class="alert alert-success"><?= h($message) ?></div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="POST" onsubmit="return confirm('Are you sure? This will log out all users immediately.');">
            <input type="hidden" name="action" value="force_logout">
            <button type="submit" class="btn btn-danger">Force Logout Everyone</button>
        </form>
    </div>
</div>

<?php 
$hideAdverts = true;
include __DIR__ . '/../includes/footer.php'; 
?>



