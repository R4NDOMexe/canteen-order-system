<?php
/**
 * Master Control Panel - View User Details
 * View detailed information about a specific user
 */
require_once '../../includes/config.php';

// Check if user is logged in and is Master
if (!isLoggedIn() || !isMaster()) {
    redirect('../../index.php');
}

$conn = getDBConnection();

// Get user ID
$userId = intval($_GET['id'] ?? 0);
if ($userId === 0) {
    setFlashMessage('Invalid user ID.', 'error');
    redirect('users.php');
}

// Handle quick actions - MySQLi version
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    if ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE users SET status = 'approved' WHERE id = ? AND role != 'master'");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
        setFlashMessage('User has been approved successfully.', 'success');
        redirect('view-user.php?id=' . $userId);
    } elseif ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE users SET status = 'rejected' WHERE id = ? AND role != 'master'");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
        setFlashMessage('User has been rejected.', 'success');
        redirect('view-user.php?id=' . $userId);
    }
}

// Get user details - MySQLi version
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND role != 'master'");
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    setFlashMessage('User not found.', 'error');
    redirect('users.php');
}

// Get user's orders if customer - MySQLi version
$orders = [];
if ($user['role'] === 'student' || $user['role'] === 'teacher' || $user['role'] === 'customer') {
    $orderStmt = $conn->prepare("
        SELECT o.*, s.store_name 
        FROM orders o 
        JOIN stores s ON o.store_id = s.id 
        WHERE o.customer_id = ? 
        ORDER BY o.created_at DESC
    ");
    $orderStmt->bind_param('i', $userId);
    $orderStmt->execute();
    $orderResult = $orderStmt->get_result();
    while ($row = $orderResult->fetch_assoc()) {
        $orders[] = $row;
    }
    $orderStmt->close();
}

// Get user's stores if seller - MySQLi version
$stores = [];
if ($user['role'] === 'seller') {
    $storeStmt = $conn->prepare("SELECT * FROM stores WHERE seller_id = ? ORDER BY created_at DESC");
    $storeStmt->bind_param('i', $userId);
    $storeStmt->execute();
    $storeResult = $storeStmt->get_result();
    while ($row = $storeResult->fetch_assoc()) {
        $stores[] = $row;
    }
    $storeStmt->close();
}

// Get order statistics
$orderStats = ['total' => 0, 'total_spent' => 0];
if (count($orders) > 0) {
    $orderStats['total'] = count($orders);
    $orderStats['total_spent'] = array_sum(array_column($orders, 'total_amount'));
}

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Details - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .user-profile-header {
            background: linear-gradient(135deg, var(--secondary), #16213e);
            color: white;
            padding: 40px;
            border-radius: var(--radius-lg);
            margin-bottom: 32px;
            display: flex;
            align-items: center;
            gap: 32px;
        }
        .user-profile-avatar {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            font-weight: 700;
            color: var(--secondary);
        }
        .user-profile-info h1 {
            font-size: 32px;
            margin-bottom: 8px;
        }
        .user-profile-info p {
            opacity: 0.8;
            font-size: 16px;
        }
        .user-profile-badges {
            display: flex;
            gap: 12px;
            margin-top: 16px;
        }
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }
        .detail-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 24px;
            box-shadow: var(--shadow);
        }
        .detail-card h3 {
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--gray-dark);
            margin-bottom: 12px;
        }
        .detail-card p {
            font-size: 18px;
            font-weight: 600;
            color: var(--secondary);
        }
        .photo-id-container {
            max-width: 400px;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow);
        }
        .photo-id-container img {
            width: 100%;
            height: auto;
            display: block;
        }
        .action-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
    </style>
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
                    <a href="system-stats.php" class="nav-item">
                        <i class="fas fa-chart-line"></i>
                        System Statistics
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
                <h1><i class="fas fa-user"></i> User Details</h1>
                <a href="users.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Users
                </a>
            </div>
            
            <!-- User Profile Header -->
            <div class="user-profile-header">
                <div class="user-profile-avatar">
                    <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                </div>
                <div class="user-profile-info">
                    <h1><?php echo htmlspecialchars($user['full_name']); ?></h1>
                    <p>@<?php echo htmlspecialchars($user['username']); ?> • <?php echo htmlspecialchars($user['email']); ?></p>
                    <div class="user-profile-badges">
                        <span class="badge badge-<?php echo $user['role']; ?>" style="font-size: 14px; padding: 8px 16px;">
                            <?php echo ucfirst($user['role']); ?>
                        </span>
                        <?php if ($user['status'] === 'approved' || $user['status'] === 'active'): ?>
                            <span class="badge badge-approved" style="font-size: 14px; padding: 8px 16px;">
                                <i class="fas fa-check"></i> Approved
                            </span>
                        <?php elseif ($user['status'] === 'pending'): ?>
                            <span class="badge badge-pending" style="font-size: 14px; padding: 8px 16px;">
                                <i class="fas fa-clock"></i> Pending Approval
                            </span>
                        <?php elseif ($user['status'] === 'rejected'): ?>
                            <span class="badge badge-rejected" style="font-size: 14px; padding: 8px 16px;">
                                <i class="fas fa-times"></i> Rejected
                            </span>
                        <?php else: ?>
                            <span class="badge badge-danger" style="font-size: 14px; padding: 8px 16px;">
                                <i class="fas fa-ban"></i> Inactive
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- User Details Grid -->
            <div class="detail-grid">
                <div class="detail-card">
                    <h3><i class="fas fa-id-card"></i> User ID</h3>
                    <p>#<?php echo $user['id']; ?></p>
                </div>
                <div class="detail-card">
                    <h3><i class="fas fa-calendar"></i> Registered On</h3>
                    <p><?php echo date('F d, Y h:i A', strtotime($user['created_at'])); ?></p>
                </div>
                <div class="detail-card">
                    <h3><i class="fas fa-clock"></i> Last Updated</h3>
                    <p><?php echo date('F d, Y h:i A', strtotime($user['updated_at'])); ?></p>
                </div>
                <?php if (count($orders) > 0): ?>
                <div class="detail-card">
                    <h3><i class="fas fa-shopping-bag"></i> Total Orders</h3>
                    <p><?php echo $orderStats['total']; ?> orders</p>
                </div>
                <div class="detail-card">
                    <h3><i class="fas fa-coins"></i> Total Spent</h3>
                    <p><?php echo formatPrice($orderStats['total_spent']); ?></p>
                </div>
                <?php endif; ?>
                <?php if (count($stores) > 0): ?>
                <div class="detail-card">
                    <h3><i class="fas fa-store"></i> Stores Owned</h3>
                    <p><?php echo count($stores); ?> store(s)</p>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Photo ID Section -->
            <?php if ($user['photo_id_path'] && file_exists('../../' . $user['photo_id_path'])): ?>
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-id-card"></i> Photo ID</h3>
                </div>
                <div class="card-body">
                    <div class="photo-id-container">
                        <img src="../../<?php echo $user['photo_id_path']; ?>" alt="Photo ID">
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Action Buttons -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-cog"></i> Actions</h3>
                </div>
                <div class="card-body">
                    <div class="action-buttons">
                        <?php if ($user['status'] === 'pending'): ?>
                            <a href="?id=<?php echo $user['id']; ?>&action=approve" 
                               class="btn btn-success btn-lg">
                                <i class="fas fa-check"></i> Approve User
                            </a>
                            <a href="?id=<?php echo $user['id']; ?>&action=reject" 
                               class="btn btn-danger btn-lg">
                                <i class="fas fa-times"></i> Reject User
                            </a>
                        <?php elseif ($user['status'] === 'approved' || $user['status'] === 'active'): ?>
                            <a href="users.php?action=deactivate&id=<?php echo $user['id']; ?>" 
                               class="btn btn-warning btn-lg">
                                <i class="fas fa-ban"></i> Deactivate User
                            </a>
                        <?php else: ?>
                            <a href="users.php?action=activate&id=<?php echo $user['id']; ?>" 
                               class="btn btn-success btn-lg">
                                <i class="fas fa-check"></i> Activate User
                            </a>
                        <?php endif; ?>
                        
                        <a href="users.php?action=delete&id=<?php echo $user['id']; ?>" 
                           class="btn btn-danger btn-lg">
                            <i class="fas fa-trash"></i> Delete User
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- User's Orders (if customer) -->
            <?php if (count($orders) > 0): ?>
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-shopping-bag"></i> Order History</h3>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Order Code</th>
                                    <th>Store</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Payment</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($orders, 0, 10) as $order): ?>
                                    <tr>
                                        <td><strong>#<?php echo $order['order_code']; ?></strong></td>
                                        <td><i class="fas fa-store"></i> <?php echo $order['store_name']; ?></td>
                                        <td><strong><?php echo formatPrice($order['total_amount']); ?></strong></td>
                                        <td>
                                            <span class="badge badge-<?php echo $order['order_status']; ?>">
                                                <?php echo ucfirst($order['order_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php echo $order['payment_status']; ?>">
                                                <?php echo ucfirst($order['payment_status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y h:i A', strtotime($order['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (count($orders) > 10): ?>
                        <p style="text-align: center; margin-top: 16px; color: var(--gray-dark);">
                            Showing 10 of <?php echo count($orders); ?> orders
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- User's Stores (if seller) -->
            <?php if (count($stores) > 0): ?>
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-store"></i> Stores</h3>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Store Name</th>
                                    <th>Description</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stores as $store): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($store['store_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($store['description'] ?: 'No description'); ?></td>
                                        <td>
                                            <?php if ($store['is_active']): ?>
                                                <span class="badge badge-approved">Active</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($store['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
