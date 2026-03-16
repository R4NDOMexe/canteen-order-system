<?php
/**
 * Customer Orders Page
 * View current orders
 */
require_once '../../includes/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('../../index.php');
}

// Allow customers, students, teachers, and master
$userRole = $_SESSION['role'] ?? '';
$allowedRoles = ['customer', 'student', 'teacher'];
if (!in_array($userRole, $allowedRoles) && $userRole !== 'master') {
    redirect('../../index.php');
}

$conn = getDBConnection();

// Get customer's orders - MySQLi version
$stmt = $conn->prepare("
    SELECT o.*, s.store_name,
           (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
    FROM orders o
    JOIN stores s ON o.store_id = s.id
    WHERE o.customer_id = ?
    ORDER BY o.created_at DESC
");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$orders = [];
while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}
$stmt->close();

// Get cart count
$cartCount = 0;
if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cartCount += $item['quantity'];
    }
}

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - <?php echo APP_NAME; ?></title>
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
                    <i class="fas fa-utensils"></i>
                </div>
                <div>
                    <h2><?php echo APP_NAME; ?></h2>
                    <span><?php echo ucfirst($_SESSION['role']); ?></span>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">Menu</div>
                    <a href="menu.php" class="nav-item">
                        <i class="fas fa-th-large"></i>
                        Browse Stores
                    </a>
                    <a href="cart.php" class="nav-item">
                        <i class="fas fa-shopping-cart"></i>
                        My Cart
                        <?php if ($cartCount > 0): ?>
                            <span class="badge" style="margin-left: auto;"><?php echo $cartCount; ?></span>
                        <?php endif; ?>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Orders</div>
                    <a href="orders.php" class="nav-item active">
                        <i class="fas fa-list"></i>
                        My Orders
                    </a>
                    <a href="history.php" class="nav-item">
                        <i class="fas fa-history"></i>
                        Order History
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
                        <span><?php echo ucfirst($_SESSION['role']); ?></span>
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
                <h1><i class="fas fa-list"></i> My Orders</h1>
                <a href="menu.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> New Order
                </a>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <?php if (count($orders) > 0): ?>
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Order Code</th>
                                        <th>Store</th>
                                        <th>Items</th>
                                        <th>Total</th>
                                        <th>Payment Method</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                        <tr>
                                            <td>
                                                <strong>#<?php echo $order['order_code']; ?></strong>
                                                <?php if ($order['is_priority']): ?>
                                                    <span class="badge badge-priority" style="margin-left: 8px;">
                                                        <i class="fas fa-star"></i>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td><i class="fas fa-store"></i> <?php echo $order['store_name']; ?></td>
                                            <td><?php echo $order['item_count']; ?> items</td>
                                            <td><strong><?php echo formatPrice($order['total_amount']); ?></strong></td>
                                            <td>
                                                <?php
                                                    // show payment method text
                                                    switch ($order['payment_method']) {
                                                        case 'gcash':
                                                            echo 'GCash';
                                                            break;
                                                        case 'paymaya':
                                                            echo 'PayMaya';
                                                            break;
                                                        case 'pay_at_front':
                                                        default:
                                                            echo 'Cash on Pickup';
                                                            break;
                                                    }
                                                ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo $order['order_status']; ?>">
                                                    <?php echo ucfirst($order['order_status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y h:i A', strtotime($order['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="fas fa-list"></i>
                            </div>
                            <h3>No orders yet</h3>
                            <p>You haven't placed any orders. Start ordering now!</p>
                            <a href="menu.php" class="btn btn-primary btn-lg" style="margin-top: 16px;">
                                <i class="fas fa-utensils"></i> Browse Menu
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
