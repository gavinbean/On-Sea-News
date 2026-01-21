<?php
require_once __DIR__ . '/../includes/functions.php';
// Allow both ADMIN and USER_ADMIN roles
requireAnyRole(['ADMIN', 'USER_ADMIN']);

$db = getDB();
$message = '';
$error = '';

// Handle resend verification email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'resend_verification') {
    $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    
    if ($userId <= 0) {
        $error = 'Invalid user ID.';
    } else {
        // Get user info
        $userStmt = $db->prepare("SELECT user_id, email, name, surname, email_verified FROM " . TABLE_PREFIX . "users WHERE user_id = ?");
        $userStmt->execute([$userId]);
        $userToVerify = $userStmt->fetch();
        
        if (!$userToVerify) {
            $error = 'User not found.';
        } elseif ($userToVerify['email_verified']) {
            $error = 'User email is already verified.';
        } else {
            require_once __DIR__ . '/../includes/email.php';
            
            // Generate new verification token
            $verificationToken = generateToken();
            $verificationExpires = date('Y-m-d H:i:s', time() + EMAIL_VERIFICATION_EXPIRY);
            
            $updateStmt = $db->prepare("
                UPDATE " . TABLE_PREFIX . "users 
                SET email_verification_token = ?, email_verification_expires = ?
                WHERE user_id = ?
            ");
            $updateStmt->execute([$verificationToken, $verificationExpires, $userId]);
            
            // Send verification email
            $fullName = $userToVerify['name'] . ' ' . $userToVerify['surname'];
            $emailSent = sendVerificationEmail($userId, $userToVerify['email'], $fullName, $verificationToken);
            
            if ($emailSent) {
                $message = 'Verification email has been resent to ' . h($userToVerify['email']) . '.';
            } else {
                error_log("Failed to send verification email to: {$userToVerify['email']} for user_id: $userId");
                $error = 'Failed to send verification email. Please check server logs.';
            }
        }
    }
}

// Handle user deletion (only for ADMIN, not USER_ADMIN)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    // Only ADMIN can delete users
    if (!hasRole('ADMIN')) {
        $error = 'You do not have permission to delete users.';
    } else {
    $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $confirmDelete = isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === '1';
    
    if ($userId <= 0) {
        $error = 'Invalid user ID.';
    } elseif (!$confirmDelete) {
        $error = 'Please confirm deletion by checking the confirmation box.';
    } else {
        // Get user info for error messages
        $userStmt = $db->prepare("SELECT username, name, surname FROM " . TABLE_PREFIX . "users WHERE user_id = ?");
        $userStmt->execute([$userId]);
        $userToDelete = $userStmt->fetch();
        
        if (!$userToDelete) {
            $error = 'User not found.';
        } else {
            // Check for RESTRICT constraints
            // Check if user has news articles
            $newsStmt = $db->prepare("SELECT COUNT(*) as count FROM " . TABLE_PREFIX . "news WHERE author_id = ?");
            $newsStmt->execute([$userId]);
            $newsCount = $newsStmt->fetch()['count'];
            
            // Check if user has advertiser account
            $advertiserStmt = $db->prepare("SELECT COUNT(*) as count FROM " . TABLE_PREFIX . "advertiser_accounts WHERE user_id = ?");
            $advertiserStmt->execute([$userId]);
            $advertiserCount = $advertiserStmt->fetch()['count'];
            
            if ($newsCount > 0) {
                $error = 'Cannot delete user: User has ' . $newsCount . ' news article(s). Please reassign or delete the news articles first.';
            } elseif ($advertiserCount > 0) {
                $error = 'Cannot delete user: User has an advertiser account. Please delete the advertiser account first.';
            } else {
                // Start transaction
                $db->beginTransaction();
                try {
                    // Set user_id to NULL for businesses (since user_id can be NULL)
                    $businessStmt = $db->prepare("UPDATE " . TABLE_PREFIX . "businesses SET user_id = NULL WHERE user_id = ?");
                    $businessStmt->execute([$userId]);
                    
                    // Delete water_availability records linked to this user
                    $waterStmt = $db->prepare("DELETE FROM " . TABLE_PREFIX . "water_availability WHERE user_id = ?");
                    $waterStmt->execute([$userId]);
                    $waterRecordsDeleted = $waterStmt->rowCount();
                    
                    // Delete user (this will CASCADE delete user_roles, water_user_responses, remember_tokens)
                    $deleteStmt = $db->prepare("DELETE FROM " . TABLE_PREFIX . "users WHERE user_id = ?");
                    $deleteStmt->execute([$userId]);
                    
                    $db->commit();
                    $message = 'User "' . h($userToDelete['username']) . '" (' . h($userToDelete['name'] . ' ' . $userToDelete['surname']) . ') has been deleted successfully.';
                    if ($waterRecordsDeleted > 0) {
                        $message .= ' ' . $waterRecordsDeleted . ' water availability record(s) were also deleted.';
                    }
                    
                    // Reload users list
                    $searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = 'Error deleting user: ' . $e->getMessage();
                    error_log("User deletion error: " . $e->getMessage());
                }
            }
        }
    }
    }
}

// Get search term from GET parameter
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query with optional search
$query = "
    SELECT u.*, 
           GROUP_CONCAT(r.role_name ORDER BY r.role_name SEPARATOR ', ') as roles
    FROM " . TABLE_PREFIX . "users u
    LEFT JOIN " . TABLE_PREFIX . "user_roles ur ON u.user_id = ur.user_id
    LEFT JOIN " . TABLE_PREFIX . "roles r ON ur.role_id = r.role_id
";

// Add WHERE clause if search term is provided
if (!empty($searchTerm)) {
    $query .= " WHERE (
        u.name LIKE :search1 
        OR u.surname LIKE :search2 
        OR CONCAT(u.name, ' ', u.surname) LIKE :search3
        OR u.email LIKE :search4
        OR u.username LIKE :search5
    )";
}

$query .= " GROUP BY u.user_id
    ORDER BY u.surname ASC, u.name ASC
";

$stmt = $db->prepare($query);

// Bind search parameter if provided (bind multiple times for each occurrence)
if (!empty($searchTerm)) {
    $searchPattern = '%' . $searchTerm . '%';
    $stmt->bindValue(':search1', $searchPattern, PDO::PARAM_STR);
    $stmt->bindValue(':search2', $searchPattern, PDO::PARAM_STR);
    $stmt->bindValue(':search3', $searchPattern, PDO::PARAM_STR);
    $stmt->bindValue(':search4', $searchPattern, PDO::PARAM_STR);
    $stmt->bindValue(':search5', $searchPattern, PDO::PARAM_STR);
}

try {
    $stmt->execute();
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Error searching users: ' . $e->getMessage();
    error_log("User search error: " . $e->getMessage());
    $users = [];
}

// Get all available roles
$rolesStmt = $db->query("SELECT * FROM " . TABLE_PREFIX . "roles ORDER BY role_name");
$allRoles = $rolesStmt->fetchAll();

$pageTitle = 'Manage Users';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="content-area">
        <h1>Manage Users</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= h($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>
        
        <div class="users-list">
            <h2>All Users</h2>
            
            <!-- Search Form -->
            <div class="search-form" style="margin-bottom: 2rem; padding: 1.5rem; background-color: var(--white); border-radius: 8px; box-shadow: var(--shadow);">
                <form method="GET" action="" style="display: flex; gap: 1rem; align-items: flex-end;">
                    <div style="flex: 1;">
                        <label for="search" style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Search Users:</label>
                        <input type="text" 
                               id="search" 
                               name="search" 
                               value="<?= h($searchTerm) ?>" 
                               placeholder="Search by name, email, or username..." 
                               style="width: 100%; padding: 0.75rem; font-size: 1rem; border: 1px solid var(--border-color); border-radius: 4px;">
                        <small style="display: block; margin-top: 0.25rem; color: #666;">Search across user names, surnames, full names, emails, and usernames</small>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary" style="padding: 0.75rem 1.5rem;">Search</button>
                        <?php if (!empty($searchTerm)): ?>
                            <a href="<?= baseUrl('/admin/users.php') ?>" class="btn btn-secondary" style="padding: 0.75rem 1.5rem; margin-left: 0.5rem;">Clear</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <?php if (!empty($searchTerm)): ?>
                <div style="margin-bottom: 1rem; padding: 0.75rem; background-color: #e7f3ff; border-left: 3px solid #2196F3; border-radius: 4px;">
                    <strong>Search Results:</strong> Found <?= count($users) ?> user(s) matching "<?= h($searchTerm) ?>"
                </div>
            <?php endif; ?>
            
            <?php if (empty($users)): ?>
                <p><?= !empty($searchTerm) ? 'No users found matching your search.' : 'No users found.' ?></p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Name</th>
                            <th>Email</th>
                            <?php if (!hasRole('USER_ADMIN')): ?>
                                <th>Roles</th>
                            <?php endif; ?>
                            <th>Status</th>
                            <th>Email Verified</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= h($user['username']) ?></td>
                                <td><?= h($user['name'] . ' ' . $user['surname']) ?></td>
                                <td><?= h($user['email']) ?></td>
                                <?php if (!hasRole('USER_ADMIN')): ?>
                                    <td><?= h($user['roles'] ?: 'No roles') ?></td>
                                <?php endif; ?>
                                <td><?= $user['is_active'] ? '<span style="color: green;">Active</span>' : '<span style="color: red;">Inactive</span>' ?></td>
                                <td><?= $user['email_verified'] ? '<span style="color: green;">Yes</span>' : '<span style="color: orange;">No</span>' ?></td>
                                <td><?= formatDate($user['created_at'], 'Y-m-d') ?></td>
                                <td style="white-space: nowrap; min-width: 300px;">
                                    <div style="display: flex; gap: 0.25rem; flex-wrap: nowrap; align-items: center;">
                                        <a href="<?= baseUrl('/admin/edit-user.php?id=' . $user['user_id']) ?>" class="btn btn-sm btn-primary" style="flex-shrink: 0;">Edit</a>
                                        <?php if (hasRole('ADMIN')): ?>
                                            <a href="<?= baseUrl('/admin/edit-user-roles.php?id=' . $user['user_id']) ?>" class="btn btn-sm btn-secondary" style="flex-shrink: 0;">Roles</a>
                                        <?php endif; ?>
                                        <?php if (!$user['email_verified']): ?>
                                            <form method="POST" action="" style="display: inline-flex; margin: 0; flex-shrink: 0;" onsubmit="return confirm('Resend verification email to <?= h(addslashes($user['email'])) ?>?');">
                                                <input type="hidden" name="action" value="resend_verification">
                                                <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                                <button type="submit" class="btn btn-sm" style="background-color: #17a2b8; color: white; border: none; margin: 0; white-space: nowrap;">Resend</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if (hasRole('ADMIN')): ?>
                                            <button type="button" 
                                                    class="btn btn-sm btn-danger" 
                                                    onclick="confirmDeleteUser(<?= $user['user_id'] ?>, '<?= h(addslashes($user['username'])) ?>', '<?= h(addslashes($user['name'] . ' ' . $user['surname'])) ?>')"
                                                    style="flex-shrink: 0;">Delete</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <div id="roles" class="roles-section">
            <h2>Available Roles</h2>
            <ul>
                <?php foreach ($allRoles as $role): ?>
                    <li><strong><?= h($role['role_name']) ?></strong> - <?= h($role['role_description'] ?: 'No description') ?></li>
                <?php endforeach; ?>
            </ul>
            <p><small>To assign roles to users, use phpMyAdmin or create a role management interface.</small></p>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 10000; align-items: center; justify-content: center;" class="delete-modal-overlay">
    <div style="background-color: white; padding: 2rem; border-radius: 8px; max-width: 500px; width: 90%; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        <h2 style="margin-top: 0; color: #dc3545;">Confirm User Deletion</h2>
        <p><strong>Warning:</strong> This action cannot be undone!</p>
        <p>You are about to delete user: <strong id="deleteUserName"></strong></p>
        <p style="color: #856404; background-color: #fff3cd; padding: 1rem; border-radius: 4px; border-left: 4px solid #ffc107;">
            <strong>This will:</strong>
            <ul style="margin: 0.5rem 0; padding-left: 1.5rem;">
                <li>Delete the user account</li>
                <li>Delete all user roles</li>
                <li>Delete all water question responses</li>
                <li>Delete remember tokens</li>
                <li>Delete all water availability records linked to this user</li>
                <li>Set businesses to have no user (user_id = NULL)</li>
            </ul>
            <strong style="color: #dc3545;">Note:</strong> If the user has news articles or an advertiser account, deletion will be prevented.
        </p>
        <form method="POST" action="" id="deleteForm" style="margin-top: 1.5rem;">
            <input type="hidden" name="action" value="delete_user">
            <input type="hidden" name="user_id" id="deleteUserId">
            <div style="margin-bottom: 1rem;">
                <label style="display: flex; align-items: center; cursor: pointer;">
                    <input type="checkbox" name="confirm_delete" value="1" required style="margin-right: 0.5rem;">
                    <span>I understand this action cannot be undone</span>
                </label>
            </div>
            <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                <button type="button" onclick="closeDeleteModal()" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete User</button>
            </div>
        </form>
    </div>
</div>

<style>
.delete-modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    z-index: 10000;
    align-items: center;
    justify-content: center;
}

.delete-modal-overlay[style*="display: flex"] {
    display: flex !important;
}
</style>

<script>
function confirmDeleteUser(userId, username, fullName) {
    document.getElementById('deleteUserId').value = userId;
    document.getElementById('deleteUserName').textContent = username + ' (' + fullName + ')';
    document.getElementById('deleteModal').style.display = 'flex';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
    document.getElementById('deleteForm').reset();
}

// Close modal when clicking outside
document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeDeleteModal();
    }
});
</script>

<?php 
$hideAdverts = true;
include __DIR__ . '/../includes/footer.php'; 
?>

