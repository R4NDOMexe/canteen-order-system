<?php
/**
 * Customer Order History Page
 * View past order history
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

// Get receipt history - MySQLi version
$stmt = $conn->prepare("
    SELECT r.*, o.order_status
    FROM receipts r
    JOIN orders o ON r.order_id = o.id
    WHERE r.customer_id = ?
    ORDER BY r.created_at DESC
");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$receipts = [];
while ($row = $result->fetch_assoc()) {
    $receipts[] = $row;
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
    <title>Order History - <?php echo APP_NAME; ?></title>
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
                    <a href="orders.php" class="nav-item">
                        <i class="fas fa-list"></i>
                        My Orders
                    </a>
                    <a href="history.php" class="nav-item active">
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
                <h1><i class="fas fa-history"></i> Order History</h1>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <?php if (count($receipts) > 0): ?>
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Receipt Code</th>
                                        <th>Store</th>
                                        <th>Total</th>
                                        <th>Payment</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($receipts as $receipt): ?>
                                        <tr>
                                            <td><strong>#<?php echo $receipt['receipt_code']; ?></strong></td>
                                            <td><i class="fas fa-store"></i> <?php echo $receipt['store_name']; ?></td>
                                            <td><strong><?php echo formatPrice($receipt['total_amount']); ?></strong></td>
                                            <td>
                                                <span class="badge badge-<?php echo $receipt['payment_status']; ?>">
                                                    <?php echo ucfirst($receipt['payment_status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo $receipt['order_status']; ?>">
                                                    <?php echo ucfirst($receipt['order_status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y h:i A', strtotime($receipt['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="fas fa-history"></i>
                            </div>
                            <h3>No order history</h3>
                            <p>Your order history will appear here after you place orders.</p>
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
