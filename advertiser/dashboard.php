<?php
require_once __DIR__ . '/../includes/functions.php';
requireAnyRole(['ADMIN', 'ADVERTISER']);

$db = getDB();
$userId = getCurrentUserId();

// Get or create advertiser account
$stmt = $db->prepare("SELECT * FROM " . TABLE_PREFIX . "advertiser_accounts WHERE user_id = ?");
$stmt->execute([$userId]);
$account = $stmt->fetch();

if (!$account) {
    // Create account if it doesn't exist
    $stmt = $db->prepare("INSERT INTO " . TABLE_PREFIX . "advertiser_accounts (user_id, balance) VALUES (?, 0.00)");
    $stmt->execute([$userId]);
    $accountId = $db->lastInsertId();
    $stmt = $db->prepare("SELECT * FROM " . TABLE_PREFIX . "advertiser_accounts WHERE account_id = ?");
    $stmt->execute([$accountId]);
    $account = $stmt->fetch();
}

$message = '';
$error = '';

// Handle payment (simplified - in production, integrate with payment gateway)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_payment') {
    $amount = (float)($_POST['amount'] ?? 0);
    if ($amount > 0) {
        try {
            $db->beginTransaction();
            
            // Update account balance
            $stmt = $db->prepare("UPDATE " . TABLE_PREFIX . "advertiser_accounts SET balance = balance + ? WHERE account_id = ?");
            $stmt->execute([$amount, $account['account_id']]);
            
            // Record transaction
            $stmt = $db->prepare("
                INSERT INTO " . TABLE_PREFIX . "advert_transactions 
                (account_id, amount, transaction_type, description)
                VALUES (?, ?, 'payment', ?)
            ");
            $stmt->execute([$account['account_id'], $amount, "Payment of R" . number_format($amount, 2)]);
            
            $db->commit();
            $message = 'Payment added successfully.';
            
            // Reload account
            $stmt = $db->prepare("SELECT * FROM " . TABLE_PREFIX . "advertiser_accounts WHERE account_id = ?");
            $stmt->execute([$account['account_id']]);
            $account = $stmt->fetch();
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Payment failed. Please try again.';
        }
    }
}

// Get user's businesses
$stmt = $db->prepare("
    SELECT b.*, c.category_name
    FROM " . TABLE_PREFIX . "businesses b
    JOIN " . TABLE_PREFIX . "business_categories c ON b.category_id = c.category_id
    WHERE b.user_id = ?
    ORDER BY b.business_name
");
$stmt->execute([$userId]);
$businesses = $stmt->fetchAll();

// Get advertisements
$advertisements = [];
if (!empty($businesses)) {
    $businessIds = array_column($businesses, 'business_id');
    $placeholders = str_repeat('?,', count($businessIds) - 1) . '?';
    $stmt = $db->prepare("
        SELECT a.*, b.business_name
        FROM " . TABLE_PREFIX . "advertisements a
        JOIN " . TABLE_PREFIX . "businesses b ON a.business_id = b.business_id
        WHERE a.business_id IN ($placeholders)
        ORDER BY a.created_at DESC
    ");
    $stmt->execute($businessIds);
    $advertisements = $stmt->fetchAll();
}

// Get transactions
$stmt = $db->prepare("
    SELECT * FROM " . TABLE_PREFIX . "advert_transactions
    WHERE account_id = ?
    ORDER BY transaction_date DESC
    LIMIT 20
");
$stmt->execute([$account['account_id']]);
$transactions = $stmt->fetchAll();

$pageTitle = 'Advertiser Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="content-area">
        <h1>Advertiser Dashboard</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= h($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>
        
        <div class="account-summary">
            <h2>Account Balance</h2>
            <p class="balance <?= $account['balance'] >= 0 ? 'positive' : 'negative' ?>">
                R <?= number_format($account['balance'], 2) ?>
            </p>
            <?php if ($account['balance'] < 0): ?>
                <p class="alert alert-error">Your account is in arrears. Advertisements will not be displayed until balance is paid.</p>
            <?php endif; ?>
        </div>
        
        <div class="payment-form">
            <h2>Add Payment</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_payment">
                <div class="form-group">
                    <label for="amount">Amount (ZAR):</label>
                    <input type="number" step="0.01" id="amount" name="amount" required min="0.01">
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Add Payment</button>
                    <small>Note: In production, integrate with a payment gateway (PayFast, PayPal, etc.)</small>
                </div>
            </form>
        </div>
        
        <div class="transactions-list">
            <h2>Recent Transactions</h2>
            <?php if (empty($transactions)): ?>
                <p>No transactions yet.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $transaction): ?>
                            <tr>
                                <td><?= formatDate($transaction['transaction_date']) ?></td>
                                <td><?= h($transaction['transaction_type']) ?></td>
                                <td class="<?= $transaction['transaction_type'] === 'payment' ? 'positive' : 'negative' ?>">
                                    R <?= number_format($transaction['amount'], 2) ?>
                                </td>
                                <td><?= h($transaction['description']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <div class="businesses-section">
            <h2>My Businesses</h2>
            <?php if (empty($businesses)): ?>
                <p>You don't have any businesses yet. <a href="/advertiser/businesses.php">Add a business</a></p>
            <?php else: ?>
                <ul class="business-list">
                    <?php foreach ($businesses as $business): ?>
                        <li>
                            <a href="/advertiser/business-edit.php?id=<?= $business['business_id'] ?>">
                                <?= h($business['business_name']) ?>
                            </a>
                            (<?= h($business['category_name']) ?>)
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        
        <div class="advertisements-section">
            <h2>My Advertisements</h2>
            <p><a href="/advertiser/advert-create.php" class="btn btn-primary">Create New Advertisement</a></p>
            <?php if (empty($advertisements)): ?>
                <p>No advertisements yet.</p>
            <?php else: ?>
                <div class="advertisements-list">
                    <?php foreach ($advertisements as $ad): ?>
                        <div class="advert-item-admin">
                            <h3><?= h($ad['business_name']) ?></h3>
                            <p><strong>Period:</strong> <?= formatDate($ad['start_date']) ?> to <?= formatDate($ad['end_date']) ?></p>
                            <p><strong>Status:</strong> <?= $ad['is_active'] && $account['balance'] >= 0 ? 'Active' : 'Inactive' ?></p>
                            <p><a href="/advertiser/advert-edit.php?id=<?= $ad['advert_id'] ?>" class="btn btn-secondary">Edit</a></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.account-summary {
    background-color: var(--white);
    padding: 2rem;
    border-radius: 8px;
    box-shadow: var(--shadow);
    margin-bottom: 2rem;
    text-align: center;
}

.balance {
    font-size: 2rem;
    font-weight: bold;
    margin: 1rem 0;
}

.balance.positive {
    color: var(--success-color);
}

.balance.negative {
    color: var(--error-color);
}

.payment-form, .transactions-list, .businesses-section, .advertisements-section {
    background-color: var(--white);
    padding: 2rem;
    border-radius: 8px;
    box-shadow: var(--shadow);
    margin-bottom: 2rem;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
}

.data-table th,
.data-table td {
    padding: 0.75rem;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
}

.data-table th {
    background-color: var(--bg-color);
    font-weight: 600;
}

.business-list {
    list-style: none;
    padding: 0;
}

.business-list li {
    padding: 0.75rem;
    border-bottom: 1px solid var(--border-color);
}

.advertisements-list {
    display: grid;
    gap: 1rem;
    margin-top: 1rem;
}

.advert-item-admin {
    padding: 1.5rem;
    background-color: var(--bg-color);
    border-radius: 4px;
    border-left: 4px solid var(--primary-color);
}
</style>

<?php 
$hideAdverts = true;
include __DIR__ . '/../includes/footer.php'; 
?>




