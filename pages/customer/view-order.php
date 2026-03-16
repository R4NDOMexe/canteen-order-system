<?php
require_once '../../includes/config.php';

// Check if user is logged in and is a customer/teacher
if (!isLoggedIn()) {
    redirect('index.php');
}

if (!hasRole('customer') && !hasRole('teacher')) {
    redirect('pages/seller/dashboard.php');
}

$conn = getDBConnection();

$orderId = intval($_GET['id'] ?? 0);

// Get order details
$stmt = $conn->prepare("
    SELECT o.*, s.store_name
    FROM orders o
    JOIN stores s ON o.store_id = s.id
    WHERE o.id = ? AND o.customer_id = ?
");
$stmt->bind_param("ii", $orderId, $_SESSION['user_id']);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    setFlashMessage('error', 'Order not found!');
    redirect('orders.php');
}

// Get order items
$stmt = $conn->prepare("
    SELECT oi.*, mi.name as item_name
    FROM order_items oi
    JOIN menu_items mi ON oi.menu_item_id = mi.id
    WHERE oi.order_id = ?
");
$stmt->bind_param("i", $orderId);
$stmt->execute();
$items = $stmt->get_result();

// Get receipt
$stmt = $conn->prepare("SELECT receipt_code FROM receipts WHERE order_id = ?");
$stmt->bind_param("i", $orderId);
$stmt->execute();
$receipt = $stmt->get_result()->fetch_assoc();

// Get cart count
$cartCount = 0;
if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cartCount += $item['quantity'];
    }
}

// Add order items - MySQLi version
$itemStmt = $conn->prepare("
    INSERT INTO order_items (order_id, menu_item_id, quantity, unit_price, subtotal)
    VALUES (?, ?, ?, ?, ?)
");

foreach ($items as $item) {
    $subtotal = $item['price'] * $item['quantity'];
    $itemStmt->bind_param('iisid', $orderId, $item['id'], $item['quantity'], $item['price'], $subtotal);
    $itemStmt->execute();
}
$itemStmt->close();

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - <?php echo APP_NAME; ?></title>
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
                    <span><?php echo $_SESSION['role']; ?></span>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">Menu</div>
                    <a href="menu.php" class="nav-item">
                        <i class="fas fa-th-large"></i>
                        Browse Menu
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
                        <span><?php echo $_SESSION['role']; ?></span>
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
                <h1><i class="fas fa-receipt"></i> Order Details</h1>
                <a href="orders.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i>
                    Back to Orders
                </a>
            </div>
            
            <div class="receipt-container">
                <div class="receipt-header">
                    <h2>Order #<?php echo $order['order_code']; ?></h2>
                    <?php if ($order['is_priority']): ?>
                        <span class="badge badge-priority" style="margin-top: 8px;">
                            <i class="fas fa-star"></i> PRIORITY ORDER
                        </span>
                    <?php endif; ?>
                </div>
                
                <div class="receipt-body">
                    <div class="receipt-store">
                        <h3><i class="fas fa-store"></i> <?php echo $order['store_name']; ?></h3>
                        <p style="color: var(--gray-dark); font-size: 14px;">
                            Ordered on <?php echo date('F d, Y h:i A', strtotime($order['created_at'])); ?>
                        </p>
                    </div>
                    
                    <div class="receipt-items">
                        <?php while ($item = $items->fetch_assoc()): ?>
                            <div class="receipt-item">
                                <div class="receipt-item-name">
                                    <?php echo $item['item_name']; ?>
                                    <span>Qty: <?php echo $item['quantity']; ?> x <?php echo formatPrice($item['unit_price']); ?></span>
                                </div>
                                <div class="receipt-item-price">
                                    <?php echo formatPrice($item['subtotal']); ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                    
                    <div class="receipt-total">
                        <span>Total Amount</span>
                        <span><?php echo formatPrice($order['total_amount']); ?></span>
                    </div>
                    
                    <div style="margin-top: 20px; padding-top: 20px; border-top: 1px dashed var(--gray);">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span style="color: var(--gray-dark);">Payment Method:</span>
                            <span style="font-weight: 600; text-transform: uppercase;">
                                <?php 
                                    echo $order['payment_method'] === 'gcash' ? '<i class="fas fa-mobile-alt"></i> GCash' : 
                                         ($order['payment_method'] === 'paymaya' ? '<i class="fas fa-credit-card"></i> PayMaya' : 
                                          '<i class="fas fa-cash-register"></i> Pay at Front'); 
                                ?>
                            </span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span style="color: var(--gray-dark);">Payment Status:</span>
                            <span class="badge badge-<?php echo $order['payment_status']; ?>">
                                <?php echo ucfirst($order['payment_status']); ?>
                            </span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: var(--gray-dark);">Order Status:</span>
                            <span class="badge badge-<?php echo $order['order_status']; ?>">
                                <?php echo ucfirst($order['order_status']); ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="receipt-footer">
                    <?php if ($receipt): ?>
                        <a href="receipt.php?code=<?php echo $receipt['receipt_code']; ?>" class="btn btn-primary w-full">
                            <i class="fas fa-receipt"></i>
                            View Receipt
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
<?php $conn->close(); ?>
