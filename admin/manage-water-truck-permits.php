<?php
require_once __DIR__ . '/../includes/functions.php';
requireAnyRole(['ADMIN', 'ANALYTICS']);

$db = getDB();
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $dateCaptured = trim($_POST['date_captured'] ?? '');
            $registrationNumber = trim($_POST['registration_number'] ?? '');
            $permitNumber = trim($_POST['permit_number'] ?? '');
            
            if (empty($dateCaptured)) {
                $error = 'Please enter a date captured.';
            } elseif (empty($registrationNumber)) {
                $error = 'Please enter a registration number.';
            } elseif (empty($permitNumber)) {
                $error = 'Please enter a permit number.';
            } else {
                try {
                    $stmt = $db->prepare("
                        INSERT INTO " . TABLE_PREFIX . "water_truck_permits 
                        (date_captured, registration_number, permit_number) 
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([
                        $dateCaptured,
                        $registrationNumber,
                        $permitNumber
                    ]);
                    $success = 'Permit added successfully!';
                } catch (PDOException $e) {
                    $error = 'Error adding permit: ' . $e->getMessage();
                    error_log("Add water truck permit error: " . $e->getMessage());
                }
            }
        } elseif ($_POST['action'] === 'update') {
            $permitId = (int)($_POST['permit_id'] ?? 0);
            $dateCaptured = trim($_POST['date_captured'] ?? '');
            $registrationNumber = trim($_POST['registration_number'] ?? '');
            $permitNumber = trim($_POST['permit_number'] ?? '');
            
            if (empty($dateCaptured)) {
                $error = 'Please enter a date captured.';
            } elseif (empty($registrationNumber)) {
                $error = 'Please enter a registration number.';
            } elseif (empty($permitNumber)) {
                $error = 'Please enter a permit number.';
            } elseif ($permitId <= 0) {
                $error = 'Invalid permit ID.';
            } else {
                try {
                    $stmt = $db->prepare("
                        UPDATE " . TABLE_PREFIX . "water_truck_permits 
                        SET date_captured = ?, registration_number = ?, permit_number = ? 
                        WHERE permit_id = ?
                    ");
                    $stmt->execute([
                        $dateCaptured,
                        $registrationNumber,
                        $permitNumber,
                        $permitId
                    ]);
                    $success = 'Permit updated successfully!';
                } catch (PDOException $e) {
                    $error = 'Error updating permit: ' . $e->getMessage();
                    error_log("Update water truck permit error: " . $e->getMessage());
                }
            }
        } elseif ($_POST['action'] === 'delete') {
            $permitId = (int)($_POST['permit_id'] ?? 0);
            
            if ($permitId <= 0) {
                $error = 'Invalid permit ID.';
            } else {
                try {
                    $stmt = $db->prepare("
                        DELETE FROM " . TABLE_PREFIX . "water_truck_permits 
                        WHERE permit_id = ?
                    ");
                    $stmt->execute([$permitId]);
                    $success = 'Permit deleted successfully!';
                } catch (Exception $e) {
                    $error = 'Error deleting permit: ' . $e->getMessage();
                    error_log("Delete water truck permit error: " . $e->getMessage());
                }
            }
        }
    }
}

// Get all permits
$stmt = $db->query("
    SELECT * 
    FROM " . TABLE_PREFIX . "water_truck_permits 
    ORDER BY date_captured DESC, permit_id DESC
");
$permits = $stmt->fetchAll();

$pageTitle = 'Manage Water Truck Permits';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="content-area">
        <h1>Manage Water Truck Permits</h1>
        
        <p><a href="<?= baseUrl('/admin/dashboard.php') ?>" class="btn btn-secondary">← Back to Dashboard</a></p>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?= h($success) ?></div>
        <?php endif; ?>
        
        <!-- Add Permit Form -->
        <div class="admin-section" style="background: #f5f5f5; padding: 20px; border-radius: 4px; margin-bottom: 30px;">
            <h2>Add New Permit</h2>
            <form method="POST" action="" id="addPermitForm">
                <input type="hidden" name="action" value="add">
                
                <div class="form-group">
                    <label for="date_captured">Date Captured: <span class="required">*</span></label>
                    <input type="date" id="date_captured" name="date_captured" required value="<?= date('Y-m-d') ?>" style="width: 100%; max-width: 400px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                
                <div class="form-group">
                    <label for="registration_number">Registration Number: <span class="required">*</span></label>
                    <input type="text" id="registration_number" name="registration_number" required style="width: 100%; max-width: 400px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" placeholder="e.g., CA 123-456">
                </div>
                
                <div class="form-group">
                    <label for="permit_number">Permit Number: <span class="required">*</span></label>
                    <input type="text" id="permit_number" name="permit_number" required style="width: 100%; max-width: 400px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" placeholder="e.g., PER-2024-001">
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Add Permit</button>
                </div>
            </form>
        </div>
        
        <!-- Permits List -->
        <div class="admin-section">
            <h2>Existing Permits</h2>
            
            <?php if (empty($permits)): ?>
                <p>No permits found.</p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="data-table" style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr>
                                <th style="text-align: left;">Date Captured</th>
                                <th style="text-align: left;">Registration Number</th>
                                <th style="text-align: left;">Permit Number</th>
                                <th style="text-align: left;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($permits as $permit): ?>
                                <tr style="border-bottom: 1px solid #ddd;">
                                    <td style="padding: 10px; vertical-align: top;">
                                        <form method="POST" action="" class="permit-edit-form" data-permit-id="<?= $permit['permit_id'] ?>">
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="permit_id" value="<?= $permit['permit_id'] ?>">
                                            <div style="margin-bottom: 5px;">
                                                <input type="date" name="date_captured" value="<?= h($permit['date_captured']) ?>" required style="width: 100%; max-width: 200px; padding: 6px; border: 1px solid #ddd; border-radius: 4px;">
                                            </div>
                                    </td>
                                    <td style="padding: 10px; vertical-align: top;">
                                            <div style="margin-bottom: 5px;">
                                                <input type="text" name="registration_number" value="<?= h($permit['registration_number']) ?>" required style="width: 100%; max-width: 200px; padding: 6px; border: 1px solid #ddd; border-radius: 4px;" placeholder="Registration Number">
                                            </div>
                                    </td>
                                    <td style="padding: 10px; vertical-align: top;">
                                            <div style="margin-bottom: 5px;">
                                                <input type="text" name="permit_number" value="<?= h($permit['permit_number']) ?>" required style="width: 100%; max-width: 200px; padding: 6px; border: 1px solid #ddd; border-radius: 4px;" placeholder="Permit Number">
                                            </div>
                                    </td>
                                    <td style="padding: 10px; vertical-align: top;">
                                            <button type="submit" class="btn btn-secondary btn-sm" style="padding: 6px 12px; margin-right: 5px;">Update</button>
                                        </form>
                                        <form method="POST" action="" style="display: inline-block;" onsubmit="return confirm('Are you sure you want to delete this permit?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="permit_id" value="<?= $permit['permit_id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" style="padding: 6px 12px;">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php 
include __DIR__ . '/../includes/footer.php'; 
?>
