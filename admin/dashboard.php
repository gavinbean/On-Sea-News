<?php
require_once __DIR__ . '/../includes/functions.php';
// Allow both ADMIN and USER_ADMIN roles
requireAnyRole(['ADMIN', 'USER_ADMIN']);

$pageTitle = 'Admin Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="content-area">
        <h1>Admin Dashboard</h1>
        
        <div class="admin-links">
            <?php if (hasAnyRole(['ADMIN', 'PUBLISHER'])): ?>
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
                <h2>Approve News</h2>
                <p>Review and approve submitted news items</p>
                <div class="admin-buttons">
                    <a href="<?= baseUrl('/admin/approve-news.php') ?>" class="btn btn-primary">Approve News</a>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (hasRole('ADMIN')): ?>
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
                <h2>Pricing Options</h2>
                <p>Manage advertising pricing options</p>
                <div class="admin-buttons">
                    <a href="<?= baseUrl('/admin/manage-pricing-options.php') ?>" class="btn btn-primary">Manage Pricing</a>
                    <a href="<?= baseUrl('/admin/my-businesses-admin.php') ?>" class="btn btn-secondary btn-sm">Test Page</a>
                </div>
            </div>
            
            <div class="admin-link-card">
                <h2>My Businesses (Admin)</h2>
                <p>Test and manage businesses with pricing options</p>
                <div class="admin-buttons">
                    <a href="<?= baseUrl('/admin/my-businesses-admin.php') ?>" class="btn btn-primary">My Businesses</a>
                    <a href="<?= baseUrl('/admin/manage-pricing-options.php') ?>" class="btn btn-secondary btn-sm">Pricing Options</a>
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
            <?php endif; ?>
            
            <?php if (hasAnyRole(['ADMIN', 'USER_ADMIN'])): ?>
            <div class="admin-link-card">
                <h2>Users</h2>
                <p>Manage user accounts<?= hasRole('ADMIN') ? ' and roles' : '' ?></p>
                <div class="admin-buttons">
                    <a href="<?= baseUrl('/admin/users.php') ?>" class="btn btn-primary">Manage Users</a>
                    <a href="<?= baseUrl('/admin/users.php') ?>" class="btn btn-secondary btn-sm">View All</a>
                    <?php if (hasRole('ADMIN')): ?>
                        <a href="<?= baseUrl('/admin/users.php') ?>#roles" class="btn btn-secondary btn-sm">Roles</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (hasRole('ADMIN')): ?>
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
                <h2>Import Water Data</h2>
                <p>Import water availability data from CSV file</p>
                <div class="admin-buttons">
                    <a href="<?= baseUrl('/admin/import-water-data.php') ?>" class="btn btn-primary">Import Water Data</a>
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
            
            <div class="admin-link-card">
                <h2>Manage FAQ</h2>
                <p>Manage frequently asked questions</p>
                <div class="admin-buttons">
                    <a href="<?= baseUrl('/admin/manage-faq.php') ?>" class="btn btn-primary">Manage FAQ</a>
                    <a href="<?= baseUrl('/admin/manage-faq.php') ?>" class="btn btn-secondary btn-sm">View All</a>
                    <a href="<?= baseUrl('/admin/manage-faq.php') ?>#create" class="btn btn-secondary btn-sm">Create</a>
                </div>
            </div>
            
            <div class="admin-link-card">
                <h2>Contact Queries</h2>
                <p>View and reply to contact form submissions</p>
                <div class="admin-buttons">
                    <a href="<?= baseUrl('/admin/contact-queries.php') ?>" class="btn btn-primary">Contact Queries</a>
                    <a href="<?= baseUrl('/admin/contact-queries.php?status=new') ?>" class="btn btn-secondary btn-sm">New</a>
                </div>
            </div>
            
            <?php endif; ?>
            
            <?php if (hasAnyRole(['ADMIN', 'ANALYTICS'])): ?>
            <div class="admin-link-card">
                <h2>Water Delivery Companies</h2>
                <p>Manage water delivery company names</p>
                <div class="admin-buttons">
                    <a href="<?= baseUrl('/admin/manage-water-delivery-companies.php') ?>" class="btn btn-primary">Manage Companies</a>
                </div>
            </div>
            
            <div class="admin-link-card">
                <h2>Water Truck Permits</h2>
                <p>Manage water truck permits</p>
                <div class="admin-buttons">
                    <a href="<?= baseUrl('/admin/manage-water-truck-permits.php') ?>" class="btn btn-primary">Manage Permits</a>
                </div>
            </div>
            
            <div class="admin-link-card">
                <h2>Tanker Reports</h2>
                <p>View and manage tanker reports on a map</p>
                <div class="admin-buttons">
                    <a href="<?= baseUrl('/admin/view-tanker-reports.php') ?>" class="btn btn-primary">View Reports</a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>


<?php 
$hideAdverts = true;
include __DIR__ . '/../includes/footer.php'; 
?>


