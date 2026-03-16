<?php
/**
 * Seller Stores Management Page
 * Manage seller's stores
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

// Handle form submission - MySQLi version
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $storeName = trim($_POST['store_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    if (!empty($storeName)) {
        $stmt = $conn->prepare("INSERT INTO stores (seller_id, store_name, description) VALUES (?, ?, ?)");
        $stmt->bind_param('iss', $sellerId, $storeName, $description);
        $stmt->execute();
        $stmt->close();
        setFlashMessage('Store created successfully!', 'success');
    } else {
        setFlashMessage('Store name is required.', 'error');
    }
    redirect('stores.php');
}

// Get seller's stores with item counts - MySQLi version
$stmt = $conn->prepare("
    SELECT s.*, 
           (SELECT COUNT(*) FROM menu_items WHERE store_id = s.id) as item_count
    FROM stores s
    WHERE s.seller_id = ?
    ORDER BY s.created_at DESC
");
$stmt->bind_param('i', $sellerId);
$stmt->execute();
$result = $stmt->get_result();
$stores = [];
while ($row = $result->fetch_assoc()) {
    $stores[] = $row;
}
$stmt->close();

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Stores - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .stores-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        .store-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
        }
        .store-card:hover {
            box-shadow: var(--shadow-lg);
        }
        .store-card-header {
            height: 120px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .store-card-header i {
            font-size: 48px;
            color: white;
        }
        .store-card-body {
            padding: 20px;
        }
        .store-card-body h3 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .store-card-body p {
            color: var(--gray-dark);
            font-size: 14px;
            margin-bottom: 16px;
        }
        .store-card-footer {
            padding: 16px 20px;
            border-top: 1px solid var(--gray);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .store-stats {
            display: flex;
            gap: 16px;
        }
        .store-stat {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 14px;
            color: var(--gray-dark);
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
                    <a href="orders.php" class="nav-item">
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
                    <a href="stores.php" class="nav-item active">
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
                <h1><i class="fas fa-store-alt"></i> My Stores</h1>
            </div>
            
            <!-- Add Store Form -->
            <div class="card" style="margin-bottom: 24px;">
                <div class="card-header">
                    <h3><i class="fas fa-plus"></i> Create New Store</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="" style="display: flex; gap: 16px; flex-wrap: wrap; align-items: flex-end;">
                        <div class="form-group" style="flex: 1; min-width: 200px; margin-bottom: 0;">
                            <label>Store Name</label>
                            <input type="text" name="store_name" class="form-control" placeholder="Enter store name" required>
                        </div>
                        <div class="form-group" style="flex: 2; min-width: 300px; margin-bottom: 0;">
                            <label>Description (Optional)</label>
                            <input type="text" name="description" class="form-control" placeholder="Enter store description">
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Create Store
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Stores Grid -->
            <h2 style="margin-bottom: 20px; font-size: 20px;"><i class="fas fa-list"></i> Your Stores</h2>
            
            <?php if (count($stores) > 0): ?>
                <div class="stores-grid">
                    <?php foreach ($stores as $store): ?>
                        <div class="store-card">
                            <div class="store-card-header">
                                <i class="fas fa-store"></i>
                            </div>
                            <div class="store-card-body">
                                <h3><?php echo htmlspecialchars($store['store_name']); ?></h3>
                                <p><?php echo htmlspecialchars($store['description'] ?: 'No description'); ?></p>
                            </div>
                            <div class="store-card-footer">
                                <div class="store-stats">
                                    <div class="store-stat">
                                        <i class="fas fa-utensils"></i>
                                        <?php echo $store['item_count']; ?> items
                                    </div>
                                    <div class="store-stat">
                                        <?php if ($store['is_active']): ?>
                                            <i class="fas fa-check-circle" style="color: var(--success);"></i> Active
                                        <?php else: ?>
                                            <i class="fas fa-ban" style="color: var(--danger);"></i> Inactive
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <a href="edit-store.php?id=<?php echo $store['id']; ?>" class="btn btn-secondary btn-sm">
                                    <i class="fas fa-edit"></i> Manage
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-body">
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="fas fa-store"></i>
                            </div>
                            <h3>No stores yet</h3>
                            <p>Create your first store to start selling.</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
