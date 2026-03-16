<?php
require_once '../../includes/config.php';

// Check if user is logged in and is a seller
if (!isLoggedIn()) {
    redirect('../../index.php');
}

if (!hasRole('seller')) {
    redirect('../customer/menu.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
    $conn = getDBConnection();
    $sellerId = $_SESSION['user_id'];
    
    $storeId = intval($_POST['store_id'] ?? 0);
    $categoryId = intval($_POST['category_id'] ?? 0);
    $itemName = trim($_POST['item_name'] ?? '');
    $description = trim($_POST['item_description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    
    // Verify store belongs to seller
    $storeCheck = $conn->prepare("SELECT id FROM stores WHERE id = ? AND seller_id = ?");
    $storeCheck->bind_param("ii", $storeId, $sellerId);
    $storeCheck->execute();
    
    if ($storeCheck->get_result()->num_rows > 0 && !empty($itemName) && $price > 0 && $categoryId > 0) {
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

        $stmt = $conn->prepare("INSERT INTO menu_items (store_id, category_id, name, description, price, image, is_available) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $isAvailable = 1;
        $stmt->bind_param("iissdsi", $storeId, $categoryId, $itemName, $description, $price, $imagePath, $isAvailable);
        
        if ($stmt->execute()) {
            setFlashMessage('Food item "' . $itemName . '" added successfully!', 'success');
        } else {
            setFlashMessage('Failed to add food item.', 'error');
        }
        $stmt->close();
    } else {
        setFlashMessage('Please fill in all required fields.', 'error');
    }
    
    $storeCheck->close();
    $conn->close();
    redirect('edit-store.php?id=' . $storeId);
}

// If not POST request, redirect back
redirect('stores.php');
?>
