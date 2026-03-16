<?php
/**
 * Registration Page
 * Handles new user registration with pending approval status
 */
require_once 'includes/config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if (isMaster()) {
        redirect('pages/master/dashboard.php');
    } elseif (hasRole('seller')) {
        redirect('pages/seller/dashboard.php');
    } else {
        redirect('pages/customer/menu.php');
    }
}

$error = '';
$success = '';

// Handle registration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $userType = $_POST['user_type'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($fullName) || empty($email) || empty($userType) || empty($username) || empty($password)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif (!in_array($userType, ['student', 'teacher', 'seller'])) {
        $error = 'Invalid user type selected.';
    } elseif (strtolower($username) === strtolower(MASTER_USERNAME)) {
        $error = 'This username is reserved. Please choose another.';
    } else {
        $conn = getDBConnection();
        
        // Check if username exists - MySQLi version
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->fetch_assoc()) {
            $error = 'Username already exists. Please choose another.';
        } else {
            $stmt->close();
            // Check if email exists - MySQLi version
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->fetch_assoc()) {
                $error = 'Email already registered. Please use another email.';
            } else {
                // Handle Photo ID upload
                $photoIdPath = null;
                if (isset($_FILES['photo_id']) && $_FILES['photo_id']['error'] === UPLOAD_ERR_OK) {
                    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                    $uploadResult = uploadFile($_FILES['photo_id'], 'uploads/photo_ids/', $allowedTypes, 5 * 1024 * 1024);
                    
                    if ($uploadResult['success']) {
                        $photoIdPath = $uploadResult['path'];
                    } else {
                        $error = 'Photo ID upload failed: ' . $uploadResult['error'];
                    }
                } else {
                    $error = 'Photo ID is required.';
                }
                
                // If no errors, proceed with registration
                if (empty($error) && $photoIdPath) {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    
                    $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, email, role, photo_id_path, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
                    $stmt->bind_param('ssssss', $username, $hashedPassword, $fullName, $email, $userType, $photoIdPath);
                    
                    if ($stmt->execute()) {
                        $success = 'Registration successful! Your account is pending approval. You will be notified once approved by the administrator.';
                    } else {
                        $error = 'Registration failed. Please try again.';
                        // Clean up uploaded file if registration failed
                        if ($photoIdPath && file_exists($photoIdPath)) {
                            unlink($photoIdPath);
                        }
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="login-container">
        <div class="university-logo">
            <img src="uploads/logos/university-logo.png" alt="Logo">
        </div>
        <div class="login-box" style="max-width: 500px;">
            <div class="login-logo">
                <i class="fas fa-user-plus"></i>
            </div>
            <h1>Create Account</h1>
            <p>Register to start ordering meals</p>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success; ?>
                </div>
                <div class="text-center mt-3">
                    <a href="index.php" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i>
                        Go to Login
                    </a>
                </div>
            <?php else: ?>
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="full_name">Full Name</label>
                        <input type="text" id="full_name" name="full_name" class="form-control" 
                               placeholder="Enter your full name" required autofocus
                               value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" class="form-control" 
                               placeholder="Enter your email address" required
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="user_type">User Type</label>
                        <select id="user_type" name="user_type" class="form-control" required>
                            <option value="">Select User Type</option>
                            <option value="student" <?php echo (isset($_POST['user_type']) && $_POST['user_type'] === 'student') ? 'selected' : ''; ?>>Student</option>
                            <option value="teacher" <?php echo (isset($_POST['user_type']) && $_POST['user_type'] === 'teacher') ? 'selected' : ''; ?>>Teacher</option>
                            <option value="seller" <?php echo (isset($_POST['user_type']) && $_POST['user_type'] === 'seller') ? 'selected' : ''; ?>>Seller</option>
                        </select>
                        <small class="form-text">
                            <i class="fas fa-info-circle"></i>
                            Students and Teachers will be registered as Customers. Sellers can manage their own stores.
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" class="form-control" 
                               placeholder="Choose a username" required
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" class="form-control" 
                               placeholder="Create a password (min 6 characters)" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                               placeholder="Confirm your password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="photo_id">Photo ID Upload</label>
                        <div class="file-upload-wrapper">
                            <input type="file" id="photo_id" name="photo_id" class="form-control" 
                                   accept="image/jpeg,image/png,image/gif" required>
                            <small class="form-text">
                                <i class="fas fa-info-circle"></i>
                                Upload a valid ID (JPG, PNG, or GIF, max 5MB). This is required for verification.
                            </small>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-user-plus"></i>
                        Register
                    </button>
                </form>
                
                <div class="mt-3 text-center">
                    <p style="color: var(--gray-dark); font-size: 14px;">
                        Already have an account? 
                        <a href="index.php" style="color: var(--primary-dark); text-decoration: none; font-weight: 600;">
                            <i class="fas fa-sign-in-alt"></i> Log In
                        </a>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
