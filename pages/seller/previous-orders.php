<?php
/**
 * Seller Previous Orders Page
 * View completed orders
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

$orders = [];
if (!empty($storeIds)) {
    $placeholders = implode(',', array_fill(0, count($storeIds), '?'));
    $types = str_repeat('i', count($storeIds));
    $ordersStmt = $conn->prepare("SELECT o.*, s.store_name, u.full_name as customer_name, r.receipt_code
        FROM orders o
        JOIN stores s ON o.store_id = s.id
        JOIN users u ON o.customer_id = u.id
        LEFT JOIN receipts r ON r.order_id = o.id
        WHERE o.store_id IN ($placeholders) AND o.order_status = 'completed'
        ORDER BY o.created_at DESC");
    $ordersStmt->bind_param($types, ...$storeIds);
    $ordersStmt->execute();
    $ordersResult = $ordersStmt->get_result();
    while ($row = $ordersResult->fetch_assoc()) {
        $orders[] = $row;
    }
    $ordersStmt->close();
}

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Previous Orders - <?php echo APP_NAME; ?></title>
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
                    <a href="previous-orders.php" class="nav-item active"><i class="fas fa-history"></i> Previous Orders</a>
                </div>
                <div class="nav-section">
                    <div class="nav-section-title">Management</div>
                    <a href="stores.php" class="nav-item"><i class="fas fa-store-alt"></i> My Stores</a>
                    <a href="sales.php" class="nav-item"><i class="fas fa-chart-line"></i> Sales Statistics</a>
                    <a href="history.php" class="nav-item"><i class="fas fa-receipt"></i> Receipt History</a>
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
                <h1><i class="fas fa-history"></i> Previous Orders</h1>
            </div>
            
            <div class="card">
                <div class="card-header"><h3><i class="fas fa-list"></i> Completed Orders</h3></div>
                <div class="card-body">
                    <?php if (count($orders) > 0): ?>
                        <div class="table-container">
                            <table class="data-table">
                                <thead><tr><th>Order Code</th><th>Customer</th><th>Store</th><th>Total</th><th>Method</th><th>Receipt</th><th>Date</th></tr></thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                        <tr>
                                            <td><strong>#<?php echo $order['order_code']; ?></strong></td>
                                            <td><i class="fas fa-user"></i> <?php echo $order['customer_name']; ?></td>
                                            <td><i class="fas fa-store"></i> <?php echo $order['store_name']; ?></td>
                                            <td><strong><?php echo formatPrice($order['total_amount']); ?></strong></td>
                                            <td>
                                                <?php
                                                    echo $order['payment_method'] === 'gcash' ? '<i class="fas fa-mobile-alt"></i> GCash' :
                                                         ($order['payment_method'] === 'paymaya' ? '<i class="fas fa-credit-card"></i> PayMaya' :
                                                          '<i class="fas fa-cash-register"></i> Pay at Front');
                                                ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($order['id'])): ?>
                                                    <a href="view-order.php?id=<?php echo $order['id']; ?>" class="btn btn-outline">View</a>
                                                <?php else: ?>
                                                    <span style="color:var(--gray-dark);">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('M d, Y h:i A', strtotime($order['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon"><i class="fas fa-history"></i></div>
                            <h3>No completed orders</h3>
                            <p>Completed orders will appear here.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
