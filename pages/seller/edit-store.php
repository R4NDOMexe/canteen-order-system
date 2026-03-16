<?php
require_once '../../includes/config.php';

// Check login and role
if (!isLoggedIn()) {
    redirect('../../index.php');
}

if (!hasRole('seller')) {
    redirect('../customer/menu.php');
}

$conn = getDBConnection();
$sellerId = $_SESSION['user_id'];

$storeId = intval($_GET['id'] ?? 0);
if ($storeId <= 0) {
    setFlashMessage('Invalid store specified.', 'error');
    redirect('stores.php');
}

// Fetch store and verify ownership
$stmt = $conn->prepare("SELECT * FROM stores WHERE id = ? AND seller_id = ?");
$stmt->bind_param('ii', $storeId, $sellerId);
$stmt->execute();
$store = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$store) {
    setFlashMessage('Store not found or access denied.', 'error');
    redirect('stores.php');
}

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['store_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if (empty($name)) {
        setFlashMessage('Store name is required.', 'error');
        redirect('edit-store.php?id=' . $storeId);
    }

    $upd = $conn->prepare("UPDATE stores SET store_name = ?, description = ?, is_active = ? WHERE id = ? AND seller_id = ?");
    $upd->bind_param('ssiii', $name, $description, $isActive, $storeId, $sellerId);
    $upd->execute();
    $upd->close();

    setFlashMessage('Store updated successfully.', 'success');
    redirect('stores.php');
}

$flash = getFlashMessage();

// Load categories
$categories = [];
$catRes = $conn->query("SELECT * FROM categories ORDER BY display_order");
if ($catRes) {
    while ($r = $catRes->fetch_assoc()) {
        $categories[$r['id']] = $r;
    }
}

// Load menu items for this store
$itemsStmt = $conn->prepare("SELECT mi.*, c.name as category_name FROM menu_items mi LEFT JOIN categories c ON mi.category_id = c.id WHERE mi.store_id = ? ORDER BY mi.name");
$itemsStmt->bind_param('i', $storeId);
$itemsStmt->execute();
$itemsResult = $itemsStmt->get_result();
$menuItems = [];
while ($row = $itemsResult->fetch_assoc()) {
    $menuItems[] = $row;
}
$itemsStmt->close();

// If user requested to edit a specific item, load it
$editingItem = null;
if (isset($_GET['edit_item'])) {
    $editId = intval($_GET['edit_item']);
    if ($editId > 0) {
        $itStmt = $conn->prepare("SELECT * FROM menu_items WHERE id = ? AND store_id = ?");
        $itStmt->bind_param('ii', $editId, $storeId);
        $itStmt->execute();
        $editingItem = $itStmt->get_result()->fetch_assoc();
        $itStmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Store - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <input type="checkbox" id="sidebar-toggle" class="hidden">
    <label for="sidebar-toggle" class="mobile-menu-toggle" aria-label="Toggle menu">
        <i class="fas fa-bars"></i>
    </label>
    <div class="dashboard">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo"><i class="fas fa-store"></i></div>
                <div><h2><?php echo APP_NAME; ?></h2><span>Seller Panel</span></div>
            </div>
            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">Main</div>
                    <a href="dashboard.php" class="nav-item"><i class="fas fa-home"></i> Dashboard</a>
                    <a href="orders.php" class="nav-item"><i class="fas fa-shopping-bag"></i> Orders</a>
                    <a href="previous-orders.php" class="nav-item"><i class="fas fa-history"></i> Previous Orders</a>
                </div>
                <div class="nav-section">
                    <div class="nav-section-title">Management</div>
                    <a href="stores.php" class="nav-item active"><i class="fas fa-store-alt"></i> My Stores</a>
                    <a href="sales.php" class="nav-item"><i class="fas fa-chart-line"></i> Sales Statistics</a>
                    <a href="history.php" class="nav-item"><i class="fas fa-receipt"></i> Receipt History</a>
                </div>
            </nav>
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?></div>
                    <div class="user-details"><h4><?php echo $_SESSION['full_name']; ?></h4><span>Seller</span></div>
                </div>
                <a href="../../logout.php" class="btn btn-outline w-full"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </aside>

        <main class="main-content">
            <?php if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?>">
                    <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo $flash['message']; ?>
                </div>
            <?php endif; ?>

            <div class="page-header">
                <h1><i class="fas fa-store"></i> Manage Store</h1>
                <a href="stores.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back</a>
            </div>

            <div class="card">
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="form-group">
                            <label>Store Name</label>
                            <input type="text" name="store_name" class="form-control" required value="<?php echo htmlspecialchars($store['store_name']); ?>">
                        </div>
                        <div class="form-group">
                            <label>Description (optional)</label>
                            <input type="text" name="description" class="form-control" value="<?php echo htmlspecialchars($store['description']); ?>">
                        </div>
                        <div class="form-group">
                            <label><input type="checkbox" name="is_active" <?php echo $store['is_active'] ? 'checked' : ''; ?>> Active</label>
                        </div>
                        <div style="display:flex;gap:12px;align-items:center;">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                            <a href="stores.php" class="btn btn-outline">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Items Management -->
            <div class="card" style="margin-top:20px;">
                <div class="card-header"><h3><i class="fas fa-utensils"></i> Manage Items</h3></div>
                <div class="card-body">
                    <!-- Add New Item Form -->
                    <div style="background:var(--gray-light);padding:16px;border-radius:8px;margin-bottom:24px;">
                        <h4 style="margin-top:0;"><i class="fas fa-plus-circle"></i> Add New Item</h4>
                        <form method="POST" action="add-item.php" enctype="multipart/form-data">
                            <input type="hidden" name="add_item" value="1">
                            <input type="hidden" name="store_id" value="<?php echo $storeId; ?>">

                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                                <div class="form-group">
                                    <label>Category <span style="color:red;">*</span></label>
                                    <select name="category_id" class="form-control" required>
                                        <option value="">Select category</option>
                                        <?php foreach ($categories as $cid => $cat): ?>
                                            <option value="<?php echo $cid; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Item Name <span style="color:red;">*</span></label>
                                    <input type="text" name="item_name" class="form-control" required placeholder="e.g., Burger">
                                </div>
                            </div>

                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                                <div class="form-group">
                                    <label>Price <span style="color:red;">*</span></label>
                                    <input type="number" step="0.01" name="price" class="form-control" required placeholder="0.00">
                                </div>
                                <div class="form-group">
                                    <label>Description</label>
                                    <input type="text" name="item_description" class="form-control" placeholder="Brief description">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Image (JPG, PNG, WEBP - Max 5MB)</label>
                                <input type="file" name="item_image" accept="image/jpeg,image/png,image/webp" class="form-control">
                            </div>

                            <button type="submit" class="btn btn-success"><i class="fas fa-plus"></i> Add Item</button>
                        </form>
                    </div>

                    <hr>

                    <?php if ($editingItem): ?>
                        <h4>Edit Item: <?php echo htmlspecialchars($editingItem['name']); ?></h4>
                        <div style="background:var(--gray-light);padding:16px;border-radius:8px;margin-bottom:24px;">
                        <form method="POST" action="edit-item.php" enctype="multipart/form-data">
                            <input type="hidden" name="update_item" value="1">
                            <input type="hidden" name="item_id" value="<?php echo $editingItem['id']; ?>">
                            <input type="hidden" name="store_id" value="<?php echo $storeId; ?>">

                            <div class="form-group">
                                <label>Category</label>
                                <select name="category_id" class="form-control" required>
                                    <option value="">Select category</option>
                                    <?php foreach ($categories as $cid => $cat): ?>
                                        <option value="<?php echo $cid; ?>" <?php echo ($editingItem['category_id'] == $cid) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Item Name</label>
                                <input type="text" name="item_name" class="form-control" required value="<?php echo htmlspecialchars($editingItem['name']); ?>">
                            </div>
                            <div class="form-group">
                                <label>Description</label>
                                <input type="text" name="item_description" class="form-control" value="<?php echo htmlspecialchars($editingItem['description']); ?>">
                            </div>
                            <div class="form-group">
                                <label>Price</label>
                                <input type="number" step="0.01" name="price" class="form-control" required value="<?php echo htmlspecialchars($editingItem['price']); ?>">
                            </div>
                            <div class="form-group">
                                <label><input type="checkbox" name="is_available" <?php echo $editingItem['is_available'] ? 'checked' : ''; ?>> Available</label>
                            </div>

                            <div class="form-group">
                                <label>Current Image</label>
                                <?php if (!empty($editingItem['image'])): ?>
                                    <div style="margin-bottom:10px;"><img src="../../<?php echo htmlspecialchars($editingItem['image']); ?>" alt="item" style="height:80px;border-radius:8px;border:1px solid var(--gray);"></div>
                                <?php else: ?>
                                    <div style="margin-bottom:10px;color:var(--gray-dark);">No image</div>
                                <?php endif; ?>
                                <label>Change Image</label>
                                <input type="file" name="item_image" accept="image/*" class="form-control">
                                <small style="color:var(--gray-dark);">Allowed types: JPG, PNG, WEBP. Max 5MB.</small>
                            </div>
                            <div style="display:flex;gap:12px;align-items:center;">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Item</button>
                                <a href="edit-store.php?id=<?php echo $storeId; ?>" class="btn btn-outline">Cancel</a>
                            </div>
                        </form>
                        </div>
                        <hr>
                    <?php endif; ?>

                    <?php if (count($menuItems) > 0): ?>
                        <div class="table-container">
                            <table class="data-table">
                                <thead><tr><th style="width:60px">Img</th><th>Name</th><th>Category</th><th>Price</th><th>Available</th><th>Actions</th></tr></thead>
                                <tbody>
                                    <?php foreach ($menuItems as $mi): ?>
                                        <tr>
                                            <td>
                                                <?php if (!empty($mi['image'])): ?>
                                                    <img src="../../<?php echo htmlspecialchars($mi['image']); ?>" style="height:48px;border-radius:6px;object-fit:cover;border:1px solid var(--gray);">
                                                <?php else: ?>
                                                    <div style="height:48px;width:48px;background:var(--gray-light);border-radius:6px;display:flex;align-items:center;justify-content:center;color:var(--gray-dark);">-</div>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($mi['name']); ?></td>
                                            <td><?php echo htmlspecialchars($mi['category_name'] ?? '-'); ?></td>
                                            <td><?php echo formatPrice($mi['price']); ?></td>
                                            <td><?php echo $mi['is_available'] ? 'Yes' : 'No'; ?></td>
                                            <td>
                                                <a href="edit-store.php?id=<?php echo $storeId; ?>&edit_item=<?php echo $mi['id']; ?>" class="btn btn-outline btn-sm"><i class="fas fa-edit"></i> Edit</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div style="background:var(--gray-light);padding:16px;border-radius:8px;text-align:center;">
                            <i class="fas fa-inbox" style="font-size:32px;color:var(--gray-dark);display:block;margin-bottom:12px;"></i>
                            <p><strong>No items found</strong> for this store yet.</p>
                            <p>Add your first item using the form above!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>

<?php $conn->close(); ?>
