<?php
/**
 * Seller Sales Statistics
 * Detailed sales statistics with today, monthly, and yearly views
 */
require_once '../../includes/config.php';

// Check if user is logged in and is a seller
if (!isLoggedIn()) {
    redirect('../../index.php');
}

if (!hasRole('seller')) {
    redirect('../customer/menu.php');
}

$conn = getDBConnection();
$sellerId = $_SESSION['user_id'];

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

// Get seller's stores - MySQLi version
$storesStmt = $conn->prepare("SELECT id, store_name FROM stores WHERE seller_id = ?");
$storesStmt->bind_param('i', $sellerId);
$storesStmt->execute();
$storesResult = $storesStmt->get_result();
$stores = [];
while ($row = $storesResult->fetch_assoc()) {
    $stores[] = $row;
}
$storesStmt->close();
$storeIds = array_column($stores, 'id');

// Get all sales statistics (today, monthly, yearly)
$allSalesStats = getAllSalesStats($sellerId, $storeIds);

// Get statistics for selected filter
$stats = [
    'total_orders' => 0,
    'total_sales' => 0,
    'paid_orders' => 0,
    'pending_orders' => 0
];

$orders = [];

if (!empty($storeIds)) {
    $placeholders = implode(',', array_fill(0, count($storeIds), '?'));
    $types = 'ss' . str_repeat('i', count($storeIds));
    $params = array_merge([$startDate, $endDate], $storeIds);
    
    // Get overall stats - MySQLi version
    $statsQuery = $conn->prepare("
        SELECT 
            COUNT(*) as total_orders,
            COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END), 0) as total_sales,
            COUNT(CASE WHEN payment_status = 'paid' THEN 1 END) as paid_orders,
            COUNT(CASE WHEN payment_status = 'pending' THEN 1 END) as pending_orders
        FROM orders
        WHERE DATE(created_at) BETWEEN ? AND ?
        AND store_id IN ($placeholders)
    ");
    $statsQuery->bind_param($types, ...$params);
    $statsQuery->execute();
    $statsResult = $statsQuery->get_result();
    $stats = $statsResult->fetch_assoc();
    $statsQuery->close();
    
    // Get detailed orders - MySQLi version
    $ordersQuery = $conn->prepare("
        SELECT o.*, s.store_name, u.full_name as customer_name
        FROM orders o
        JOIN stores s ON o.store_id = s.id
        JOIN users u ON o.customer_id = u.id
        WHERE DATE(o.created_at) BETWEEN ? AND ?
        AND o.store_id IN ($placeholders)
        ORDER BY o.created_at DESC
    ");
    $ordersQuery->bind_param($types, ...$params);
    $ordersQuery->execute();
    $ordersResult = $ordersQuery->get_result();
    while ($row = $ordersResult->fetch_assoc()) {
        $orders[] = $row;
    }
    $ordersQuery->close();
}

// Get daily breakdown for charts (if monthly or yearly) - MySQLi version
$dailySales = [];
if (!empty($storeIds) && ($filter === 'monthly' || $filter === 'yearly')) {
    $placeholders = implode(',', array_fill(0, count($storeIds), '?'));
    $types = str_repeat('i', count($storeIds)) . 'ss';
    $params = array_merge($storeIds, [$startDate, $endDate]);
    
    $dailyQuery = $conn->prepare("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as orders,
            COALESCE(SUM(total_amount), 0) as sales
        FROM orders
        WHERE store_id IN ($placeholders)
        AND payment_status = 'paid'
        AND DATE(created_at) BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    $dailyQuery->bind_param($types, ...$params);
    $dailyQuery->execute();
    $dailyResult = $dailyQuery->get_result();
    while ($row = $dailyResult->fetch_assoc()) {
        $dailySales[] = $row;
    }
    $dailyQuery->close();
}

// Get sales by store - MySQLi version
$storeSales = [];
if (!empty($storeIds)) {
    $placeholders = implode(',', array_fill(0, count($storeIds), '?'));
    $types = 'ss' . str_repeat('i', count($storeIds));
    $params = array_merge([$startDate, $endDate], $storeIds);
    
    $storeSalesQuery = $conn->prepare("
        SELECT 
            s.store_name,
            COUNT(o.id) as order_count,
            COALESCE(SUM(CASE WHEN o.payment_status = 'paid' THEN o.total_amount ELSE 0 END), 0) as total_sales
        FROM stores s
        LEFT JOIN orders o ON s.id = o.store_id 
            AND DATE(o.created_at) BETWEEN ? AND ?
        WHERE s.id IN ($placeholders)
        GROUP BY s.id
        ORDER BY total_sales DESC
    ");
    $storeSalesQuery->bind_param($types, ...$params);
    $storeSalesQuery->execute();
    $storeSalesResult = $storeSalesQuery->get_result();
    while ($row = $storeSalesResult->fetch_assoc()) {
        $storeSales[] = $row;
    }
    $storeSalesQuery->close();
}

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Statistics - <?php echo APP_NAME; ?></title>
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
                    <a href="dashboard.php" class="nav-item">
                        <i class="fas fa-home"></i>
                        Dashboard
                    </a>
                    <a href="orders.php" class="nav-item">
                        <i class="fas fa-shopping-bag"></i>
                        Orders
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
                    <a href="sales.php" class="nav-item active">
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
                <h1><i class="fas fa-chart-line"></i> Sales Statistics</h1>
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
            
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total_orders']; ?></h3>
                        <span>Total Orders</span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-peso-sign"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo formatPrice($stats['total_sales']); ?></h3>
                        <span>Total Sales</span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['paid_orders']; ?></h3>
                        <span>Paid Orders</span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon danger">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['pending_orders']; ?></h3>
                        <span>Pending Payments</span>
                    </div>
                </div>
            </div>
            
            <!-- All-Time Summary -->
            <h2 style="margin-bottom: 20px; font-size: 20px;"><i class="fas fa-coins"></i> Sales Summary</h2>
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
            
            <!-- Sales by Store -->
            <?php if (count($storeSales) > 0): ?>
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-store"></i> Sales by Store</h3>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Store</th>
                                    <th>Orders</th>
                                    <th>Total Sales</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($storeSales as $store): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($store['store_name']); ?></strong></td>
                                        <td><?php echo $store['order_count']; ?></td>
                                        <td><strong><?php echo formatPrice($store['total_sales']); ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Orders Table -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-list"></i> Order Details</h3>
                </div>
                <div class="card-body">
                    <?php if (count($orders) > 0): ?>
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
                                    <?php foreach ($orders as $order): ?>
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
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <h3>No sales data</h3>
                            <p>No orders found for the selected period.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
</body>
</html>
