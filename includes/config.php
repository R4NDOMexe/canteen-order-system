<?php

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Application Constants
define('APP_NAME', 'Canteen Order System');
define('APP_VERSION', '1.0.0');
define('CURRENCY_SYMBOL', '₱');
define('RECEIPT_EXPIRY_HOURS', 48);

// Master Account Credentials
define('MASTER_USERNAME', 'MASTER');
define('MASTER_PASSWORD', 'PASSWORD');

// Database Configuration - MySQL
// For Railway deployment, use environment variables
// define('DB_HOST', 'localhost');
// define('DB_NAME', 'canteen_system');
// define('DB_USER', 'root');
// define('DB_PASS', '');  // Add your password if you have one

// Debug mode - set to true to see SQL errors
define('DEBUG_MODE', true);

// Database Connection using MySQLi
function getDBConnection() {
    // Use Railway environment variables if available, else fallback to constants
    $host = getenv("MYSQLHOST") ?: 'localhost';
    $port = getenv("MYSQLPORT") ?: 3306;
    $user = getenv("MYSQLUSER") ?: 'root';
    $pass = getenv("MYSQLPASSWORD") ?: '';
    $db   = getenv("MYSQLDATABASE") ?: 'canteen_system';
    
    $conn = new mysqli($host, $user, $pass, $db, $port);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // ensure legacy orders without explicit statuses are treated as pending
    // this will run once per connection and is idempotent
    $conn->query("UPDATE orders SET payment_status='pending' WHERE payment_status IS NULL OR payment_status = ''");
    $conn->query("UPDATE orders SET order_status='pending' WHERE order_status IS NULL OR order_status = ''");
    
    return $conn;
}

// Helper to check if column exists
function columnExists($table, $column) {
    $conn = getDBConnection();
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    $exists = $result->num_rows > 0;
    $conn->close();
    return $exists;
}

// Get actual column names from table
function getTableColumns($table) {
    $conn = getDBConnection();
    $columns = [];
    $result = $conn->query("SHOW COLUMNS FROM `$table`");
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    $conn->close();
    return $columns;
}

// Session Management Functions
function isLoggedIn() {
    return isset($_SESSION['user_id']) && $_SESSION['user_id'] !== null && $_SESSION['user_id'] !== '';
}
function isMaster() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'master';
}

function hasRole($role) {
    if (!isLoggedIn()) return false;
    
    if (isMaster()) return true;
    
    if (isset($_SESSION['role'])) {
        $userRole = $_SESSION['role'];
        
        // Handle different role names
        $customerRoles = ['customer', 'student', 'teacher', 'user'];
        if ($role === 'customer' && in_array($userRole, $customerRoles)) {
            return true;
        }
        
        return $userRole === $role;
    }
    
    return false;
}

function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getCurrentUsername() {
    return $_SESSION['username'] ?? null;
}

function getCurrentUserRole() {
    return $_SESSION['role'] ?? null;
}

// Redirection Helper
function redirect($url) {
    header("Location: $url");
    exit();
}

// Flash Messages
function setFlashMessage($message, $type = 'success') {
    $_SESSION['flash_message'] = [
        'message' => $message,
        'type' => $type
    ];
}

function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $flash;
    }
    return null;
}

// Formatting Helpers
function formatPrice($amount) {
    return CURRENCY_SYMBOL . number_format($amount, 2);
}

function formatDate($date, $format = 'M d, Y h:i A') {
    if (empty($date)) return 'N/A';
    return date($format, strtotime($date));
}

// Generate Unique Codes
function generateOrderCode() {
    // Attempt to create a sequential code based on the next auto_increment value
    // Format: AU0001, AU0002, etc. (hash prefix added when displaying)
    $conn = getDBConnection();
    $code = '';
    $result = $conn->query("SELECT AUTO_INCREMENT FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders'");
    if ($result && ($row = $result->fetch_assoc())) {
        $next = intval($row['AUTO_INCREMENT']);
        $code = 'AU' . str_pad($next, 4, '0', STR_PAD_LEFT);
    }
    if (empty($code)) {
        // fallback to random string if we couldn't determine the auto increment
        $code = 'AU' . strtoupper(substr(uniqid(), -6));
    }
    return $code;
}

function generateReceiptCode() {
    return 'RCP' . date('Ymd') . strtoupper(substr(uniqid(), -6));
}

// Security Helpers
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// File Upload Helper
function uploadFile($file, $uploadDir, $allowedTypes, $maxSize = 5242880) {
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return ['success' => false, 'error' => 'No file uploaded'];
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload error: ' . $file['error']];
    }
    
    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'error' => 'Invalid file type. Allowed: ' . implode(', ', $allowedTypes)];
    }
    
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'error' => 'File too large (max ' . ($maxSize / 1024 / 1024) . 'MB)'];
    }
    
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'path' => $filepath];
    }
    
    return ['success' => false, 'error' => 'Failed to move uploaded file'];
}

// FLEXIBLE Sales Statistics Functions
// These auto-detect your column names

function getTodaySales($sellerId = null, $storeIds = []) {
    $conn = getDBConnection();
    $today = date('Y-m-d');
    
    // Check available columns
    $columns = getTableColumns('orders');
    $hasPaymentStatus = in_array('payment_status', $columns);
    $hasTotalAmount = in_array('total_amount', $columns);
    $hasCreatedAt = in_array('created_at', $columns);
    $hasDate = in_array('date', $columns);
    $hasStoreId = in_array('store_id', $columns);
    
    // Build query based on available columns
    $dateColumn = $hasCreatedAt ? 'created_at' : ($hasDate ? 'date' : 'created_at');
    $amountColumn = $hasTotalAmount ? 'total_amount' : 'amount';
    
    $whereClause = "DATE($dateColumn) = ?";
    $params = [$today];
    $types = "s";
    
    if ($hasPaymentStatus) {
        $whereClause .= " AND payment_status = 'paid'";
    }
    
    if (!empty($storeIds) && $hasStoreId) {
        $placeholders = implode(',', array_fill(0, count($storeIds), '?'));
        $whereClause .= " AND store_id IN ($placeholders)";
        $types .= str_repeat('i', count($storeIds));
        $params = array_merge($params, $storeIds);
    }
    
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as orders, COALESCE(SUM($amountColumn), 0) as sales FROM orders WHERE $whereClause");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();
        $conn->close();
        
        return [
            'orders' => (int)$data['orders'],
            'sales' => (float)$data['sales']
        ];
    } catch (Exception $e) {
        if (DEBUG_MODE) {
            echo "Error in getTodaySales: " . $e->getMessage();
        }
        return ['orders' => 0, 'sales' => 0];
    }
}

function getMonthlySales($sellerId = null, $storeIds = []) {
    $conn = getDBConnection();
    $monthStart = date('Y-m-01');
    $monthEnd = date('Y-m-t');
    
    $columns = getTableColumns('orders');
    $hasPaymentStatus = in_array('payment_status', $columns);
    $hasTotalAmount = in_array('total_amount', $columns);
    $hasCreatedAt = in_array('created_at', $columns);
    $hasDate = in_array('date', $columns);
    $hasStoreId = in_array('store_id', $columns);
    
    $dateColumn = $hasCreatedAt ? 'created_at' : ($hasDate ? 'date' : 'created_at');
    $amountColumn = $hasTotalAmount ? 'total_amount' : 'amount';
    
    $whereClause = "DATE($dateColumn) BETWEEN ? AND ?";
    $params = [$monthStart, $monthEnd];
    $types = "ss";
    
    if ($hasPaymentStatus) {
        $whereClause .= " AND payment_status = 'paid'";
    }
    
    if (!empty($storeIds) && $hasStoreId) {
        $placeholders = implode(',', array_fill(0, count($storeIds), '?'));
        $whereClause .= " AND store_id IN ($placeholders)";
        $types .= str_repeat('i', count($storeIds));
        $params = array_merge($params, $storeIds);
    }
    
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as orders, COALESCE(SUM($amountColumn), 0) as sales FROM orders WHERE $whereClause");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();
        $conn->close();
        
        return [
            'orders' => (int)$data['orders'],
            'sales' => (float)$data['sales']
        ];
    } catch (Exception $e) {
        if (DEBUG_MODE) {
            echo "Error in getMonthlySales: " . $e->getMessage();
        }
        return ['orders' => 0, 'sales' => 0];
    }
}

function getYearlySales($sellerId = null, $storeIds = []) {
    $conn = getDBConnection();
    $yearStart = date('Y-01-01');
    $yearEnd = date('Y-12-31');
    
    $columns = getTableColumns('orders');
    $hasPaymentStatus = in_array('payment_status', $columns);
    $hasTotalAmount = in_array('total_amount', $columns);
    $hasCreatedAt = in_array('created_at', $columns);
    $hasDate = in_array('date', $columns);
    $hasStoreId = in_array('store_id', $columns);
    
    $dateColumn = $hasCreatedAt ? 'created_at' : ($hasDate ? 'date' : 'created_at');
    $amountColumn = $hasTotalAmount ? 'total_amount' : 'amount';
    
    $whereClause = "DATE($dateColumn) BETWEEN ? AND ?";
    $params = [$yearStart, $yearEnd];
    $types = "ss";
    
    if ($hasPaymentStatus) {
        $whereClause .= " AND payment_status = 'paid'";
    }
    
    if (!empty($storeIds) && $hasStoreId) {
        $placeholders = implode(',', array_fill(0, count($storeIds), '?'));
        $whereClause .= " AND store_id IN ($placeholders)";
        $types .= str_repeat('i', count($storeIds));
        $params = array_merge($params, $storeIds);
    }
    
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as orders, COALESCE(SUM($amountColumn), 0) as sales FROM orders WHERE $whereClause");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();
        $conn->close();
        
        return [
            'orders' => (int)$data['orders'],
            'sales' => (float)$data['sales']
        ];
    } catch (Exception $e) {
        if (DEBUG_MODE) {
            echo "Error in getYearlySales: " . $e->getMessage();
        }
        return ['orders' => 0, 'sales' => 0];
    }
}

// Get all sales statistics at once
function getAllSalesStats($sellerId = null, $storeIds = []) {
    return [
        'today' => getTodaySales($sellerId, $storeIds),
        'monthly' => getMonthlySales($sellerId, $storeIds),
        'yearly' => getYearlySales($sellerId, $storeIds)
    ];
}

// Get seller's store IDs - flexible version
function getSellerStoreIds($sellerId) {
    $conn = getDBConnection();
    
    // Check if stores table exists and has seller_id
    $columns = getTableColumns('stores');
    
    if (in_array('seller_id', $columns)) {
        $stmt = $conn->prepare("SELECT id FROM stores WHERE seller_id = ?");
        $stmt->bind_param("i", $sellerId);
    } elseif (in_array('user_id', $columns)) {
        $stmt = $conn->prepare("SELECT id FROM stores WHERE user_id = ?");
        $stmt->bind_param("i", $sellerId);
    } else {
        $conn->close();
        return [];
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $ids = [];
    while ($row = $result->fetch_assoc()) {
        $ids[] = $row['id'];
    }
    $stmt->close();
    $conn->close();
    return $ids;
}

// DEBUG FUNCTION - Use this to check your table structure
function debugTableStructure($table) {
    echo "<h3>Table: $table</h3>";
    echo "<pre>";
    print_r(getTableColumns($table));
    echo "</pre>";
}