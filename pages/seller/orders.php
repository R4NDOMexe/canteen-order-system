<?php
/**
 * Seller Orders Page
 * View and manage orders
 */
require_once '../../includes/config.php';

// Check if user is logged in and is a seller
if (!isLoggedIn()) {
    redirect('../../index.php');
}

if (!hasRole('seller')) {
    redirect('../customer/menu.php');
}

$conn = getDBConnection();
$sellerId = $_SESSION['user_id'];

// some installations may not yet have the order_status column on receipts;
// we only update it if it exists
$hasReceiptOrderStatus = columnExists('receipts', 'order_status');

// handle status update actions coming from view-order.php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $orderId = intval($_POST['order_id'] ?? 0);

    // verify this order belongs to one of the seller's stores
    $verify = $conn->prepare("SELECT o.payment_method, o.payment_status, o.order_status
        FROM orders o
        JOIN stores s ON o.store_id = s.id
        WHERE o.id = ? AND s.seller_id = ?");
    $verify->bind_param('ii', $orderId, $sellerId);
    $verify->execute();
    $orderRow = $verify->get_result()->fetch_assoc();
    $verify->close();

    if ($orderRow) {
        switch ($action) {
            case 'confirm_payment':
                // only for pay-at-front orders that are pending
                if ($orderRow['payment_method'] === 'pay_at_front' && $orderRow['payment_status'] === 'pending') {
                    $upd = $conn->prepare("UPDATE orders SET payment_status='paid', paid_at = NOW() WHERE id = ?");
                    $upd->bind_param('i', $orderId);
                    $upd->execute();
                    $upd->close();
                    // also update any receipt for this order if exists
                    $r = $conn->prepare("UPDATE receipts SET payment_status='paid' WHERE order_id = ?");
                    $r->bind_param('i', $orderId);
                    $r->execute();
                    $r->close();
                    setFlashMessage('Payment confirmed. Order ready to be prepared.', 'success');
                }
                break;
            case 'preparing':
                if ($orderRow['order_status'] === 'pending') {
                    $upd = $conn->prepare("UPDATE orders SET order_status='preparing' WHERE id = ?");
                    $upd->bind_param('i', $orderId);
                    $upd->execute();
                    $upd->close();
                    if ($hasReceiptOrderStatus) {
                        $r = $conn->prepare("UPDATE receipts SET order_status='preparing' WHERE order_id = ?");
                        $r->bind_param('i', $orderId);
                        $r->execute();
                        $r->close();
                    }
                    setFlashMessage('Order status changed to preparing.', 'success');
                }
                break;
            case 'ready':
                if ($orderRow['order_status'] === 'preparing') {
                    $upd = $conn->prepare("UPDATE orders SET order_status='ready' WHERE id = ?");
                    $upd->bind_param('i', $orderId);
                    $upd->execute();
                    $upd->close();
                    if ($hasReceiptOrderStatus) {
                        $r = $conn->prepare("UPDATE receipts SET order_status='ready' WHERE order_id = ?");
                        $r->bind_param('i', $orderId);
                        $r->execute();
                        $r->close();
                    }
                    setFlashMessage('Order marked ready for pickup.', 'success');
                }
                break;
            case 'completed':
                if ($orderRow['order_status'] === 'ready') {
                    $upd = $conn->prepare("UPDATE orders SET order_status='completed', completed_at = NOW() WHERE id = ?");
                    $upd->bind_param('i', $orderId);
                    $upd->execute();
                    $upd->close();
                    if ($hasReceiptOrderStatus) {
                        $r = $conn->prepare("UPDATE receipts SET order_status='completed' WHERE order_id = ?");
                        $r->bind_param('i', $orderId);
                        $r->execute();
                        $r->close();
                    }
                    setFlashMessage('Order completed.', 'success');
                }
                break;
        }
    }
    // redirect back to the order detail page to avoid resubmission
    redirect("view-order.php?id={$orderId}");
}

// void any pending cash orders that have passed their expiration time
// (use prepared statement below since we need to bind the seller ID)

$voidStmt = $conn->prepare("UPDATE orders o
    JOIN stores s ON o.store_id = s.id
    SET o.payment_status = 'void', o.order_status = 'cancelled'
    WHERE o.payment_status = 'pending'
      AND o.payment_method = 'pay_at_front'
      AND o.void_after IS NOT NULL
      AND o.void_after < NOW()
      AND s.seller_id = ?");
$voidStmt->bind_param('i', $sellerId);
$voidStmt->execute();
$voidStmt->close();

// Get seller's stores - MySQLi version
$storesStmt = $conn->prepare("SELECT id FROM stores WHERE seller_id = ?");
$storesStmt->bind_param('i', $sellerId);
$storesStmt->execute();
$storesResult = $storesStmt->get_result();
$storeIds = [];
while ($row = $storesResult->fetch_assoc()) {
    $storeIds[] = $row['id'];
}
$storesStmt->close();

// Get orders - MySQLi version (with optional status filter)
$orders = [];
// filter: active (default), all, completed
$filter = $_GET['filter'] ?? 'active';
if (!in_array($filter, ['active', 'all', 'completed'])) {
    $filter = 'active';
}

if (!empty($storeIds)) {
    $placeholders = implode(',', array_fill(0, count($storeIds), '?'));
    $types = str_repeat('i', count($storeIds));

    // build optional status clause
    $statusSql = '';
    if ($filter === 'active') {
        $statusSql = " AND o.order_status IN ('pending','preparing','ready')";
    } elseif ($filter === 'completed') {
        $statusSql = " AND o.order_status = 'completed'";
    } // 'all' => no additional clause

    $sql = "SELECT o.*, s.store_name, u.full_name as customer_name
        FROM orders o
        JOIN stores s ON o.store_id = s.id
        JOIN users u ON o.customer_id = u.id
        WHERE o.store_id IN ($placeholders) " . $statusSql . "
        ORDER BY o.is_priority DESC, o.created_at DESC";

    $ordersStmt = $conn->prepare($sql);
    $ordersStmt->bind_param($types, ...$storeIds);
    $ordersStmt->execute();
    $ordersResult = $ordersStmt->get_result();
    while ($row = $ordersResult->fetch_assoc()) {
        if (empty($row['payment_status'])) {
            $row['payment_status'] = 'pending';
        }
        if (empty($row['order_status'])) {
            $row['order_status'] = 'pending';
        }
        $orders[] = $row;
    }
    $ordersStmt->close();
}

// Get pending count
$pendingCount = 0;
foreach ($orders as $order) {
    if (in_array($order['order_status'], ['pending', 'preparing'])) {
        $pendingCount++;
    }
}

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - <?php echo APP_NAME; ?></title>
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
                        <?php if ($pendingCount > 0): ?>
                            <span class="badge badge-danger" style="margin-left: auto;"><?php echo $pendingCount; ?></span>
                        <?php endif; ?>
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
                <h1><i class="fas fa-shopping-bag"></i> Orders</h1>
                <div style="margin-top:12px; display:flex; gap:8px;">
                    <?php $curFilter = $_GET['filter'] ?? 'active'; ?>
                    <a href="orders.php?filter=active" class="btn <?php echo $curFilter === 'active' ? 'btn-primary' : 'btn-outline'; ?>">Active</a>
                    <a href="orders.php?filter=all" class="btn <?php echo $curFilter === 'all' ? 'btn-primary' : 'btn-outline'; ?>">All Orders</a>
                    <a href="orders.php?filter=completed" class="btn <?php echo $curFilter === 'completed' ? 'btn-primary' : 'btn-outline'; ?>">Completed</a>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-list"></i> All Orders</h3>
                    <span style="color: var(--gray-dark); font-size: 14px;">
                        <?php echo count($orders); ?> total order(s)
                    </span>
                </div>
                <div class="card-body">
                    <?php if (count($orders) > 0): ?>
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Order Code</th>
                                        <th>Customer</th>
                                        <th>Store</th>
                                        <th>Total</th>
                                        <th>Method</th>
                                        <th>Payment</th>
                                        <th>Status</th>
                                        <th>Priority</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                        <tr>
                                            <td><strong>#<?php echo $order['order_code']; ?></strong></td>
                                            <td><i class="fas fa-user"></i> <?php echo $order['customer_name']; ?></td>
                                            <td><i class="fas fa-store"></i> <?php echo $order['store_name']; ?></td>
                                            <td><strong><?php echo formatPrice($order['total_amount']); ?></strong></td>
                                            <td>
                                                <?php
                                                    echo $order['payment_method'] === 'gcash' ? '<i class="fas fa-mobile-alt"></i> GCash' :
                                                         ($order['payment_method'] === 'paymaya' ? '<i class="fas fa-credit-card"></i> PayMaya' :
                                                          '<i class="fas fa-cash-register"></i> Pay at Front');
                                                ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo $order['payment_status']; ?>">
                                                    <?php echo ucfirst($order['payment_status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo $order['order_status']; ?>">
                                                    <?php echo ucfirst($order['order_status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($order['is_priority']): ?>
                                                    <span class="badge badge-priority">
                                                        <i class="fas fa-star"></i> TEACHER
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color: var(--gray-dark);">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('M d, h:i A', strtotime($order['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="fas fa-shopping-bag"></i>
                            </div>
                            <h3>No orders yet</h3>
                            <p>Orders will appear here when customers place them.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
