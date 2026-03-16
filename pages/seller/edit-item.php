<?php
require_once '../../includes/config.php';

// Check if user is logged in and is a seller
if (!isLoggedIn()) {
    redirect('index.php');
}

if (!hasRole('seller')) {
    redirect('pages/customer/menu.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_item'])) {
    $conn = getDBConnection();
    $sellerId = $_SESSION['user_id'];
    
    $itemId = intval($_POST['item_id'] ?? 0);
    $storeId = intval($_POST['store_id'] ?? 0);
    $categoryId = intval($_POST['category_id'] ?? 0);
    $itemName = trim($_POST['item_name'] ?? '');
    $description = trim($_POST['item_description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $isAvailable = isset($_POST['is_available']) ? 1 : 0;
    
    // Verify item belongs to seller's store
    $verifyStmt = $conn->prepare("
        SELECT mi.id FROM menu_items mi
        JOIN stores s ON mi.store_id = s.id
        WHERE mi.id = ? AND s.seller_id = ?
    ");
    $verifyStmt->bind_param("ii", $itemId, $sellerId);
    $verifyStmt->execute();
    
    if ($verifyStmt->get_result()->num_rows > 0 && !empty($itemName) && $price > 0 && $categoryId > 0) {
        // Handle image upload if provided
        $imagePath = null;
        if (isset($_FILES['item_image']) && !empty($_FILES['item_image']['tmp_name'])) {
            $allowed = ['image/jpeg','image/png','image/webp'];
            $file = $_FILES['item_image'];
            if ($file['error'] === UPLOAD_ERR_OK && in_array($file['type'], $allowed) && $file['size'] <= 5 * 1024 * 1024) {
                $uploadDir = __DIR__ . '/../../uploads/menu_items/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = uniqid('mi_') . '.' . $ext;
                $dest = $uploadDir . $filename;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $imagePath = 'uploads/menu_items/' . $filename;
                }
            }
        }

        if ($imagePath !== null) {
            $stmt = $conn->prepare("UPDATE menu_items SET category_id = ?, name = ?, description = ?, price = ?, is_available = ?, image = ? WHERE id = ?");
            $stmt->bind_param("issdisi", $categoryId, $itemName, $description, $price, $isAvailable, $imagePath, $itemId);
        } else {
            $stmt = $conn->prepare("UPDATE menu_items SET category_id = ?, name = ?, description = ?, price = ?, is_available = ? WHERE id = ?");
            $stmt->bind_param("issdii", $categoryId, $itemName, $description, $price, $isAvailable, $itemId);
        }
        
        if ($stmt->execute()) {
            setFlashMessage('Food item "' . $itemName . '" updated successfully!', 'success');
        } else {
            setFlashMessage('Failed to update food item.', 'error');
        }
    } else {
        setFlashMessage('Please fill in all required fields.', 'error');
    }

    redirect('edit-store.php?id=' . $storeId);
}

// If not POST request, redirect back
redirect('stores.php');
?>
