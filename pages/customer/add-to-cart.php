<?php
/**
 * Add to Cart Handler
 * Adds items to the shopping cart
 */
require_once '../../includes/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('../../index.php');
}

if (!hasRole('customer') && !isMaster()) {
    redirect('../seller/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $itemId = $_POST['item_id'] ?? '';
    $name = $_POST['name'] ?? '';
    $price = floatval($_POST['price'] ?? 0);
    $storeId = $_POST['store_id'] ?? '';
    $storeName = $_POST['store_name'] ?? '';
    
    if (empty($itemId) || empty($name) || $price <= 0) {
        setFlashMessage('Invalid item data.', 'error');
        redirect('menu.php');
    }
    
    // Initialize cart if not exists
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    // Check if item already in cart
    if (isset($_SESSION['cart'][$itemId])) {
        $_SESSION['cart'][$itemId]['quantity']++;
    } else {
        $_SESSION['cart'][$itemId] = [
            'id' => $itemId,
            'name' => $name,
            'price' => $price,
            'store_id' => $storeId,
            'store_name' => $storeName,
            'quantity' => 1
        ];
    }
    
    setFlashMessage('Item added to cart!', 'success');
    redirect('menu.php?store=' . $storeId);
}

redirect('menu.php');
