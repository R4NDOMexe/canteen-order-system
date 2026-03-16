<?php
/**
 * Master Control Panel - All Orders
 * View all orders in the system
 */
require_once '../../includes/config.php';

if (!isLoggedIn() || !isMaster()) {
    redirect('../../index.php');
}

$conn = getDBConnection();

// expire any pending pay_at_front orders system-wide
$expireStmt = $conn->prepare("UPDATE orders SET payment_status='void', order_status='cancelled' WHERE payment_status='pending' AND payment_method='pay_at_front' AND void_after IS NOT NULL AND void_after < NOW()");
$expireStmt->execute();
$expireStmt->close();

// Get all orders - MySQLi version
$ordersResult = $conn->query("
    SELECT o.*, s.store_name, u.full_name as customer_name, seller.full_name as seller_name
    FROM orders o
    JOIN stores s ON o.store_id = s.id
    JOIN users u ON o.customer_id = u.id
    JOIN users seller ON s.seller_id = seller.id
    ORDER BY o.created_at DESC
    LIMIT 100
");
$orders = [];
while ($row = $ordersResult->fetch_assoc()) {
    $orders[] = $row;
}

// Get statistics - MySQLi version
$result = $conn->query("
    SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN order_status = 'pending' THEN 1 END) as pending,
        COUNT(CASE WHEN order_status = 'completed' THEN 1 END) as completed,
        COUNT(CASE WHEN payment_status = 'paid' THEN 1 END) as paid,
        COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END), 0) as total_sales
    FROM orders
");
$stats = $result->fetch_assoc();

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Orders - <?php echo APP_NAME; ?></title>
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
                <div class="sidebar-logo"><i class="fas fa-crown"></i></div>
                <div><h2><?php echo APP_NAME; ?></h2><span>Master Panel</span></div>
            </div>
            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">Main</div>
                    <a href="dashboard.php" class="nav-item"><i class="fas fa-home"></i> Dashboard</a>
                    <a href="users.php" class="nav-item"><i class="fas fa-users"></i> User Management</a>
                    <a href="pending-registrations.php" class="nav-item"><i class="fas fa-user-clock"></i> Pending Approvals</a>
                </div>
                <div class="nav-section">
                    <div class="nav-section-title">System</div>
                    <a href="system-stats.php" class="nav-item"><i class="fas fa-chart-line"></i> System Statistics</a>
                    <a href="all-orders.php" class="nav-item active"><i class="fas fa-shopping-bag"></i> All Orders</a>
                </div>
            </nav>
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar" style="background: linear-gradient(135deg, #6f42c1, #5a32a3); color: white;"><i class="fas fa-crown"></i></div>
                    <div class="user-details"><h4><?php echo $_SESSION['full_name']; ?></h4><span>Master Administrator</span></div>
                </div>
                <a href="../../logout.php" class="btn btn-outline w-full"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </aside>
        
        <main class="main-content">
            <?php if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?>"><i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i> <?php echo $flash['message']; ?></div>
            <?php endif; ?>
            
            <div class="page-header">
                <h1><i class="fas fa-shopping-bag"></i> All Orders</h1>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon primary"><i class="fas fa-shopping-bag"></i></div>
                    <div class="stat-info"><h3><?php echo $stats['total']; ?></h3><span>Total Orders</span></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon warning"><i class="fas fa-clock"></i></div>
                    <div class="stat-info"><h3><?php echo $stats['pending']; ?></h3><span>Pending</span></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon success"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-info"><h3><?php echo $stats['completed']; ?></h3><span>Completed</span></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon info"><i class="fas fa-coins"></i></div>
                    <div class="stat-info"><h3><?php echo formatPrice($stats['total_sales']); ?></h3><span>Total Sales</span></div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header"><h3><i class="fas fa-list"></i> Order List</h3><span style="color: var(--gray-dark); font-size: 14px;">Showing last 100 orders</span></div>
                <div class="card-body">
                    <?php if (count($orders) > 0): ?>
                        <div class="table-container">
                            <table class="data-table">
                                <thead><tr><th>Order Code</th><th>Customer</th><th>Store</th><th>Seller</th><th>Total</th><th>Payment</th><th>Status</th><th>Priority</th><th>Date</th></tr></thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                        <tr>
                                            <td><strong>#<?php echo $order['order_code']; ?></strong></td>
                                            <td><i class="fas fa-user"></i> <?php echo $order['customer_name']; ?></td>
                                            <td><i class="fas fa-store"></i> <?php echo $order['store_name']; ?></td>
                                            <td><?php echo $order['seller_name']; ?></td>
                                            <td><strong><?php echo formatPrice($order['total_amount']); ?></strong></td>
                                            <td><span class="badge badge-<?php echo $order['payment_status']; ?>"><?php echo ucfirst($order['payment_status']); ?></span></td>
                                            <td><span class="badge badge-<?php echo $order['order_status']; ?>"><?php echo ucfirst($order['order_status']); ?></span></td>
                                            <td><?php echo $order['is_priority'] ? '<span class="badge badge-priority"><i class="fas fa-star"></i></span>' : '-'; ?></td>
                                            <td><?php echo date('M d, h:i A', strtotime($order['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon"><i class="fas fa-shopping-bag"></i></div>
                            <h3>No orders yet</h3>
                            <p>Orders will appear here when customers place them.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
