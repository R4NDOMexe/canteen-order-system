<?php
/**
 * Seller Receipt History Page
 */
require_once '../../includes/config.php';

if (!isLoggedIn() || !hasRole('seller')) {
    redirect('../../index.php');
}

$conn = getDBConnection();
$sellerId = $_SESSION['user_id'];

$storesStmt = $conn->prepare("SELECT id FROM stores WHERE seller_id = ?");
$storesStmt->bind_param('i', $sellerId);
$storesStmt->execute();
$storesResult = $storesStmt->get_result();
$storeIds = [];
while ($row = $storesResult->fetch_assoc()) {
    $storeIds[] = $row['id'];
}
$storesStmt->close();

$receipts = [];
if (!empty($storeIds)) {
    $placeholders = implode(',', array_fill(0, count($storeIds), '?'));
    $types = str_repeat('i', count($storeIds));
    $receiptsStmt = $conn->prepare("
        SELECT r.*, o.order_status
        FROM receipts r
        JOIN orders o ON r.order_id = o.id
        WHERE o.store_id IN ($placeholders)
        ORDER BY r.created_at DESC
    ");
    $receiptsStmt->bind_param($types, ...$storeIds);
    $receiptsStmt->execute();
    $receiptsResult = $receiptsStmt->get_result();
    while ($row = $receiptsResult->fetch_assoc()) {
        $receipts[] = $row;
    }
    $receiptsStmt->close();
}

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt History - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <input type="checkbox" id="sidebar-toggle" class="hidden">
    <label for="sidebar-toggle" class="mobile-menu-toggle" aria-label="Toggle menu">
        <i class="fas fa-bars"></i>
    </label>
    <div class="dashboard">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo"><i class="fas fa-store"></i></div>
                <div><h2><?php echo APP_NAME; ?></h2><span>Seller Panel</span></div>
            </div>
            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">Main</div>
                    <a href="dashboard.php" class="nav-item"><i class="fas fa-home"></i> Dashboard</a>
                    <a href="orders.php" class="nav-item"><i class="fas fa-shopping-bag"></i> Orders</a>
                    <a href="previous-orders.php" class="nav-item"><i class="fas fa-history"></i> Previous Orders</a>
                </div>
                <div class="nav-section">
                    <div class="nav-section-title">Management</div>
                    <a href="stores.php" class="nav-item"><i class="fas fa-store-alt"></i> My Stores</a>
                    <a href="sales.php" class="nav-item"><i class="fas fa-chart-line"></i> Sales Statistics</a>
                    <a href="history.php" class="nav-item active"><i class="fas fa-receipt"></i> Receipt History</a>
                </div>
            </nav>
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?></div>
                    <div class="user-details"><h4><?php echo $_SESSION['full_name']; ?></h4><span>Seller</span></div>
                </div>
                <a href="../../logout.php" class="btn btn-outline w-full"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </aside>
        
        <main class="main-content">
            <?php if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?>"><i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i> <?php echo $flash['message']; ?></div>
            <?php endif; ?>
            
            <div class="page-header">
                <h1><i class="fas fa-receipt"></i> Receipt History</h1>
            </div>
            
            <div class="card">
                <div class="card-header"><h3><i class="fas fa-list"></i> All Receipts</h3></div>
                <div class="card-body">
                    <?php if (count($receipts) > 0): ?>
                        <div class="table-container">
                            <table class="data-table">
                                <thead><tr><th>Receipt Code</th><th>Customer</th><th>Total</th><th>Payment</th><th>Status</th><th>Date</th></tr></thead>
                                <tbody>
                                    <?php foreach ($receipts as $receipt): ?>
                                        <tr>
                                            <td><strong>#<?php echo $receipt['receipt_code']; ?></strong></td>
                                            <td><?php echo $receipt['customer_id']; ?></td>
                                            <td><strong><?php echo formatPrice($receipt['total_amount']); ?></strong></td>
                                            <td><span class="badge badge-<?php echo $receipt['payment_status']; ?>"><?php echo ucfirst($receipt['payment_status']); ?></span></td>
                                            <td><span class="badge badge-<?php echo $receipt['order_status']; ?>"><?php echo ucfirst($receipt['order_status']); ?></span></td>
                                            <td><?php echo date('M d, Y h:i A', strtotime($receipt['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon"><i class="fas fa-receipt"></i></div>
                            <h3>No receipts yet</h3>
                            <p>Receipts will appear here when orders are completed.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
