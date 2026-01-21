<?php
require_once 'includes/functions.php';

$db = getDB();
$search = $_GET['search'] ?? '';
$categoryId = isset($_GET['category']) ? (int)$_GET['category'] : 0;

// Build query
$where = [];
$params = [];

// Only show approved businesses (user_id can be NULL for imported businesses)
$where[] = "b.is_approved = 1";

// For admins, only show businesses with valid pricing status
// For regular users, show all approved businesses regardless of pricing status
$isAdmin = hasRole('ADMIN');
if ($isAdmin) {
    $where[] = "b.pricing_status IS NOT NULL AND b.pricing_status != ''";
}

if (!empty($search)) {
    $where[] = "(b.business_name LIKE ? OR b.contact_name LIKE ? OR b.description LIKE ? OR b.email LIKE ? OR b.telephone LIKE ? OR c.category_name LIKE ?)";
    $searchTerm = '%' . $search . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($categoryId > 0) {
    $where[] = "b.category_id = ?";
    $params[] = $categoryId;
}

$whereClause = implode(' AND ', $where);

// Get total count for pagination
$countStmt = $db->prepare("
    SELECT COUNT(*) as total
    FROM " . TABLE_PREFIX . "businesses b
    LEFT JOIN " . TABLE_PREFIX . "business_categories c ON b.category_id = c.category_id
    WHERE $whereClause
");
$countStmt->execute($params);
$total = $countStmt->fetch()['total'];

// Get businesses
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Get all businesses (no pagination for grouped view)
$stmt = $db->prepare("
    SELECT b.*, c.category_name, c.display_order
    FROM " . TABLE_PREFIX . "businesses b
    LEFT JOIN " . TABLE_PREFIX . "business_categories c ON b.category_id = c.category_id
    WHERE $whereClause
    ORDER BY c.display_order, c.category_name, b.business_name
");
$stmt->execute($params);
$allBusinesses = $stmt->fetchAll();

// Group businesses by category
$businessesByCategory = [];
foreach ($allBusinesses as $business) {
    $categoryName = $business['category_name'] ?: 'Uncategorized';
    if (!isset($businessesByCategory[$categoryName])) {
        $businessesByCategory[$categoryName] = [];
    }
    $businessesByCategory[$categoryName][] = $business;
}

// Get all categories for filter
$categoriesStmt = $db->query("
    SELECT c.*, COUNT(b.business_id) as business_count
    FROM " . TABLE_PREFIX . "business_categories c
    LEFT JOIN " . TABLE_PREFIX . "businesses b ON c.category_id = b.category_id
    GROUP BY c.category_id
    ORDER BY c.display_order, c.category_name
");
$categories = $categoriesStmt->fetchAll();

$pageTitle = 'Business Directory';
include 'includes/header.php';
?>

<div class="container">
    <div class="content-area">
        <h1>Business Directory</h1>
        
        <div class="business-search">
            <form method="GET" action="" class="search-form">
                <div class="search-row">
                    <div class="search-field">
                        <label for="search">Search:</label>
                        <input type="text" id="search" name="search" value="<?= h($search) ?>" placeholder="Search by business name, category, contact name, email, or phone...">
                    </div>
                    
                    <div class="search-field">
                        <label for="category">Category:</label>
                        <select id="category" name="category">
                            <option value="0">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['category_id'] ?>" <?= $categoryId == $cat['category_id'] ? 'selected' : '' ?>>
                                    <?= h($cat['category_name']) ?> (<?= $cat['business_count'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="search-actions">
                        <button type="submit" class="btn btn-primary">Search</button>
                        <a href="<?= baseUrl('/businesses.php') ?>" class="btn btn-secondary">Clear</a>
                    </div>
                </div>
            </form>
        </div>
        
        <?php if (empty($businessesByCategory)): ?>
            <div class="no-results">
                <p>No businesses found<?= !empty($search) || $categoryId > 0 ? ' matching your criteria' : '' ?>.</p>
            </div>
        <?php else: ?>
            <div class="business-results">
                <p class="results-count">Total: <?= $total ?> businesses</p>
                
                <div class="business-spreadsheet">
                    <?php foreach ($businessesByCategory as $categoryName => $categoryBusinesses): ?>
                        <div class="category-section">
                            <div class="category-header-row">
                                <div class="category-name"><?= h($categoryName) ?></div>
                            </div>
                            <table class="business-table">
                                <thead>
                                    <tr>
                                        <th class="col-name">Business Name</th>
                                        <th class="col-contact">Contact</th>
                                        <th class="col-phone">Phone</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categoryBusinesses as $business): ?>
                                        <tr>
                                            <td class="col-name" data-label="Business">
                                                <a href="#" class="business-name-link" data-business-id="<?= $business['business_id'] ?>" onclick="showBusinessModal(<?= $business['business_id'] ?>); return false;">
                                                    <?= h($business['business_name']) ?>
                                                </a>
                                            </td>
                                            <td class="col-contact" data-label="Contact"><?= h($business['contact_name'] ?: 'N/A') ?></td>
                                            <td class="col-phone" data-label="Phone">
                                                <?php if ($business['telephone']): ?>
                                                    <a href="tel:<?= h($business['telephone']) ?>" style="word-break: break-all; white-space: normal;"><?= h($business['telephone']) ?></a>
                                                <?php else: ?>
                                                    N/A
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.business-search {
    background-color: var(--white);
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: var(--shadow);
    margin-bottom: 2rem;
}

.search-form {
    width: 100%;
}

.search-row {
    display: flex;
    gap: 1rem;
    align-items: flex-end;
    flex-wrap: wrap;
}

.search-field {
    flex: 1;
    min-width: 200px;
}

.search-field label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
}

.search-field input,
.search-field select {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 1rem;
}

.search-actions {
    display: flex;
    gap: 0.5rem;
}

.business-results {
    margin-top: 2rem;
}

.results-count {
    color: #666;
    margin-bottom: 1rem;
}

.business-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.business-card {
    background-color: var(--white);
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: var(--shadow);
    transition: transform 0.2s, box-shadow 0.2s;
}

.business-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.business-header {
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--border-color);
}

.business-header h2 {
    margin: 0 0 0.5rem 0;
    font-size: 1.25rem;
}

.business-header h2 a {
    color: var(--primary-color);
    text-decoration: none;
}

.business-header h2 a:hover {
    text-decoration: underline;
}

.business-category {
    display: inline-block;
    background-color: var(--bg-color);
    padding: 0.25rem 0.75rem;
    border-radius: 4px;
    font-size: 0.875rem;
    color: #666;
}

.business-info {
    margin-bottom: 1rem;
}

.business-info p {
    margin: 0.5rem 0;
    font-size: 0.9rem;
}

.business-info strong {
    color: var(--text-color);
}

.business-description {
    margin-top: 0.75rem;
    color: #666;
    font-style: italic;
}

.business-actions {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border-color);
}

.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
}

.no-results {
    background-color: var(--white);
    padding: 3rem;
    border-radius: 8px;
    box-shadow: var(--shadow);
    text-align: center;
    color: #666;
}

.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 1rem;
    margin-top: 2rem;
    padding: 1rem;
}

@media (max-width: 768px) {
    .search-row {
        flex-direction: column;
    }
    
    .search-field {
        width: 100%;
        min-width: 100%;
    }
    
    .search-actions {
        width: 100%;
    }
    
    .search-actions .btn {
        flex: 1;
    }
    
    /* Convert table to card layout on mobile */
    .business-spreadsheet {
        overflow-x: visible;
    }
    
    .business-table,
    .business-table thead,
    .business-table tbody,
    .business-table th,
    .business-table td,
    .business-table tr {
        display: block;
    }
    
    .business-table thead {
        display: none;
    }
    
    .business-table tbody tr {
        background-color: var(--white);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        margin-bottom: 1rem;
        padding: 1rem;
        box-shadow: var(--shadow);
    }
    
    .business-table tbody tr:last-child {
        margin-bottom: 0;
    }
    
    .business-table td {
        border: none;
        padding: 0.5rem 0;
        text-align: left;
        position: relative;
        padding-left: 0;
        margin-bottom: 0.75rem;
        display: flex;
        flex-direction: column;
    }
    
    .business-table td:before {
        content: attr(data-label);
        position: relative;
        left: 0;
        width: 100%;
        font-weight: 600;
        color: var(--text-color);
        margin-bottom: 0.25rem;
        display: block;
    }
    
    .business-table td:last-child {
        margin-bottom: 0;
    }
    
    .business-table td.col-name:before {
        content: "Business:";
    }
    
    .business-table td.col-contact:before {
        content: "Contact:";
    }
    
    .business-table td.col-phone:before {
        content: "Phone:";
    }
    
    .col-name,
    .col-contact,
    .col-phone {
        width: 100% !important;
        min-width: 100% !important;
        display: block;
    }
    
    .business-table a {
        word-break: break-word;
        display: inline-block;
        max-width: 100%;
    }
    
    .business-modal-content {
        width: 95% !important;
        max-width: 95% !important;
        margin: 5% auto !important;
        min-width: 95% !important;
    }
}

.business-spreadsheet {
    margin-top: 2rem;
    overflow-x: auto;
}

.category-section {
    margin-bottom: 2rem;
    background-color: var(--white);
    border-radius: 8px;
    box-shadow: var(--shadow);
    overflow: hidden;
}

.category-header-row {
    background-color: var(--primary-color);
    color: var(--white);
    padding: 1rem 1.5rem;
    font-weight: 600;
    font-size: 1.25rem;
}

.category-name {
    margin: 0;
}

.business-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.95rem;
}

.business-table thead {
    background-color: var(--bg-color);
}

.business-table th {
    padding: 0.75rem 1rem;
    text-align: left;
    font-weight: 600;
    border-bottom: 2px solid var(--border-color);
    color: var(--text-color);
}

.business-table td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--border-color);
    vertical-align: middle;
}

.business-table tbody tr:hover {
    background-color: #f8f9fa;
}

.business-table tbody tr:last-child td {
    border-bottom: none;
}

.col-name {
    width: 40%;
    min-width: 200px;
}

.col-contact {
    width: 30%;
    min-width: 150px;
}

.col-phone {
    width: 30%;
    min-width: 150px;
    word-break: break-word;
    overflow-wrap: break-word;
}

.col-phone a {
    word-break: break-all;
    white-space: normal;
}

.business-name-link {
    color: var(--primary-color);
    text-decoration: none;
    font-weight: 500;
    transition: color 0.2s;
}

.business-name-link:hover {
    color: var(--primary-color);
    text-decoration: underline;
}

.business-table a {
    color: var(--primary-color);
    text-decoration: none;
}

.business-table a:hover {
    text-decoration: underline;
}

.business-modal {
    display: none;
    position: fixed !important;
    z-index: 99999 !important;
    left: 0 !important;
    top: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    background-color: rgba(0, 0, 0, 0.75) !important;
    overflow: auto;
    margin: 0 !important;
    padding: 0 !important;
}

.business-modal-content {
    background-color: var(--white);
    margin: 2% auto;
    padding: 0;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    width: auto;
    min-width: 400px;
    max-width: 90%;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
    position: relative;
    z-index: 100000;
}

.business-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid var(--border-color);
    background-color: var(--primary-color);
    color: var(--white);
    border-radius: 8px 8px 0 0;
}

.business-modal-header h2 {
    margin: 0;
    color: var(--white);
}

.business-modal-body {
    flex: 1;
    padding: 1.5rem;
    overflow-y: auto;
    min-width: 0; /* Allow content to shrink */
}

.business-modal-body p {
    margin: 0.75rem 0;
}

.modal-category {
    font-size: 0.9rem;
    color: #666;
    margin-bottom: 1rem !important;
}

.modal-description {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border-color);
}

.modal-description p {
    margin-top: 0.5rem;
    line-height: 1.6;
}
</style>

<!-- Business Details Modal -->
<div id="business-modal" class="business-modal">
    <div class="business-modal-content">
        <div class="business-modal-header">
            <h2 id="modal-business-name"></h2>
            <button type="button" class="modal-close-btn" onclick="closeBusinessModal()" aria-label="Close">&times;</button>
        </div>
        <div class="business-modal-body" id="modal-business-details">
            <!-- Content loaded via AJAX -->
        </div>
    </div>
</div>

<script>
function showBusinessModal(businessId) {
    let modal = document.getElementById('business-modal');
    
    if (!modal) {
        console.error('Business modal not found');
        return;
    }
    
    // Move modal to body if it's not already there (ensures it's not constrained by parent containers)
    if (modal.parentElement !== document.body) {
        console.log('Moving business modal to body');
        document.body.appendChild(modal);
    }
    
    // Remove the modal from DOM and re-add it to force browser to recognize it
    const modalClone = modal.cloneNode(true);
    modal.remove();
    modal = modalClone;
    modal.id = 'business-modal';
    document.body.appendChild(modal);
    
    // Re-attach event listeners after cloning
    const closeBtn = modal.querySelector('.modal-close-btn');
    if (closeBtn) {
        closeBtn.removeAttribute('onclick');
        closeBtn.addEventListener('click', closeBusinessModal);
    }
    
    // Get references to elements in the cloned modal
    const modalName = modal.querySelector('#modal-business-name');
    const modalDetails = modal.querySelector('#modal-business-details');
    
    // Show loading
    if (modalDetails) {
        modalDetails.innerHTML = '<p>Loading...</p>';
    }
    
    // Show modal with explicit positioning to ensure it appears as overlay
    modal.style.cssText = 'display: block !important; position: fixed !important; z-index: 99999 !important; left: 0 !important; top: 0 !important; width: 100vw !important; height: 100vh !important; background-color: rgba(0, 0, 0, 0.75) !important; margin: 0 !important; padding: 0 !important; overflow: auto !important; visibility: visible !important; opacity: 1 !important;';
    document.body.style.overflow = 'hidden';
    
    // Fetch business details
    fetch('<?= baseUrl('/api/business-details.php') ?>?id=' + businessId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const business = data.business;
                
                if (modalName) modalName.textContent = business.business_name;
                
                let html = '';
                if (business.category_name) {
                    html += '<p class="modal-category"><strong>Category:</strong> ' + escapeHtml(business.category_name) + '</p>';
                }
                if (business.contact_name) {
                    html += '<p><strong>Contact:</strong> ' + escapeHtml(business.contact_name) + '</p>';
                }
                if (business.telephone) {
                    html += '<p><strong>Phone:</strong> <a href="tel:' + escapeHtml(business.telephone) + '">' + escapeHtml(business.telephone) + '</a></p>';
                }
                if (business.email) {
                    html += '<p><strong>Email:</strong> <a href="mailto:' + escapeHtml(business.email) + '">' + escapeHtml(business.email) + '</a></p>';
                }
                if (business.address) {
                    html += '<p><strong>Address:</strong> ' + escapeHtml(business.address) + '</p>';
                }
                if (business.website) {
                    html += '<p><strong>Website:</strong> <a href="' + escapeHtml(business.website) + '" target="_blank">' + escapeHtml(business.website) + '</a></p>';
                }
                if (business.description) {
                    html += '<div class="modal-description"><strong>Description:</strong><p>' + escapeHtml(business.description).replace(/\n/g, '<br>') + '</p></div>';
                }
                
                if (modalDetails) modalDetails.innerHTML = html;
                
                // Resize modal to fit content after loading
                setTimeout(() => {
                    const modalContent = modal.querySelector('.business-modal-content');
                    if (modalContent) {
                        // Reset width to auto to recalculate
                        modalContent.style.width = 'auto';
                        // Get the actual content width
                        const contentWidth = modalDetails.scrollWidth;
                        // Set width to content width + padding, but not more than 90% of viewport
                        const maxWidth = Math.min(window.innerWidth * 0.9, contentWidth + 60);
                        modalContent.style.width = maxWidth + 'px';
                        modalContent.style.minWidth = '400px';
                    }
                }, 100);
            } else {
                if (modalDetails) modalDetails.innerHTML = '<p class="error">Error loading business details.</p>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (modalDetails) modalDetails.innerHTML = '<p class="error">Error loading business details.</p>';
        });
}

function closeBusinessModal() {
    const modal = document.getElementById('business-modal');
    modal.style.display = 'none';
    modal.style.cssText = '';
    document.body.style.overflow = '';
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Close modal when clicking outside
document.addEventListener('click', function(event) {
    const modal = document.getElementById('business-modal');
    if (event.target === modal) {
        closeBusinessModal();
    }
});
</script>

<?php 
$hideAdverts = false;
include 'includes/footer.php'; 
?>

