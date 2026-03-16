<?php
/**
 * Customer Cart Page
 * View and manage cart items
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

// Handle cart actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update':
                $itemId = $_POST['item_id'];
                $quantity = intval($_POST['quantity']);
                if ($quantity > 0) {
                    $_SESSION['cart'][$itemId]['quantity'] = $quantity;
                } else {
                    unset($_SESSION['cart'][$itemId]);
                }
                break;
                
            case 'increment':
                $itemId = $_POST['item_id'];
                if (isset($_SESSION['cart'][$itemId])) {
                    $_SESSION['cart'][$itemId]['quantity']++;
                }
                break;
                
            case 'decrement':
                $itemId = $_POST['item_id'];
                if (isset($_SESSION['cart'][$itemId])) {
                    $_SESSION['cart'][$itemId]['quantity']--;
                    if ($_SESSION['cart'][$itemId]['quantity'] <= 0) {
                        unset($_SESSION['cart'][$itemId]);
                    }
                }
                break;
                
            case 'remove':
                $itemId = $_POST['item_id'];
                unset($_SESSION['cart'][$itemId]);
                break;
                
            case 'clear':
                $_SESSION['cart'] = [];
                break;
        }
    }
    redirect('cart.php');
}

// Calculate cart totals
$cartItems = $_SESSION['cart'] ?? [];
$cartCount = 0;
$cartTotal = 0;

foreach ($cartItems as $item) {
    $cartCount += $item['quantity'];
    $cartTotal += $item['price'] * $item['quantity'];
}

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Cart - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .cart-item {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 20px;
            border-bottom: 1px solid var(--gray);
        }
        .cart-item:last-child {
            border-bottom: none;
        }
        .cart-item-image {
            width: 80px;
            height: 80px;
            background: var(--gray-light);
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .cart-item-image i {
            font-size: 32px;
            color: var(--gray-dark);
        }
        .cart-item-details {
            flex: 1;
        }
        .cart-item-details h4 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 4px;
        }
        .cart-item-details p {
            color: var(--gray-dark);
            font-size: 14px;
        }
        .cart-item-price {
            font-size: 18px;
            font-weight: 700;
            color: var(--primary-dark);
        }
        .cart-item-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .quantity-control {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .quantity-control input {
            width: 60px;
            text-align: center;
            padding: 8px;
            border: 2px solid var(--gray);
            border-radius: var(--radius);
        }
        .cart-summary {
            background: var(--gray-light);
            padding: 24px;
            border-radius: var(--radius-lg);
        }
        .cart-summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
        }
        .cart-summary-row.total {
            font-size: 20px;
            font-weight: 700;
            padding-top: 12px;
            border-top: 2px solid var(--gray);
            margin-top: 12px;
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
                <h1><i class="fas fa-shopping-cart"></i> My Cart</h1>
                <a href="menu.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Continue Shopping
                </a>
            </div>
            
            <?php if (count($cartItems) > 0): ?>
                <div style="display: grid; grid-template-columns: 1fr 350px; gap: 24px;">
                    <!-- Cart Items -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-list"></i> Cart Items (<?php echo $cartCount; ?>)</h3>
                            <form method="POST" action="" style="display: inline;">
                                <input type="hidden" name="action" value="clear">
                                <button type="submit" class="btn btn-danger btn-sm">
                                    <i class="fas fa-trash"></i> Clear Cart
                                </button>
                            </form>
                        </div>
                        <div class="card-body" style="padding: 0;">
                            <?php foreach ($cartItems as $itemId => $item): ?>
                                <div class="cart-item">
                                    <div class="cart-item-image">
                                        <i class="fas fa-utensils"></i>
                                    </div>
                                    <div class="cart-item-details">
                                        <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                                        <p><i class="fas fa-store"></i> <?php echo htmlspecialchars($item['store_name']); ?></p>
                                        <p class="cart-item-price"><?php echo formatPrice($item['price']); ?> each</p>
                                    </div>
                                    <div class="cart-item-actions">
                                        <form method="POST" action="" class="quantity-control">
                                            <input type="hidden" name="item_id" value="<?php echo $itemId; ?>">
                                            <button type="submit" name="action" value="decrement" class="btn btn-secondary btn-sm">
                                                <i class="fas fa-minus"></i>
                                            </button>
                                            <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="0">
                                            <button type="submit" name="action" value="increment" class="btn btn-secondary btn-sm">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                            <button type="submit" name="action" value="update" class="btn btn-primary btn-sm">
                                                Update
                                            </button>
                                        </form>
                                        <form method="POST" action="">
                                            <input type="hidden" name="action" value="remove">
                                            <input type="hidden" name="item_id" value="<?php echo $itemId; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Cart Summary -->
                    <div class="cart-summary">
                        <h3 style="margin-bottom: 20px;"><i class="fas fa-receipt"></i> Order Summary</h3>
                        <div class="cart-summary-row">
                            <span>Items (<?php echo $cartCount; ?>)</span>
                            <span><?php echo formatPrice($cartTotal); ?></span>
                        </div>
                        <div class="cart-summary-row">
                            <span>Service Fee</span>
                            <span><?php echo formatPrice(0); ?></span>
                        </div>
                        <div class="cart-summary-row total">
                            <span>Total</span>
                            <span><?php echo formatPrice($cartTotal); ?></span>
                        </div>
                        <a href="checkout.php" class="btn btn-primary btn-lg" style="margin-top: 20px;">
                            <i class="fas fa-credit-card"></i> Proceed to Checkout
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-body">
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <h3>Your cart is empty</h3>
                            <p>Browse our stores and add items to your cart.</p>
                            <a href="menu.php" class="btn btn-primary btn-lg" style="margin-top: 16px;">
                                <i class="fas fa-store"></i> Browse Stores
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
