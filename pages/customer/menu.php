<?php
/**
 * Customer Menu Page
 * Browse stores and menu items
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

// Get view mode
$viewStoreId = isset($_GET['store']) ? intval($_GET['store']) : 0;
$viewCategoryId = isset($_GET['category']) ? intval($_GET['category']) : 0;

// Get all active stores - MySQLi version
$storesResult = $conn->query("
    SELECT s.*, 
           (SELECT COUNT(*) FROM menu_items WHERE store_id = s.id AND is_available = 1) as item_count
    FROM stores s
    WHERE s.is_active = 1
    ORDER BY s.store_name
");
$stores = [];
while ($row = $storesResult->fetch_assoc()) {
    $stores[] = $row;
}

// Get all categories - MySQLi version
$categoriesResult = $conn->query("SELECT * FROM categories ORDER BY display_order");
$categories = [];
while ($row = $categoriesResult->fetch_assoc()) {
    $categories[] = $row;
}
$categoriesList = [];
foreach ($categories as $cat) {
    $categoriesList[$cat['id']] = $cat;
}

// Get current store details if viewing - MySQLi version
$currentStore = null;
$storeItems = [];
if ($viewStoreId > 0) {
    $storeStmt = $conn->prepare("
        SELECT s.*, 
               (SELECT COUNT(*) FROM menu_items WHERE store_id = s.id AND is_available = 1) as item_count
        FROM stores s
        WHERE s.id = ? AND s.is_active = 1
    ");
    $storeStmt->bind_param('i', $viewStoreId);
    $storeStmt->execute();
    $storeResult = $storeStmt->get_result();
    $currentStore = $storeResult->fetch_assoc();
    $storeStmt->close();
    
    if ($currentStore) {
        // Build query based on category filter - MySQLi version
        if ($viewCategoryId > 0) {
            $itemsStmt = $conn->prepare("
                SELECT mi.*, c.name as category_name, c.id as category_id
                FROM menu_items mi
                JOIN categories c ON mi.category_id = c.id
                WHERE mi.store_id = ? AND mi.is_available = 1 AND mi.category_id = ?
                ORDER BY c.display_order, mi.name
            ");
            $itemsStmt->bind_param('ii', $viewStoreId, $viewCategoryId);
            $itemsStmt->execute();
        } else {
            $itemsStmt = $conn->prepare("
                SELECT mi.*, c.name as category_name, c.id as category_id
                FROM menu_items mi
                JOIN categories c ON mi.category_id = c.id
                WHERE mi.store_id = ? AND mi.is_available = 1
                ORDER BY c.display_order, mi.name
            ");
            $itemsStmt->bind_param('i', $viewStoreId);
            $itemsStmt->execute();
        }
        
        $itemsResult = $itemsStmt->get_result();
        while ($item = $itemsResult->fetch_assoc()) {
            $catId = $item['category_id'];
            if (!isset($storeItems[$catId])) {
                $storeItems[$catId] = [
                    'category_name' => $item['category_name'],
                    'items' => []
                ];
            }
            $storeItems[$catId]['items'][] = $item;
        }
    }
}

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
    <title>Menu - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .stores-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }
        .store-card-customer {
            background: var(--white);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow);
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: var(--secondary);
            display: block;
        }
        .store-card-customer:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }
        .store-card-customer .store-image {
            height: 160px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .store-card-customer .store-image i {
            font-size: 64px;
            color: white;
        }
        .store-card-customer .store-info {
            padding: 20px;
        }
        .store-card-customer h3 {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .store-card-customer p {
            color: var(--gray-dark);
            font-size: 14px;
            margin-bottom: 12px;
        }
        .store-card-customer .item-count {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--gray-light);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
        }
        .category-filter {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }
        .category-filter a {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: var(--white);
            border: 2px solid var(--gray);
            border-radius: var(--radius);
            text-decoration: none;
            color: var(--secondary);
            font-weight: 500;
            transition: all 0.2s ease;
        }
        .category-filter a:hover {
            border-color: var(--primary);
        }
        .category-filter a.active {
            background: var(--primary);
            border-color: var(--primary);
            color: var(--white);
        }
        .category-filter a.active i {
            color: var(--white);
        }
        .category-section {
            margin-bottom: 32px;
        }
        .category-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--gray);
        }
        .category-header i {
            width: 48px;
            height: 48px;
            background: var(--primary);
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: var(--white);
        }
        .category-header h3 {
            font-size: 22px;
            font-weight: 600;
        }
        .menu-items-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            width: 100%;
        }
        .menu-item-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
        }
        .menu-item-card:hover {
            box-shadow: var(--shadow-lg);
        }
        .menu-item-image {
            height: 160px;
            background: var(--gray-light);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
        }
        .menu-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .menu-item-image i {
            font-size: 48px;
            color: var(--gray);
        }
        .menu-item-info {
            padding: 16px;
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }
        .menu-item-info h4 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .menu-item-info p {
            color: var(--gray-dark);
            font-size: 13px;
            margin-bottom: 12px;
            line-height: 1.5;
            flex: 1;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        .menu-item-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: auto;
        }
        .menu-item-price {
            font-size: 18px;
            font-weight: 700;
            color: var(--primary-dark);
        }
        .store-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 24px;
            padding: 24px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: var(--radius-lg);
            color: white;
        }
        .store-header h2 {
            font-size: 28px;
            margin-bottom: 8px;
        }
        .store-header p {
            opacity: 0.9;
        }
        .store-header .item-count {
            background: rgba(255,255,255,0.2);
            padding: 8px 16px;
            border-radius: var(--radius);
            font-weight: 500;
        }
        @media (max-width: 1024px) {
            .menu-items-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (max-width: 640px) {
            .menu-items-grid {
                grid-template-columns: 1fr;
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
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div class="sidebar-logo">
                        <i class="fas fa-utensils"></i>
                    </div>
                </div>
                <div>
                    <h2><?php echo APP_NAME; ?></h2>
                    <span><?php echo ucfirst($_SESSION['role']); ?></span>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">Menu</div>
                    <a href="menu.php" class="nav-item active">
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
            
            <?php if ($currentStore): ?>
                <!-- Store Detail View -->
                <div class="store-header">
                    <div>
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                            <a href="menu.php" style="color: white; text-decoration: none;">
                                <i class="fas fa-arrow-left"></i> Back to Stores
                            </a>
                        </div>
                        <h2><i class="fas fa-store"></i> <?php echo $currentStore['store_name']; ?></h2>
                        <p><?php echo $currentStore['description'] ?: 'Delicious food awaits!'; ?></p>
                    </div>
                    <div class="item-count">
                        <i class="fas fa-utensils"></i> <?php echo $currentStore['item_count']; ?> items
                    </div>
                </div>
                
                <!-- Category Filter -->
                <div class="category-filter">
                    <a href="menu.php?store=<?php echo $currentStore['id']; ?>" class="<?php echo $viewCategoryId == 0 ? 'active' : ''; ?>">
                        <i class="fas fa-th-large"></i> All Items
                    </a>
                    <?php foreach ($categoriesList as $catId => $cat): ?>
                        <a href="menu.php?store=<?php echo $currentStore['id']; ?>&category=<?php echo $catId; ?>" 
                           class="<?php echo $viewCategoryId == $catId ? 'active' : ''; ?>">
                            <i class="fas fa-<?php 
                                echo $cat['name'] === 'Meals' ? 'hamburger' : 
                                     ($cat['name'] === 'Drinks' ? 'glass-whiskey' : 
                                      ($cat['name'] === 'Snacks' ? 'cookie-bite' : 'ice-cream')); 
                            ?>"></i>
                            <?php echo $cat['name']; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                
                <!-- Menu Items by Category -->
                <?php if (!empty($storeItems)): ?>
                    <?php foreach ($storeItems as $catId => $categoryData): ?>
                        <div class="category-section">
                            <div class="category-header">
                                <i class="fas fa-<?php 
                                    echo $categoryData['category_name'] === 'Meals' ? 'hamburger' : 
                                         ($categoryData['category_name'] === 'Drinks' ? 'glass-whiskey' : 
                                          ($categoryData['category_name'] === 'Snacks' ? 'cookie-bite' : 'ice-cream')); 
                                ?>"></i>
                                <h3><?php echo $categoryData['category_name']; ?></h3>
                            </div>
                            
                            <div class="menu-items-grid">
                                <?php foreach ($categoryData['items'] as $item): ?>
                                    <div class="menu-item-card">
                                        <div class="menu-item-image">
                                            <?php if (!empty($item['image']) && file_exists('../../' . $item['image'])): ?>
                                                <img src="../../<?php echo $item['image']; ?>" alt="<?php echo $item['name']; ?>">
                                            <?php else: ?>
                                                <i class="fas fa-utensils"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="menu-item-info">
                                            <h4><?php echo $item['name']; ?></h4>
                                            <p><?php echo $item['description'] ?: 'Delicious ' . strtolower($item['name']); ?></p>
                                            <div class="menu-item-footer">
                                                <span class="menu-item-price"><?php echo formatPrice($item['price']); ?></span>
                                                <form method="POST" action="add-to-cart.php" style="display: inline;">
                                                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                    <input type="hidden" name="name" value="<?php echo $item['name']; ?>">
                                                    <input type="hidden" name="price" value="<?php echo $item['price']; ?>">
                                                    <input type="hidden" name="store_id" value="<?php echo $currentStore['id']; ?>">
                                                    <input type="hidden" name="store_name" value="<?php echo $currentStore['store_name']; ?>">
                                                    <button type="submit" class="btn btn-primary">
                                                        <i class="fas fa-plus"></i> Add
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-utensils"></i>
                        </div>
                        <h3>No items available</h3>
                        <p>This store doesn't have any items in this category yet.</p>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <!-- Store List View -->
                <div class="page-header">
                    <h1><i class="fas fa-store"></i> Select a Store</h1>
                    <a href="cart.php" class="btn btn-primary">
                        <i class="fas fa-shopping-cart"></i>
                        Cart (<?php echo $cartCount; ?>)
                    </a>
                </div>
                
                <p style="color: var(--gray-dark); margin-bottom: 24px;">
                    Choose a store to browse their menu and place your order.
                </p>
                
                <div class="stores-grid">
                    <?php if (count($stores) > 0): ?>
                        <?php foreach ($stores as $store): ?>
                            <a href="menu.php?store=<?php echo $store['id']; ?>" class="store-card-customer">
                                <div class="store-image">
                                    <i class="fas fa-store"></i>
                                </div>
                                <div class="store-info">
                                    <h3><?php echo $store['store_name']; ?></h3>
                                    <p><?php echo $store['description'] ?: 'Click to view menu'; ?></p>
                                    <span class="item-count">
                                        <i class="fas fa-utensils"></i> <?php echo $store['item_count']; ?> items available
                                    </span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state" style="grid-column: 1 / -1;">
                            <div class="empty-state-icon">
                                <i class="fas fa-store"></i>
                            </div>
                            <h3>No stores available</h3>
                            <p>Check back later for available stores.</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
