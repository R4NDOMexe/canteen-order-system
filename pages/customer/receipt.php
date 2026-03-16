<?php
require_once '../../includes/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('index.php');
}

$conn = getDBConnection();

// Get receipt codes
$codes = isset($_GET['code']) ? explode(',', $_GET['code']) : [];

    if (empty($codes)) {
    setFlashMessage('error', 'No receipt found!');
    redirect('orders.php');
}

// Get receipts
$receipts = [];
foreach ($codes as $code) {
    $stmt = $conn->prepare("
        SELECT r.*, o.void_after, o.is_priority, o.order_status, o.order_code
        FROM receipts r
        JOIN orders o ON r.order_id = o.id
        WHERE r.receipt_code = ? AND r.customer_id = ?
    ");
    $stmt->bind_param("si", $code, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        // fetch associated items
        $itemsStmt = $conn->prepare(
            "SELECT oi.quantity, oi.unit_price, oi.subtotal, mi.name FROM order_items oi JOIN menu_items mi ON oi.menu_item_id = mi.id WHERE oi.order_id = ?"
        );
        $itemsStmt->bind_param('i', $row['order_id']);
        $itemsStmt->execute();
        $itemsResult = $itemsStmt->get_result();
        $row['items'] = $itemsResult->fetch_all(MYSQLI_ASSOC);
        $itemsStmt->close();

        $receipts[] = $row;
    }
}

if (empty($receipts)) {
    setFlashMessage('error', 'Receipt not found!');
    redirect('orders.php');
}

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta http-equiv="refresh" content="30">
    <title>Receipt - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print {
            .sidebar, .page-header, .btn {
                display: none !important;
            }
            .main-content {
                margin-left: 0 !important;
            }
            .receipt-container {
                box-shadow: none !important;
            }
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
                <h1><i class="fas fa-receipt"></i> Order Receipt</h1>
                <div style="display: flex; gap: 12px;">
                    <a href="orders.php" class="btn btn-primary">
                        <i class="fas fa-list"></i>
                        View Orders
                    </a>
                </div>
            </div>
            
            <?php foreach ($receipts as $receipt): 
                $isPayAtFront = $receipt['payment_method'] === 'pay_at_front';
                $isPending = $receipt['payment_status'] === 'pending';
            ?>
                <div class="receipt-container mb-3">
                    <div class="receipt-screenshot-notice">
                        <i class="fas fa-camera"></i>
                        PLEASE SCREENSHOT THIS RECEIPT
                    </div>
                    
                    <div class="receipt-header">
                        <h2><i class="fas fa-check-circle"></i> Order Confirmed!</h2>
                        <div class="receipt-code">
                            Order #<?php echo strtoupper($receipt['order_code']); ?>
                        </div>
                        <?php if ($receipt['is_priority']): ?>
                            <span class="badge badge-priority" style="margin-top: 8px;">
                                <i class="fas fa-star"></i> PRIORITY ORDER
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="receipt-body">
                        <div class="receipt-store">
                            <h3><i class="fas fa-store"></i> <?php echo $receipt['store_name']; ?></h3>
                            <p style="color: var(--gray-dark); font-size: 14px;">
                                <?php echo date('F d, Y h:i A', strtotime($receipt['created_at'])); ?>
                            </p>
                        </div>
                        
                       
                        
<?php if (!empty($receipt['items'])): ?>
                            <div class="receipt-items" style="margin-bottom: 20px;">
                                <?php foreach ($receipt['items'] as $it): ?>
                                    <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                                        <span><?php echo $it['quantity']; ?>x <?php echo htmlspecialchars($it['name']); ?></span>
                                        <span><?php echo formatPrice($it['subtotal']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="receipt-total">
                            <span>Total Amount</span>
                            <span><?php echo formatPrice($receipt['total_amount']); ?></span>
                        </div>
                        
                        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px dashed var(--gray);">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 8px; align-items: center;">
                                <span style="color: var(--gray-dark);">Payment Method:</span>
                                <span style="font-weight: 600; text-transform: uppercase; display:flex; align-items:center; gap:8px;">
                                    <?php 
                                        if ($receipt['payment_method'] === 'gcash') {
                                            echo '<img src="../../uploads/online/gcash.png" alt="GCash" style="height:20px;"> GCash';
                                        } elseif ($receipt['payment_method'] === 'paymaya') {
                                            echo '<img src="../../uploads/online/paymaya.png" alt="PayMaya" style="height:20px;"> PayMaya';
                                        } else {
                                            echo '<i class="fas fa-cash-register"></i> Pay at Front';
                                        }
                                    ?>
                                </span>
                            </div>
                            <div style="display: flex; justify-content: space-between;">
                                <span style="color: var(--gray-dark);">Payment Status:</span>
                                <span class="badge badge-<?php echo $receipt['payment_status']; ?>">
                                    <?php echo ucfirst($receipt['payment_status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="receipt-footer">
                        <?php if ($isPayAtFront && $isPending): ?>
                            <div class="receipt-timer">
                                <i class="fas fa-clock"></i>
                                <?php
                                    // PHP countdown with reload instruction
                                    if (!empty($receipt['void_after'])) {
                                        $secondsLeft = strtotime($receipt['void_after']) - time();
                                        if ($secondsLeft <= 0) {
                                            echo 'Status: <span style="color: var(--danger); font-weight: bold;">EXPIRED</span>';
                                        } else {
                                            $minutes = floor($secondsLeft / 60);
                                            $seconds = $secondsLeft % 60;
                                            echo 'Time remaining: <span style="font-weight: bold;">' . $minutes . 'm ' . $seconds . 's</span> <small style="font-size: 11px; opacity: 0.7;">(Reload page to update)</small>';
                                        }
                                    } else {
                                        echo 'No timer available';
                                    }
                                ?>
                            </div>
                            <p style="font-size: 14px; color: var(--gray-dark);">
                                Please pay at the front counter within 10 minutes.<br>
                                Your order will not be prepared until payment is confirmed.
                            </p>
                        <?php else: ?>

                            <p style="font-size: 14px; color: var(--gray-dark);">
                                <i class="fas fa-check-circle" style="color: var(--success);"></i>
                                Payment confirmed! Your order is being prepared.
                            </p>
                        <?php endif; ?>
                        
                        <p style="margin-top: 12px; font-size: 12px; color: var(--gray-dark);">
                            Thank you for ordering with <?php echo APP_NAME; ?>!
                        </p>
                    </div>
                </div>
            <?php endforeach; ?>
        </main>
    </div>

</body>
</html>
<?php $conn->close(); ?>
