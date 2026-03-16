<?php
/**
 * Login Page
 * Handles authentication for all user types including Master account
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

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        // Check for Master account first
        if ($username === MASTER_USERNAME) {
            if ($password === MASTER_PASSWORD) {
                // Master login successful
                $_SESSION['user_id'] = 0;
                $_SESSION['username'] = MASTER_USERNAME;
                $_SESSION['full_name'] = 'System Administrator';
                $_SESSION['role'] = 'master';
                
                setFlashMessage('Welcome, Master Administrator!', 'success');
                redirect('pages/master/dashboard.php');
            } else {
                $error = 'Invalid Master password.';
            }
        } else {
            // Regular user login - MySQLi version
            $conn = getDBConnection();
            $stmt = $conn->prepare("SELECT id, username, password, full_name, role, status FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);  // MySQLi style
            $stmt->execute();
            $result = $stmt->get_result();  // MySQLi style
            $user = $result->fetch_assoc();  // MySQLi style - NO arguments!
            
            if ($user) {
                if (password_verify($password, $user['password'])) {
                    // Check account status
                    if ($user['status'] === 'pending') {
                        $error = 'Your account is pending approval. Please wait for administrator confirmation.';
                    } elseif ($user['status'] === 'rejected') {
                        $error = 'Your registration has been rejected. Please contact support for more information.';
                    } elseif ($user['status'] === 'active' || $user['status'] === 'approved') {
                        // Login successful
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['full_name'] = $user['full_name'];
                        $_SESSION['role'] = $user['role'];
                        
                        // Initialize cart if customer
                        if ($user['role'] === 'student' || $user['role'] === 'teacher' || $user['role'] === 'customer') {
                            if (!isset($_SESSION['cart'])) {
                                $_SESSION['cart'] = [];
                            }
                            setFlashMessage('Welcome back, ' . $user['full_name'] . '!', 'success');
                            redirect('pages/customer/menu.php');
                        } elseif ($user['role'] === 'seller') {
                            setFlashMessage('Welcome back, ' . $user['full_name'] . '!', 'success');
                            redirect('pages/seller/dashboard.php');
                        }
                    } else {
                        $error = 'Account status unknown. Please contact support.';
                    }
                } else {
                    $error = 'Invalid password.';
                }
            } else {
                $error = 'User not found.';
            }
            
            $stmt->close();  // MySQLi - close statement
            $conn->close();  // MySQLi - close connection
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="login-container">
        <div class="university-logo">
            <img src="uploads/logos/university-logo.png" alt="Logo">
        </div>
        <div class="login-box">
            <div class="login-logo">
                <i class="fas fa-user"></i>
            </div>
            <h1><?php echo APP_NAME; ?></h1>
            <p>Order your favorite meals quickly and easily</p>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" class="form-control" 
                           placeholder="Enter your username" required autofocus>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" 
                           placeholder="Enter your password" required>
                </div>
                
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-sign-in-alt"></i>
                    Login
                </button>
            </form>
            
            <div class="mt-3 text-center">
                <hr style="border: none; border-top: 1px solid var(--gray-light); margin: 20px 0;">
                <p style="color: var(--gray-dark); font-size: 14px; margin-bottom: 10px;">
                    Don't have an account?
                </p>
                <a href="register.php" class="btn btn-secondary">
                    <i class="fas fa-user-plus"></i>
                    Create Account
                </a>
            </div>
            
            </div>
        </div>
    </div>
</body>
</html>