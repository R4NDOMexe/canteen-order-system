<?php
/**
 * Master Control Panel - System Statistics
 * Comprehensive sales and system statistics
 */
require_once '../../includes/config.php';

// Check if user is logged in and is Master
if (!isLoggedIn() || !isMaster()) {
    redirect('../../index.php');
}

$conn = getDBConnection();

// Get filter
$filter = $_GET['filter'] ?? 'today';

// Set date range based on filter
switch ($filter) {
    case 'monthly':
        $startDate = date('Y-m-01');
        $endDate = date('Y-m-t');
        $title = 'This Month';
        break;
    case 'yearly':
        $startDate = date('Y-01-01');
        $endDate = date('Y-12-31');
        $title = 'This Year';
        break;
    case 'today':
    default:
        $startDate = date('Y-m-d');
        $endDate = date('Y-m-d');
        $title = 'Today';
        $filter = 'today';
        break;
}

// Get all sales statistics
$allSalesStats = getAllSalesStats();

// Get overall statistics - MySQLi version
$result = $conn->query("
    SELECT 
        COUNT(*) as total_orders,
        COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END), 0) as total_sales,
        COUNT(CASE WHEN payment_status = 'paid' THEN 1 END) as paid_orders,
        COUNT(CASE WHEN payment_status = 'pending' THEN 1 END) as pending_orders,
        COUNT(CASE WHEN order_status = 'pending' THEN 1 END) as pending_processing,
        COUNT(CASE WHEN order_status = 'completed' THEN 1 END) as completed_orders
    FROM orders
    WHERE DATE(created_at) BETWEEN '$startDate' AND '$endDate'
");
$overallStats = $result->fetch_assoc();

// Get daily breakdown for charts (if monthly or yearly) - MySQLi version
$dailySales = [];
if ($filter === 'monthly' || $filter === 'yearly') {
    $dailyQuery = $conn->query("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as orders,
            COALESCE(SUM(total_amount), 0) as sales
        FROM orders
        WHERE payment_status = 'paid'
        AND DATE(created_at) BETWEEN '$startDate' AND '$endDate'
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    while ($row = $dailyQuery->fetch_assoc()) {
        $dailySales[] = $row;
    }
}

// Get top selling stores - MySQLi version
$topStoresResult = $conn->query("
    SELECT 
        s.store_name,
        COUNT(o.id) as order_count,
        COALESCE(SUM(o.total_amount), 0) as total_sales
    FROM stores s
    LEFT JOIN orders o ON s.id = o.store_id 
        AND DATE(o.created_at) BETWEEN '$startDate' AND '$endDate'
        AND o.payment_status = 'paid'
    GROUP BY s.id
    ORDER BY total_sales DESC
    LIMIT 5
");
$topStores = [];
while ($row = $topStoresResult->fetch_assoc()) {
    $topStores[] = $row;
}

// Get recent orders - MySQLi version
$recentOrdersResult = $conn->query("
    SELECT o.*, s.store_name, u.full_name as customer_name
    FROM orders o
    JOIN stores s ON o.store_id = s.id
    JOIN users u ON o.customer_id = u.id
    WHERE DATE(o.created_at) BETWEEN '$startDate' AND '$endDate'
    ORDER BY o.created_at DESC
    LIMIT 20
");
$recentOrders = [];
while ($row = $recentOrdersResult->fetch_assoc()) {
    $recentOrders[] = $row;
}

// User growth statistics - MySQLi version
$userGrowthResult = $conn->query("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as new_users
    FROM users
    WHERE role != 'master'
    AND DATE(created_at) BETWEEN '$startDate' AND '$endDate'
    GROUP BY DATE(created_at)
    ORDER BY date
");
$userGrowth = [];
while ($row = $userGrowthResult->fetch_assoc()) {
    $userGrowth[] = $row;
}

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Statistics - <?php echo APP_NAME; ?></title>
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
                    <i class="fas fa-crown"></i>
                </div>
                <div>
                    <h2><?php echo APP_NAME; ?></h2>
                    <span>Master Panel</span>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">Main</div>
                    <a href="dashboard.php" class="nav-item">
                        <i class="fas fa-home"></i>
                        Dashboard
                    </a>
                    <a href="users.php" class="nav-item">
                        <i class="fas fa-users"></i>
                        User Management
                    </a>
                    <a href="pending-registrations.php" class="nav-item">
                        <i class="fas fa-user-clock"></i>
                        Pending Approvals
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">System</div>
                    <a href="system-stats.php" class="nav-item active">
                        <i class="fas fa-chart-line"></i>
                        System Statistics
                    </a>
                    <a href="all-orders.php" class="nav-item">
                        <i class="fas fa-shopping-bag"></i>
                        All Orders
                    </a>
                </div>
            </nav>
            
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar" style="background: linear-gradient(135deg, #6f42c1, #5a32a3); color: white;">
                        <i class="fas fa-crown" style="font-size: 20px;"></i>
                    </div>
                    <div class="user-details">
                        <h4><?php echo $_SESSION['full_name']; ?></h4>
                        <span>Master Administrator</span>
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
                <h1><i class="fas fa-chart-line"></i> System Statistics</h1>
            </div>
            
            <!-- Filter Buttons -->
            <div class="filter-bar">
                <a href="?filter=today" class="filter-btn <?php echo $filter === 'today' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-day"></i> Today
                </a>
                <a href="?filter=monthly" class="filter-btn <?php echo $filter === 'monthly' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-alt"></i> Monthly
                </a>
                <a href="?filter=yearly" class="filter-btn <?php echo $filter === 'yearly' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar"></i> Yearly
                </a>
            </div>
            
            <h2 style="margin-bottom: 20px; font-size: 20px;"><i class="fas fa-chart-pie"></i> <?php echo $title; ?> Overview</h2>
            
            <!-- Main Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $overallStats['total_orders']; ?></h3>
                        <span>Total Orders</span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-peso-sign"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo formatPrice($overallStats['total_sales']); ?></h3>
                        <span>Total Sales</span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $overallStats['paid_orders']; ?></h3>
                        <span>Paid Orders</span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon info">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $overallStats['pending_orders']; ?></h3>
                        <span>Pending Payments</span>
                    </div>
                </div>
            </div>
            
            <!-- All-Time Sales Summary -->
            <h2 style="margin-bottom: 20px; font-size: 20px;"><i class="fas fa-coins"></i> All-Time Sales Summary</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo formatPrice($allSalesStats['today']['sales']); ?></h3>
                        <span>Today's Sales (<?php echo $allSalesStats['today']['orders']; ?> orders)</span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo formatPrice($allSalesStats['monthly']['sales']); ?></h3>
                        <span>Monthly Sales (<?php echo $allSalesStats['monthly']['orders']; ?> orders)</span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-calendar"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo formatPrice($allSalesStats['yearly']['sales']); ?></h3>
                        <span>Yearly Sales (<?php echo $allSalesStats['yearly']['orders']; ?> orders)</span>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($dailySales)): ?>
            <!-- Sales Trend Table -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-bar"></i> Sales Trend</h3>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Sales (₱)</th>
                                    <th>Orders</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dailySales as $day): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($day['date'])); ?></td>
                                    <td><?php echo formatPrice($day['sales']); ?></td>
                                    <td><?php echo $day['orders']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($userGrowth)): ?>
            <!-- User Growth Table -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-user-plus"></i> New User Registrations</h3>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>New Users</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($userGrowth as $day): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($day['date'])); ?></td>
                                    <td><?php echo $day['new_users']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Top Selling Stores -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-trophy"></i> Top Selling Stores</h3>
                </div>
                <div class="card-body">
                    <?php if (count($topStores) > 0 && $topStores[0]['order_count'] > 0): ?>
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Rank</th>
                                        <th>Store</th>
                                        <th>Orders</th>
                                        <th>Total Sales</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topStores as $index => $store): ?>
                                        <?php if ($store['order_count'] > 0): ?>
                                        <tr>
                                            <td>
                                                <?php if ($index === 0): ?>
                                                    <i class="fas fa-crown" style="color: gold;"></i>
                                                <?php elseif ($index === 1): ?>
                                                    <i class="fas fa-medal" style="color: silver;"></i>
                                                <?php elseif ($index === 2): ?>
                                                    <i class="fas fa-medal" style="color: #cd7f32;"></i>
                                                <?php else: ?>
                                                    #<?php echo $index + 1; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td><strong><?php echo htmlspecialchars($store['store_name']); ?></strong></td>
                                            <td><?php echo $store['order_count']; ?></td>
                                            <td><strong><?php echo formatPrice($store['total_sales']); ?></strong></td>
                                        </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state" style="padding: 40px;">
                            <div class="empty-state-icon">
                                <i class="fas fa-store"></i>
                            </div>
                            <h3>No sales data</h3>
                            <p>No sales have been recorded for the selected period.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Recent Orders -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-list"></i> Recent Orders</h3>
                    <a href="all-orders.php" class="btn btn-outline" style="padding: 8px 16px; font-size: 14px;">
                        View All Orders
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
                                        <th>Payment</th>
                                        <th>Status</th>
                                        <th>Date</th>
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
                                                <span class="badge badge-<?php echo $order['payment_status']; ?>">
                                                    <?php echo ucfirst($order['payment_status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo $order['order_status']; ?>">
                                                    <?php echo ucfirst($order['order_status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, h:i A', strtotime($order['created_at'])); ?></td>
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
                            <p>No orders have been placed for the selected period.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
</body>
</html>
