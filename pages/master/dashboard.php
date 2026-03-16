<?php
/**
 * Master Control Panel - Dashboard
 * Master has access to all features including user management and system statistics
 */
require_once '../../includes/config.php';

// DEBUG: Check session
if (!isLoggedIn()) {
    setFlashMessage('Please login first.', 'error');
    redirect('../../index.php');
}

if (!isMaster()) {
    setFlashMessage('Access denied. Master only.', 'error');
    redirect('../../index.php');
}

$conn = getDBConnection();

// Get system statistics
$stats = [
    'total_users' => 0,
    'pending_users' => 0,
    'active_users' => 0,
    'total_sellers' => 0,
    'total_customers' => 0,
    'total_orders' => 0,
    'total_sales' => 0
];

// User statistics - MySQLi version
$userQuery = "SELECT 
    COUNT(*) as total,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
    COUNT(CASE WHEN status IN ('active', 'approved') THEN 1 END) as active,
    COUNT(CASE WHEN role = 'seller' THEN 1 END) as sellers,
    COUNT(CASE WHEN role IN ('student', 'teacher', 'customer') THEN 1 END) as customers
FROM users WHERE role != 'master'";

$userResult = $conn->query($userQuery);
$userStats = $userResult->fetch_assoc();  // MySQLi - no argument!

$stats['total_users'] = $userStats['total'];
$stats['pending_users'] = $userStats['pending'];
$stats['active_users'] = $userStats['active'];
$stats['total_sellers'] = $userStats['sellers'];
$stats['total_customers'] = $userStats['customers'];

// Order statistics - MySQLi version
$orderQuery = "SELECT 
    COUNT(*) as total_orders,
    COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END), 0) as total_sales
FROM orders";

$orderResult = $conn->query($orderQuery);
$orderStats = $orderResult->fetch_assoc();  // MySQLi - no argument!

$stats['total_orders'] = $orderStats['total_orders'];
$stats['total_sales'] = $orderStats['total_sales'];

// Get all sales statistics using the helper function
$allSalesStats = getAllSalesStats();

// Get pending registrations - MySQLi version with prepared statements
$pendingStmt = $conn->prepare("SELECT * FROM users WHERE status = 'pending' AND role != 'master' ORDER BY created_at DESC");
$pendingStmt->execute();
$pendingResult = $pendingStmt->get_result();
$pendingUsers = [];
while ($row = $pendingResult->fetch_assoc()) {
    $pendingUsers[] = $row;
}
$pendingStmt->close();

// Get recent users - MySQLi version
$recentStmt = $conn->prepare("SELECT * FROM users WHERE role != 'master' ORDER BY created_at DESC LIMIT 10");
$recentStmt->execute();
$recentResult = $recentStmt->get_result();
$recentUsers = [];
while ($row = $recentResult->fetch_assoc()) {
    $recentUsers[] = $row;
}
$recentStmt->close();

$conn->close();

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Control Panel - <?php echo APP_NAME; ?></title>
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
                    <a href="dashboard.php" class="nav-item active">
                        <i class="fas fa-home"></i>
                        Dashboard
                    </a>
                    <a href="users.php" class="nav-item">
                        <i class="fas fa-users"></i>
                        User Management
                        <?php if ($stats['pending_users'] > 0): ?>
                            <span class="badge badge-danger" style="margin-left: auto;"><?php echo $stats['pending_users']; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="pending-registrations.php" class="nav-item">
                        <i class="fas fa-user-clock"></i>
                        Pending Approvals
                        <?php if ($stats['pending_users'] > 0): ?>
                            <span class="badge badge-warning" style="margin-left: auto;"><?php echo $stats['pending_users']; ?></span>
                        <?php endif; ?>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">System</div>
                    <a href="system-stats.php" class="nav-item">
                        <i class="fas fa-chart-line"></i>
                        System Statistics
                    </a>
                    <a href="all-orders.php" class="nav-item">
                        <i class="fas fa-shopping-bag"></i>
                        All Orders
                    </a>
                    <a href="stores-management.php" class="nav-item">
                        <i class="fas fa-store"></i>
                        Stores Management
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
                <div class="alert alert-<?php echo $flash['type']; ?> fade-in">
                    <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo $flash['message']; ?>
                </div>
            <?php endif; ?>
            
            <div class="master-panel-header">
                <h1><i class="fas fa-crown"></i> Master Control Panel</h1>
                <p>Welcome back, Master Administrator. You have full system access.</p>
            </div>
            
            <!-- System Statistics -->
            <h2 style="margin-bottom: 20px; font-size: 20px;"><i class="fas fa-chart-pie"></i> System Overview</h2>
            <div class="master-stats">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total_users']; ?></h3>
                        <span>Total Users</span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-user-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['pending_users']; ?></h3>
                        <span>Pending Approvals</span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-store"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total_sellers']; ?></h3>
                        <span>Sellers</span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon info">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total_customers']; ?></h3>
                        <span>Customers</span>
                    </div>
                </div>
            </div>
            
            <!-- Sales Statistics -->
            <h2 style="margin-bottom: 20px; font-size: 20px;"><i class="fas fa-coins"></i> Sales Overview</h2>
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
                
                <div class="stat-card">
                    <div class="stat-icon info">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo formatPrice($stats['total_sales']); ?></h3>
                        <span>All-Time Sales (<?php echo $stats['total_orders']; ?> orders)</span>
                    </div>
                </div>
            </div>
            
            <!-- Pending Approvals Section -->
            <?php if (count($pendingUsers) > 0): ?>
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-user-clock"></i> Pending Registrations</h3>
                    <a href="pending-registrations.php" class="btn btn-outline" style="padding: 8px 16px; font-size: 14px;">
                        View All
                    </a>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Type</th>
                                    <th>Email</th>
                                    <th>Registered</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($pendingUsers, 0, 5) as $user): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
                                            <br>
                                            <small style="color: var(--gray-dark);">@<?php echo htmlspecialchars($user['username']); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php echo $user['role']; ?>">
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                        <td>
                                            <div style="display: flex; gap: 8px;">
                                                <a href="view-user.php?id=<?php echo $user['id']; ?>&action=approve" 
                                                   class="btn btn-success btn-sm">
                                                    <i class="fas fa-check"></i> Approve
                                                </a>
                                                <a href="view-user.php?id=<?php echo $user['id']; ?>&action=reject" 
                                                   class="btn btn-danger btn-sm">
                                                    <i class="fas fa-times"></i> Reject
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Recent Users -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-users"></i> Recent Users</h3>
                    <a href="users.php" class="btn btn-outline" style="padding: 8px 16px; font-size: 14px;">
                        View All
                    </a>
                </div>
                <div class="card-body">
                    <?php if (count($recentUsers) > 0): ?>
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Registered</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentUsers as $user): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
                                                <br>
                                                <small style="color: var(--gray-dark);">@<?php echo htmlspecialchars($user['username']); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo $user['role']; ?>">
                                                    <?php echo ucfirst($user['role']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($user['status'] === 'active' || $user['status'] === 'approved'): ?>
                                                    <span class="badge badge-approved">Approved</span>
                                                <?php elseif ($user['status'] === 'pending'): ?>
                                                    <span class="badge badge-pending">Pending</span>
                                                <?php else: ?>
                                                    <span class="badge badge-rejected">Rejected</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                            <td>
                                                <a href="view-user.php?id=<?php echo $user['id']; ?>" class="btn btn-secondary btn-sm">
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
                                <i class="fas fa-users"></i>
                            </div>
                            <h3>No users yet</h3>
                            <p>Users will appear here once they register.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>