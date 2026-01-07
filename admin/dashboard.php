<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('ADMIN');

$pageTitle = 'Admin Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="content-area">
        <h1>Admin Dashboard</h1>
        
        <div class="admin-links">
            <div class="admin-link-card">
                <h2>News Management</h2>
                <p>Create and manage news articles</p>
                <div class="admin-buttons">
                    <a href="<?= baseUrl('/admin/news.php') ?>" class="btn btn-primary">Manage News</a>
                    <a href="<?= baseUrl('/admin/news.php') ?>" class="btn btn-secondary btn-sm">View All</a>
                    <a href="<?= baseUrl('/admin/news.php') ?>#create" class="btn btn-secondary btn-sm">Create</a>
                </div>
            </div>
            
            <div class="admin-link-card">
                <h2>Business Categories</h2>
                <p>Manage business categories</p>
                <div class="admin-buttons">
                    <a href="<?= baseUrl('/admin/businesses.php') ?>" class="btn btn-primary">Manage Categories</a>
                    <a href="<?= baseUrl('/admin/businesses.php') ?>" class="btn btn-secondary btn-sm">View All</a>
                    <a href="<?= baseUrl('/admin/businesses.php') ?>#create" class="btn btn-secondary btn-sm">Create</a>
                </div>
            </div>
            
            <div class="admin-link-card">
                <h2>Import Businesses</h2>
                <p>Import businesses from PDF or text</p>
                <div class="admin-buttons">
                    <a href="<?= baseUrl('/admin/import-businesses.php') ?>" class="btn btn-primary">Import Businesses</a>
                    <a href="<?= baseUrl('/admin/import-businesses.php') ?>" class="btn btn-secondary btn-sm">Import PDF</a>
                    <a href="<?= baseUrl('/businesses.php') ?>" class="btn btn-secondary btn-sm">View All</a>
                </div>
            </div>
            
            <div class="admin-link-card">
                <h2>Manage Businesses</h2>
                <p>View, edit, and delete all businesses</p>
                <div class="admin-buttons">
                    <a href="<?= baseUrl('/admin/manage-businesses.php') ?>" class="btn btn-primary">Manage Businesses</a>
                    <a href="<?= baseUrl('/admin/manage-businesses.php') ?>" class="btn btn-secondary btn-sm">View All</a>
                    <a href="<?= baseUrl('/admin/approve-businesses.php') ?>" class="btn btn-secondary btn-sm">Approve</a>
                </div>
            </div>
            
            <div class="admin-link-card">
                <h2>Approve Businesses</h2>
                <p>Review and approve business submissions</p>
                <div class="admin-buttons">
                    <a href="<?= baseUrl('/admin/approve-businesses.php') ?>" class="btn btn-primary">Approve Businesses</a>
                    <a href="<?= baseUrl('/admin/approve-businesses.php') ?>" class="btn btn-secondary btn-sm">View Pending</a>
                </div>
            </div>
            
            <div class="admin-link-card">
                <h2>Water Questions</h2>
                <p>Manage water information questions</p>
                <div class="admin-buttons">
                    <a href="<?= baseUrl('/admin/manage-water-questions.php') ?>" class="btn btn-primary">Manage Questions</a>
                    <a href="<?= baseUrl('/admin/manage-water-questions.php') ?>" class="btn btn-secondary btn-sm">View All</a>
                </div>
            </div>
            
            <div class="admin-link-card">
                <h2>Users</h2>
                <p>Manage user accounts and roles</p>
                <div class="admin-buttons">
                    <a href="<?= baseUrl('/admin/users.php') ?>" class="btn btn-primary">Manage Users</a>
                    <a href="<?= baseUrl('/admin/users.php') ?>" class="btn btn-secondary btn-sm">View All</a>
                    <a href="<?= baseUrl('/admin/users.php') ?>#roles" class="btn btn-secondary btn-sm">Roles</a>
                </div>
            </div>
            
            <div class="admin-link-card">
                <h2>Geocoding Settings</h2>
                <p>Configure geocoding provider (Nominatim or Google Maps)</p>
                <div class="admin-buttons">
                    <a href="<?= baseUrl('/admin/geocoding-settings.php') ?>" class="btn btn-primary">Geocoding Settings</a>
                </div>
            </div>
            
            <div class="admin-link-card">
                <h2>Re-geocode Addresses</h2>
                <p>Update coordinates for all user addresses</p>
                <div class="admin-buttons">
                    <a href="<?= baseUrl('/admin/regeocode-addresses.php') ?>" class="btn btn-primary">Re-geocode Addresses</a>
                </div>
            </div>
            
            <div class="admin-link-card">
                <h2>Daily Report Emails</h2>
                <p>Manage email recipients for daily water reports</p>
                <div class="admin-buttons">
                    <a href="<?= baseUrl('/admin/manage-report-emails.php') ?>" class="btn btn-primary">Manage Emails</a>
                </div>
            </div>
            
            <div class="admin-link-card">
                <h2>Force Logout</h2>
                <p>Log out all users and clear sessions</p>
                <div class="admin-buttons">
                    <a href="<?= baseUrl('/admin/force-logout.php') ?>" class="btn btn-primary">Force Logout</a>
                </div>
            </div>
        </div>
    </div>
</div>


<?php 
$hideAdverts = true;
include __DIR__ . '/../includes/footer.php'; 
?>


