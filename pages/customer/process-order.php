<?php
require_once '../../includes/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('../../index.php');
}

// Allow customers, students, teachers
$userRole = $_SESSION['role'] ?? '';
$allowedRoles = ['customer', 'student', 'teacher'];
if (!in_array($userRole, $allowedRoles)) {
    redirect('../../index.php');
}

// Check if cart is empty
if (empty($_SESSION['cart'])) {
    setFlashMessage('error', 'Your cart is empty!');
    redirect('pages/customer/cart.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paymentMethod = $_POST['payment_method'] ?? '';
    
    if (!in_array($paymentMethod, ['gcash', 'paymaya', 'pay_at_front'])) {
        setFlashMessage('error', 'Invalid payment method!');
        redirect('pages/customer/checkout.php');
    }
    
    $conn = getDBConnection();
    
    // Group cart items by store
    $storeOrders = [];
    foreach ($_SESSION['cart'] as $item) {
        $storeId = $item['store_id'];
        if (!isset($storeOrders[$storeId])) {
            $storeOrders[$storeId] = [
                'store_id' => $storeId,
                'store_name' => $item['store_name'],
                'items' => [],
                'total' => 0
            ];
        }
        $storeOrders[$storeId]['items'][] = $item;
        $storeOrders[$storeId]['total'] += $item['price'] * $item['quantity'];
    }
    
    $orderIds = [];
    $receiptCodes = [];
    
    // Create order for each store
    foreach ($storeOrders as $storeOrder) {
        $orderCode = generateCode('AU');
        $receiptCode = generateCode('AU');
        $customerId = $_SESSION['user_id'];
        $storeId = $storeOrder['store_id'];
        $totalAmount = $storeOrder['total'] * 1.12; // With tax
        $isPriority = hasRole('teacher') ? 1 : 0;
        
        // Calculate void time (10 minutes for pay_at_front)
        $voidAfter = null;
        if ($paymentMethod === 'pay_at_front') {
            $voidAfter = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        }
        
        // Insert order
        $stmt = $conn->prepare("
            INSERT INTO orders (order_code, customer_id, store_id, total_amount, payment_method, 
                              payment_status, order_status, is_priority, void_after)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $paymentStatus = $paymentMethod === 'pay_at_front' ? 'pending' : 'paid';
        $orderStatus = $paymentMethod === 'pay_at_front' ? 'pending' : 'preparing';
        
        $stmt->bind_param("siidsssis", 
    $orderCode, $customerId, $storeId, $totalAmount, $paymentMethod,
    $paymentStatus, $orderStatus, $isPriority, $voidAfter
);

        
        $stmt->execute();
        $orderId = $stmt->insert_id;
        $orderIds[] = $orderId;
        $receiptCodes[] = $receiptCode;
        
        // Insert order items
        $itemStmt = $conn->prepare("
            INSERT INTO order_items (order_id, menu_item_id, quantity, unit_price, subtotal)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        foreach ($storeOrder['items'] as $item) {
            $subtotal = $item['price'] * $item['quantity'];
            $itemStmt->bind_param("iiidd", 
                $orderId, $item['item_id'], $item['quantity'], $item['price'], $subtotal
            );
            $itemStmt->execute();
        }
        
        // Create receipt record (expires after 36 hours)
        $itemsJson = json_encode($storeOrder['items']);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . RECEIPT_EXPIRY_HOURS . ' hours'));
        
        $receiptStmt = $conn->prepare("
            INSERT INTO receipts (receipt_code, order_id, customer_id, store_id, store_name, 
                                total_amount, payment_method, payment_status, items_json, expires_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $receiptStmt->bind_param("siiisdssss",
            $receiptCode, $orderId, $customerId, $storeId, $storeOrder['store_name'],
            $totalAmount, $paymentMethod, $paymentStatus, $itemsJson, $expiresAt
        );
        $receiptStmt->execute();
    }
    
    // Clear cart
    $_SESSION['cart'] = [];
    
    // Redirect to receipt
    $receiptParam = implode(',', $receiptCodes);
    redirect('pages/customer/receipt.php?code=' . $receiptParam);
}

redirect('pages/customer/checkout.php');
?>
