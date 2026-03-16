<?php
/**
 * Customer Checkout Page
 * Process order checkout
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

// Get cart items
$cartItems = $_SESSION['cart'] ?? [];
$cartCount = 0;
$cartTotal = 0;

foreach ($cartItems as $item) {
    $cartCount += $item['quantity'];
    $cartTotal += $item['price'] * $item['quantity'];
}

// Redirect if cart is empty
if (empty($cartItems)) {
    setFlashMessage('Your cart is empty.', 'error');
    redirect('cart.php');
}

// Handle checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paymentMethod = $_POST['payment_method'] ?? 'pay_at_front';
    $storeId = $_POST['store_id'] ?? null;
    
    if (!$storeId) {
        setFlashMessage('Invalid store.', 'error');
        redirect('cart.php');
    }
    
    // Check if user is a teacher for priority
    $isPriority = ($_SESSION['role'] === 'teacher') ? 1 : 0;
    
    // Determine starting statuses based on payment method
    if ($paymentMethod === 'pay_at_front') {
        $initialOrderStatus = 'pending';
        $initialPaymentStatus = 'pending';
        $voidAfter = date('Y-m-d H:i:s', strtotime('+10 minutes')); // expires 10 minutes after
    } else {
        // GCASH and PAY MAYA treated as instant payments
        $initialOrderStatus = 'preparing';
        $initialPaymentStatus = 'paid';
        $voidAfter = null;
    }
    
    // Create order - MySQLi version
    $orderCode = generateOrderCode();
    $stmt = $conn->prepare("
        INSERT INTO orders (order_code, customer_id, store_id, total_amount, order_status, payment_status, payment_method, is_priority, void_after)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('siidsisis', $orderCode, $_SESSION['user_id'], $storeId, $cartTotal, $initialOrderStatus, $initialPaymentStatus, $paymentMethod, $isPriority, $voidAfter);
    if ($stmt->execute()) {
        $orderId = $conn->insert_id;
        $stmt->close();
    } else {
        setFlashMessage('Failed to create order.', 'error');
        redirect('cart.php');
    }
    
    // Add order items - MySQLi version
    $itemStmt = $conn->prepare("
        INSERT INTO order_items (order_id, menu_item_id, quantity, unit_price, subtotal)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    foreach ($cartItems as $item) {
        $subtotal = $item['price'] * $item['quantity'];
        $itemStmt->bind_param('iisid', $orderId, $item['id'], $item['quantity'], $item['price'], $subtotal);
        $itemStmt->execute();
    }
    $itemStmt->close();
    
    // Create receipt - MySQLi version
    $receiptCode = generateReceiptCode();
    $storeName = $cartItems[array_key_first($cartItems)]['store_name'];
    $expiresAt = date('Y-m-d H:i:s', strtotime('+' . RECEIPT_EXPIRY_HOURS . ' hours'));
    
    // determine whether the receipts table actually has order_status column
    $hasOrderStatus = false;
    $col = $conn->query("SHOW COLUMNS FROM receipts LIKE 'order_status'");
    if ($col && $col->num_rows > 0) {
        $hasOrderStatus = true;
    }
    
    // try to add the column if missing (best effort; failure is non-fatal)
    if (!$hasOrderStatus) {
        try {
            $conn->query("ALTER TABLE receipts ADD COLUMN order_status VARCHAR(20)");
            $conn->query("UPDATE receipts SET order_status='pending' WHERE order_status IS NULL");
            $hasOrderStatus = true;
        } catch (mysqli_sql_exception $e) {
            // ignore errors; we will just insert without the column below
        }
    }
    
    if ($hasOrderStatus) {
        $receiptStmt = $conn->prepare("
            INSERT INTO receipts (receipt_code, order_id, customer_id, store_name, total_amount, payment_status, payment_method, order_status, expires_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $receiptStmt->bind_param('siisdssss', $receiptCode, $orderId, $_SESSION['user_id'], $storeName, $cartTotal, $initialPaymentStatus, $paymentMethod, $initialOrderStatus, $expiresAt);
    } else {
        $receiptStmt = $conn->prepare("
            INSERT INTO receipts (receipt_code, order_id, customer_id, store_name, total_amount, payment_status, payment_method, expires_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $receiptStmt->bind_param('siisdsss', $receiptCode, $orderId, $_SESSION['user_id'], $storeName, $cartTotal, $initialPaymentStatus, $paymentMethod, $expiresAt);
    }
    $receiptStmt->execute();
    $receiptStmt->close();
    
    // Clear cart
    $_SESSION['cart'] = [];
    
    // redirect to receipt view immediately with the generated receipt code
    // the order code will be shown there as well
    // no flash message needed since receipt page displays details
    redirect('receipt.php?code=' . $receiptCode);
}

// Group cart items by store
$storeGroups = [];
foreach ($cartItems as $item) {
    $storeId = $item['store_id'];
    if (!isset($storeGroups[$storeId])) {
        $storeGroups[$storeId] = [
            'name' => $item['store_name'],
            'items' => [],
            'total' => 0
        ];
    }
    $storeGroups[$storeId]['items'][] = $item;
    $storeGroups[$storeId]['total'] += $item['price'] * $item['quantity'];
}

// For simplicity, we'll use the first store's ID
$firstStoreId = array_key_first($storeGroups);

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - <?php echo APP_NAME; ?></title>
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
                    <a href="cart.php" class="nav-item active">
                        <i class="fas fa-shopping-cart"></i>
                        My Cart
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Orders</div>
                    <a href="orders.php" class="nav-item">
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
                <h1><i class="fas fa-credit-card"></i> Checkout</h1>
                <a href="cart.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Cart
                </a>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 350px; gap: 24px;">
                <!-- Order Summary -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-receipt"></i> Order Summary</h3>
                    </div>
                    <div class="card-body">
                        <?php foreach ($storeGroups as $storeId => $store): ?>
                            <div style="margin-bottom: 24px; padding-bottom: 24px; border-bottom: 1px solid var(--gray);">
                                <h4 style="margin-bottom: 12px;"><i class="fas fa-store"></i> <?php echo $store['name']; ?></h4>
                                <?php foreach ($store['items'] as $item): ?>
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                        <span><?php echo $item['quantity']; ?>x <?php echo $item['name']; ?></span>
                                        <span><?php echo formatPrice($item['price'] * $item['quantity']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                                <div style="display: flex; justify-content: space-between; margin-top: 12px; padding-top: 12px; border-top: 1px dashed var(--gray);">
                                    <strong>Store Total</strong>
                                    <strong><?php echo formatPrice($store['total']); ?></strong>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <div style="display: flex; justify-content: space-between; font-size: 20px; font-weight: 700; padding-top: 16px; border-top: 2px solid var(--gray);">
                            <span>Total Amount</span>
                            <span><?php echo formatPrice($cartTotal); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Payment Form -->
                <div>
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-wallet"></i> Payment</h3>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="store_id" value="<?php echo $firstStoreId; ?>">
                                
                                <div class="form-group">
                                    <label>Payment Method</label>
                                    <div style="display: flex; flex-direction: column; gap: 12px;">
                                        <label style="display: flex; align-items: center; gap: 12px; padding: 16px; border: 2px solid var(--primary); border-radius: var(--radius); cursor: pointer; background: rgba(255, 199, 44, 0.1);">
                                            <input type="radio" name="payment_method" value="pay_at_front" checked>
                                            <i class="fas fa-money-bill-wave" style="font-size: 24px; color: var(--success);"></i>
                                            <div>
                                                <strong>Cash on Pickup</strong>
                                                <p style="font-size: 12px; color: var(--gray-dark); margin: 0;">Pay when you pick up your order</p>
                                            </div>
                                        </label>
                                        <label style="display: flex; align-items: center; gap: 12px; padding: 16px; border: 2px solid var(--primary); border-radius: var(--radius); cursor: pointer;">
                                            <input type="radio" name="payment_method" value="gcash">
                                            <img src="../../uploads/online/gcash.png" alt="GCash" style="width:24px;height:24px;">
                                            <div>
                                                <strong>GCASH</strong>
                                                <p style="font-size: 12px; color: var(--gray-dark); margin: 0;">Pay with GCash mobile wallet</p>
                                            </div>
                                        </label>
                                        <label style="display: flex; align-items: center; gap: 12px; padding: 16px; border: 2px solid var(--primary); border-radius: var(--radius); cursor: pointer;">
                                            <input type="radio" name="payment_method" value="paymaya">
                                            <img src="../../uploads/online/paymaya.png" alt="PayMaya" style="width:24px;height:24px;">
                                            <div>
                                                <strong>PayMaya</strong>
                                                <p style="font-size: 12px; color: var(--gray-dark); margin: 0;">Pay with PayMaya mobile wallet</p>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary btn-lg" style="margin-top: 20px;">
                                    <i class="fas fa-check"></i> Place Order
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <?php if ($_SESSION['role'] === 'teacher'): ?>
                    <div class="alert alert-info" style="margin-top: 16px;">
                        <i class="fas fa-star"></i>
                        <strong>Teacher Priority:</strong> Your order will be marked as priority for faster processing!
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
