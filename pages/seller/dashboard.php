<?php
/**
 * Seller Dashboard
 * Shows seller's stores, orders, and sales statistics
 */
require_once '../../includes/config.php';

// Check if user is logged in and is a seller (or master)
if (!isLoggedIn()) {
    redirect('../../index.php');
}

if (!hasRole('seller')) {
    redirect('../customer/menu.php');
}

$conn = getDBConnection();
$sellerId = $_SESSION['user_id'];

// Get seller's stores - MySQLi version
$storesStmt = $conn->prepare("SELECT id FROM stores WHERE seller_id = ?");
$storesStmt->bind_param('i', $sellerId);
$storesStmt->execute();
$storesResult = $storesStmt->get_result();
$storeIds = [];
while ($row = $storesResult->fetch_assoc()) {
    $storeIds[] = $row['id'];
}
$storesStmt->close();

// Get all sales statistics using the helper function
$salesStats = getAllSalesStats($sellerId, $storeIds);

// Get pending orders count - MySQLi version
$pendingCount = 0;
if (!empty($storeIds)) {
    $placeholders = implode(',', array_fill(0, count($storeIds), '?'));
    $types = str_repeat('i', count($storeIds));
    $pendingStmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM orders
        WHERE store_id IN ($placeholders) AND order_status IN ('pending', 'preparing')
    ");
    $pendingStmt->bind_param($types, ...$storeIds);
    $pendingStmt->execute();
    $result = $pendingStmt->get_result();
    $row = $result->fetch_assoc();
    $pendingCount = $row['count'];
    $pendingStmt->close();
}

// Get recent orders - MySQLi version
$recentOrders = [];
if (!empty($storeIds)) {
    $placeholders = implode(',', array_fill(0, count($storeIds), '?'));
    $types = str_repeat('i', count($storeIds));
    $ordersStmt = $conn->prepare("
        SELECT o.*, s.store_name, u.full_name as customer_name
        FROM orders o
        JOIN stores s ON o.store_id = s.id
        JOIN users u ON o.customer_id = u.id
        WHERE o.store_id IN ($placeholders)
        ORDER BY o.is_priority DESC, o.created_at DESC
        LIMIT 10
    ");
    $ordersStmt->bind_param($types, ...$storeIds);
    $ordersStmt->execute();
    $ordersResult = $ordersStmt->get_result();
    while ($row = $ordersResult->fetch_assoc()) {
        $recentOrders[] = $row;
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
    <title>Seller Dashboard - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <input type="checkbox" id="sidebar-toggle" class="hidden">
    <label for="sidebar-toggle" class="mobile-menu-toggle" aria-label="Toggle menu">
        <i class="fas fa-bars"></i>
    </label>
    <div class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <i class="fas fa-store"></i>
                </div>
                <div>
                    <h2><?php echo APP_NAME; ?></h2>
                    <span>Seller Panel</span>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">Main</div>
                    <a href="dashboard.php" class="nav-item active">
                        <i class="fas fa-home"></i>
                        Dashboard
                    </a>
                    <a href="orders.php" class="nav-item">
                        <i class="fas fa-shopping-bag"></i>
                        Orders
                        <?php if ($pendingCount > 0): ?>
                            <span class="badge badge-danger" style="margin-left: auto;"><?php echo $pendingCount; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="previous-orders.php" class="nav-item">
                        <i class="fas fa-history"></i>
                        Previous Orders
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Management</div>
                    <a href="stores.php" class="nav-item">
                        <i class="fas fa-store-alt"></i>
                        My Stores
                    </a>
                    <a href="sales.php" class="nav-item">
                        <i class="fas fa-chart-line"></i>
                        Sales Statistics
                    </a>
                    <a href="history.php" class="nav-item">
                        <i class="fas fa-receipt"></i>
                        Receipt History
                    </a>
                </div>
            </nav>
            
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                    </div>
                    <div class="user-details">
                        <h4><?php echo $_SESSION['full_name']; ?></h4>
                        <span>Seller</span>
                    </div>
                </div>
                <a href="../../logout.php" class="btn btn-outline w-full">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <?php if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?>">
                    <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo $flash['message']; ?>
                </div>
            <?php endif; ?>
            
            <div class="page-header">
                <h1><i class="fas fa-home"></i> Seller Dashboard</h1>
                <a href="stores.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Manage Stores
                </a>
            </div>
            
            <!-- Sales Statistics Cards -->
            <h2 style="margin-bottom: 20px; font-size: 18px; color: var(--gray-dark);">
                <i class="fas fa-chart-bar"></i> Sales Overview
            </h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo formatPrice($salesStats['today']['sales']); ?></h3>
                        <span>Today's Sales (<?php echo $salesStats['today']['orders']; ?> orders)</span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo formatPrice($salesStats['monthly']['sales']); ?></h3>
                        <span>Monthly Sales (<?php echo $salesStats['monthly']['orders']; ?> orders)</span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-calendar"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo formatPrice($salesStats['yearly']['sales']); ?></h3>
                        <span>Yearly Sales (<?php echo $salesStats['yearly']['orders']; ?> orders)</span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon danger">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $pendingCount; ?></h3>
                        <span>Pending Orders</span>
                    </div>
                </div>
            </div>
            
            <!-- Recent Orders -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-list"></i> Recent Orders</h3>
                    <a href="orders.php" class="btn btn-outline" style="padding: 8px 16px; font-size: 14px;">
                        View All
                    </a>
                </div>
                <div class="card-body">
                    <?php if (count($recentOrders) > 0): ?>
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Order Code</th>
                                        <th>Customer</th>
                                        <th>Store</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th>Priority</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentOrders as $order): ?>
                                        <tr>
                                            <td><strong>#<?php echo $order['order_code']; ?></strong></td>
                                            <td><i class="fas fa-user"></i> <?php echo $order['customer_name']; ?></td>
                                            <td><i class="fas fa-store"></i> <?php echo $order['store_name']; ?></td>
                                            <td><strong><?php echo formatPrice($order['total_amount']); ?></strong></td>
                                            <td>
                                                <span class="badge badge-<?php echo $order['order_status']; ?>">
                                                    <?php echo ucfirst($order['order_status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($order['is_priority']): ?>
                                                    <span class="badge badge-priority">
                                                        <i class="fas fa-star"></i> TEACHER
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color: var(--gray-dark);">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('M d, h:i A', strtotime($order['created_at'])); ?></td>
                                            <td>
                                                <a href="view-order.php?id=<?php echo $order['id']; ?>" class="btn btn-secondary" style="padding: 8px 16px; font-size: 14px;">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state" style="padding: 40px;">
                            <div class="empty-state-icon">
                                <i class="fas fa-shopping-bag"></i>
                            </div>
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
