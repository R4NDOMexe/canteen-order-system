<?php
/**
 * Master Control Panel - Stores Management
 * View and manage all stores in the system
 */
require_once '../../includes/config.php';

if (!isLoggedIn() || !isMaster()) {
    redirect('../../index.php');
}

$conn = getDBConnection();

// Handle store status toggle - MySQLi version
if (isset($_GET['action']) && isset($_GET['id'])) {
    $storeId = intval($_GET['id']);
    $action = $_GET['action'];
    
    if ($action === 'activate') {
        $stmt = $conn->prepare("UPDATE stores SET is_active = 1 WHERE id = ?");
        $stmt->bind_param('i', $storeId);
        $stmt->execute();
        $stmt->close();
        setFlashMessage('Store activated successfully.', 'success');
    } elseif ($action === 'deactivate') {
        $stmt = $conn->prepare("UPDATE stores SET is_active = 0 WHERE id = ?");
        $stmt->bind_param('i', $storeId);
        $stmt->execute();
        $stmt->close();
        setFlashMessage('Store deactivated successfully.', 'success');
    } elseif ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM stores WHERE id = ?");
        $stmt->bind_param('i', $storeId);
        $stmt->execute();
        $stmt->close();
        setFlashMessage('Store deleted permanently.', 'success');
    }
    redirect('stores-management.php');
}

// Get all stores with seller info - MySQLi version
$storesResult = $conn->query("
    SELECT s.*, u.full_name as seller_name, u.email as seller_email,
           (SELECT COUNT(*) FROM menu_items WHERE store_id = s.id) as item_count,
           (SELECT COUNT(*) FROM orders WHERE store_id = s.id) as order_count
    FROM stores s
    JOIN users u ON s.seller_id = u.id
    ORDER BY s.created_at DESC
");
$stores = [];
while ($row = $storesResult->fetch_assoc()) {
    $stores[] = $row;
}

// Get statistics - MySQLi version
$result = $conn->query("
    SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN is_active = 1 THEN 1 END) as active,
        COUNT(CASE WHEN is_active = 0 THEN 1 END) as inactive
    FROM stores
");
$stats = $result->fetch_assoc();

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stores Management - <?php echo APP_NAME; ?></title>
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
                    <a href="all-orders.php" class="nav-item"><i class="fas fa-shopping-bag"></i> All Orders</a>
                    <a href="stores-management.php" class="nav-item active"><i class="fas fa-store"></i> Stores Management</a>
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
                <h1><i class="fas fa-store"></i> Stores Management</h1>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon primary"><i class="fas fa-store"></i></div>
                    <div class="stat-info"><h3><?php echo $stats['total']; ?></h3><span>Total Stores</span></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon success"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-info"><h3><?php echo $stats['active']; ?></h3><span>Active</span></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon danger"><i class="fas fa-ban"></i></div>
                    <div class="stat-info"><h3><?php echo $stats['inactive']; ?></h3><span>Inactive</span></div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header"><h3><i class="fas fa-list"></i> All Stores</h3></div>
                <div class="card-body">
                    <?php if (count($stores) > 0): ?>
                        <div class="table-container">
                            <table class="data-table">
                                <thead><tr><th>ID</th><th>Store Name</th><th>Seller</th><th>Items</th><th>Orders</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
                                <tbody>
                                    <?php foreach ($stores as $store): ?>
                                        <tr>
                                            <td>#<?php echo $store['id']; ?></td>
                                            <td><strong><?php echo htmlspecialchars($store['store_name']); ?></strong></td>
                                            <td><?php echo $store['seller_name']; ?><br><small style="color: var(--gray-dark);"><?php echo $store['seller_email']; ?></small></td>
                                            <td><?php echo $store['item_count']; ?></td>
                                            <td><?php echo $store['order_count']; ?></td>
                                            <td><?php echo $store['is_active'] ? '<span class="badge badge-approved">Active</span>' : '<span class="badge badge-danger">Inactive</span>'; ?></td>
                                            <td><?php echo date('M d, Y', strtotime($store['created_at'])); ?></td>
                                            <td>
                                                <div style="display: flex; gap: 8px;">
                                                    <?php if ($store['is_active']): ?>
                                                        <a href="?action=deactivate&id=<?php echo $store['id']; ?>" class="btn btn-warning btn-sm"><i class="fas fa-ban"></i></a>
                                                    <?php else: ?>
                                                        <a href="?action=activate&id=<?php echo $store['id']; ?>" class="btn btn-success btn-sm"><i class="fas fa-check"></i></a>
                                                    <?php endif; ?>
                                                    <a href="?action=delete&id=<?php echo $store['id']; ?>" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon"><i class="fas fa-store"></i></div>
                            <h3>No stores yet</h3>
                            <p>Stores will appear here when sellers create them.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
