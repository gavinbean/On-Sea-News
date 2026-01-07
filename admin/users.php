<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('ADMIN');

$db = getDB();
$message = '';
$error = '';

// Get all users with their roles
$stmt = $db->query("
    SELECT u.*, 
           GROUP_CONCAT(r.role_name ORDER BY r.role_name SEPARATOR ', ') as roles
    FROM " . TABLE_PREFIX . "users u
    LEFT JOIN " . TABLE_PREFIX . "user_roles ur ON u.user_id = ur.user_id
    LEFT JOIN " . TABLE_PREFIX . "roles r ON ur.role_id = r.role_id
    GROUP BY u.user_id
    ORDER BY u.created_at DESC
");
$users = $stmt->fetchAll();

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
            <?php if (empty($users)): ?>
                <p>No users found.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Roles</th>
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
                                <td><?= h($user['roles'] ?: 'No roles') ?></td>
                                <td><?= $user['is_active'] ? '<span style="color: green;">Active</span>' : '<span style="color: red;">Inactive</span>' ?></td>
                                <td><?= $user['email_verified'] ? '<span style="color: green;">Yes</span>' : '<span style="color: orange;">No</span>' ?></td>
                                <td><?= formatDate($user['created_at'], 'Y-m-d') ?></td>
                                <td>
                                    <a href="<?= baseUrl('/admin/edit-user.php?id=' . $user['user_id']) ?>" class="btn btn-sm btn-primary">Edit</a>
                                    <a href="<?= baseUrl('/admin/edit-user-roles.php?id=' . $user['user_id']) ?>" class="btn btn-sm btn-secondary">Edit Roles</a>
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

<?php 
$hideAdverts = true;
include __DIR__ . '/../includes/footer.php'; 
?>

