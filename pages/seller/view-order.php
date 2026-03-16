<?php
require_once '../../includes/config.php';

// Check if user is logged in and is a seller
if (!isLoggedIn()) {
    redirect('index.php');
}

if (!hasRole('seller')) {
    redirect('pages/customer/menu.php');
}

$conn = getDBConnection();
$sellerId = $_SESSION['user_id'];

$orderId = intval($_GET['id'] ?? 0);

// Get order details with verification
$stmt = $conn->prepare("
    SELECT o.*, s.store_name, s.seller_id, u.full_name as customer_name, u.email as customer_email, u.role as customer_role
    FROM orders o
    JOIN stores s ON o.store_id = s.id
    JOIN users u ON o.customer_id = u.id
    WHERE o.id = ? AND s.seller_id = ?
");
$stmt->bind_param("ii", $orderId, $sellerId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
// default any missing statuses to pending so buttons appear
if ($order) {
    if (empty($order['payment_status'])) {
        $order['payment_status'] = 'pending';
    }
    if (empty($order['order_status'])) {
        $order['order_status'] = 'pending';
    }
}

if (!$order) {
    setFlashMessage('error', 'Order not found or access denied!');
    redirect('pages/seller/orders.php');
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
                    <a href="orders.php" class="nav-item active">
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
                <h1><i class="fas fa-receipt"></i> Order Details</h1>
                <a href="orders.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i>
                    Back to Orders
                </a>
            </div>
            
            <div class="receipt-container">
                <div class="receipt-header" style="<?php echo $order['is_priority'] ? 'background: linear-gradient(135deg, var(--primary), var(--danger)); color: white;' : ''; ?>">
                    <h2>Order #<?php echo $order['order_code']; ?></h2>
                    <?php if ($order['is_priority']): ?>
                        <div style="margin-top: 8px;">
                            <span class="badge" style="background: white; color: var(--danger);">
                                <i class="fas fa-star"></i> PRIORITY ORDER - TEACHER
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="receipt-body">
                    <div class="receipt-store">
                        <h3><i class="fas fa-store"></i> <?php echo $order['store_name']; ?></h3>
                        <p style="color: var(--gray-dark); font-size: 14px;">
                            Ordered on <?php echo date('F d, Y h:i A', strtotime($order['created_at'])); ?>
                        </p>
                    </div>
                    
                    <div style="background: var(--gray-light); padding: 16px; border-radius: var(--radius); margin-bottom: 20px;">
                        <h4 style="margin-bottom: 12px;"><i class="fas fa-user"></i> Customer Information</h4>
                        <p style="margin-bottom: 4px;"><strong>Name:</strong> <?php echo $order['customer_name']; ?></p>
                        <p style="margin-bottom: 4px;"><strong>Email:</strong> <?php echo $order['customer_email']; ?></p>
                        <p><strong>Role:</strong> 
                            <span class="badge badge-<?php echo $order['customer_role'] === 'teacher' ? 'priority' : 'pending'; ?>">
                                <?php echo ucfirst($order['customer_role']); ?>
                            </span>
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
                    <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
                        <?php if ($order['payment_method'] === 'pay_at_front' && $order['payment_status'] === 'pending'): ?>
                            <form method="POST" action="orders.php" style="display: inline;">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <input type="hidden" name="action" value="confirm_payment">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-check"></i> Confirm Payment
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <?php if ($order['payment_status'] === 'paid' && $order['order_status'] === 'pending'): ?>
                            <form method="POST" action="orders.php" style="display: inline;">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <input type="hidden" name="action" value="preparing">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-fire"></i> Start Preparing
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <?php if ($order['order_status'] === 'preparing'): ?>
                            <form method="POST" action="orders.php" style="display: inline;">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <input type="hidden" name="action" value="ready">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-check"></i> Mark as Ready
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <?php if ($order['order_status'] === 'ready'): ?>
                            <form method="POST" action="orders.php" style="display: inline;">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <input type="hidden" name="action" value="completed">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-check-double"></i> Complete Order
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
<?php $conn->close(); ?>
