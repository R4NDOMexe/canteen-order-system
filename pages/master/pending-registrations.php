<?php
/**
 * Master Control Panel - Pending Registrations
 * Approve or deny pending user registrations
 */
require_once '../../includes/config.php';

// Check if user is logged in and is Master
if (!isLoggedIn() || !isMaster()) {
    redirect('../../index.php');
}

$conn = getDBConnection();

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = intval($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $reason = trim($_POST['reason'] ?? '');
    
    if ($userId > 0 && in_array($action, ['approve', 'reject'])) {
        if ($action === 'approve') {
            $stmt = $conn->prepare("UPDATE users SET status = 'approved' WHERE id = ? AND role != 'master'");
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->close();
            setFlashMessage('User has been approved successfully.', 'success');
        } else {
            $stmt = $conn->prepare("UPDATE users SET status = 'rejected' WHERE id = ? AND role != 'master'");
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->close();
            setFlashMessage('User has been rejected.', 'success');
        }
        redirect('pending-registrations.php');
    }
}

// Get all pending users with their details - MySQLi version
$stmt = $conn->prepare("
    SELECT u.*, 
           (SELECT COUNT(*) FROM stores WHERE seller_id = u.id) as store_count
    FROM users u 
    WHERE u.status = 'pending' AND u.role != 'master' 
    ORDER BY u.created_at DESC
");
$stmt->execute();
$result = $stmt->get_result();
$pendingUsers = [];
while ($row = $result->fetch_assoc()) {
    $pendingUsers[] = $row;
}
$stmt->close();

// Get pending count for sidebar
$pendingCount = count($pendingUsers);

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Registrations - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .user-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 24px;
        }
        .user-card-header {
            padding: 20px 24px;
            background: linear-gradient(135deg, var(--gray-light), var(--white));
            border-bottom: 1px solid var(--gray);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .user-info-header {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .user-avatar-large {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 700;
            color: var(--secondary);
        }
        .user-details h3 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 4px;
        }
        .user-details p {
            color: var(--gray-dark);
            font-size: 14px;
        }
        .user-card-body {
            padding: 24px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .info-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .info-item label {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--gray-dark);
        }
        .info-item span {
            font-size: 14px;
            color: var(--secondary);
        }
        .photo-id-preview {
            max-width: 300px;
            border-radius: var(--radius);
            border: 2px solid var(--gray);
            overflow: hidden;
        }
        .photo-id-preview img {
            width: 100%;
            height: auto;
            display: block;
        }
        .user-card-footer {
            padding: 16px 24px;
            border-top: 1px solid var(--gray);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            background: var(--gray-light);
        }
        .rejection-reason {
            margin-top: 16px;
            padding: 16px;
            background: #fff3cd;
            border-radius: var(--radius);
            display: none;
        }
        .rejection-reason.show {
            display: block;
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
                    <i class="fas fa-crown"></i>
                </div>
                <div>
                    <h2><?php echo APP_NAME; ?></h2>
                    <span>Master Panel</span>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">Main</div>
                    <a href="dashboard.php" class="nav-item">
                        <i class="fas fa-home"></i>
                        Dashboard
                    </a>
                    <a href="users.php" class="nav-item">
                        <i class="fas fa-users"></i>
                        User Management
                    </a>
                    <a href="pending-registrations.php" class="nav-item active">
                        <i class="fas fa-user-clock"></i>
                        Pending Approvals
                        <?php if ($pendingCount > 0): ?>
                            <span class="badge badge-warning" style="margin-left: auto;"><?php echo $pendingCount; ?></span>
                        <?php endif; ?>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">System</div>
                    <a href="system-stats.php" class="nav-item">
                        <i class="fas fa-chart-line"></i>
                        System Statistics
                    </a>
                    <a href="all-orders.php" class="nav-item">
                        <i class="fas fa-shopping-bag"></i>
                        All Orders
                    </a>
                </div>
            </nav>
            
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar" style="background: linear-gradient(135deg, #6f42c1, #5a32a3); color: white;">
                        <i class="fas fa-crown" style="font-size: 20px;"></i>
                    </div>
                    <div class="user-details">
                        <h4><?php echo $_SESSION['full_name']; ?></h4>
                        <span>Master Administrator</span>
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
                <h1><i class="fas fa-user-clock"></i> Pending Registrations</h1>
                <span class="badge badge-warning" style="font-size: 16px; padding: 10px 16px;">
                    <i class="fas fa-clock"></i> <?php echo $pendingCount; ?> Pending
                </span>
            </div>
            
            <?php if (count($pendingUsers) > 0): ?>
                <p style="color: var(--gray-dark); margin-bottom: 24px;">
                    <i class="fas fa-info-circle"></i>
                    Review and approve or reject pending user registrations. Students and Teachers are registered as Customers.
                </p>
                
                <?php foreach ($pendingUsers as $user): ?>
                    <div class="user-card">
                        <div class="user-card-header">
                            <div class="user-info-header">
                                <div class="user-avatar-large">
                                    <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                                </div>
                                <div class="user-details">
                                    <h3><?php echo htmlspecialchars($user['full_name']); ?></h3>
                                    <p>@<?php echo htmlspecialchars($user['username']); ?></p>
                                </div>
                            </div>
                            <span class="badge badge-<?php echo $user['role']; ?>" style="font-size: 14px; padding: 8px 16px;">
                                <?php echo ucfirst($user['role']); ?>
                            </span>
                        </div>
                        
                        <div class="user-card-body">
                            <div class="info-grid">
                                <div class="info-item">
                                    <label><i class="fas fa-envelope"></i> Email</label>
                                    <span><?php echo htmlspecialchars($user['email']); ?></span>
                                </div>
                                <div class="info-item">
                                    <label><i class="fas fa-user"></i> Username</label>
                                    <span><?php echo htmlspecialchars($user['username']); ?></span>
                                </div>
                                <div class="info-item">
                                    <label><i class="fas fa-calendar"></i> Registered On</label>
                                    <span><?php echo date('F d, Y h:i A', strtotime($user['created_at'])); ?></span>
                                </div>
                                <div class="info-item">
                                    <label><i class="fas fa-clock"></i> Time Since Registration</label>
                                    <span><?php 
                                        $diff = time() - strtotime($user['created_at']);
                                        $hours = floor($diff / 3600);
                                        $days = floor($hours / 24);
                                        if ($days > 0) {
                                            echo $days . ' day' . ($days > 1 ? 's' : '');
                                        } else {
                                            echo $hours . ' hour' . ($hours > 1 ? 's' : '');
                                        }
                                    ?></span>
                                </div>
                            </div>
                            
                            <?php if ($user['photo_id_path'] && file_exists('../../' . $user['photo_id_path'])): ?>
                                <div class="info-item">
                                    <label><i class="fas fa-id-card"></i> Photo ID</label>
                                    <div class="photo-id-preview">
                                        <img src="../../<?php echo $user['photo_id_path']; ?>" alt="Photo ID">
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST" action="" id="form-<?php echo $user['id']; ?>">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                
                                <div class="rejection-reason" id="reason-<?php echo $user['id']; ?>">
                                    <label><i class="fas fa-comment"></i> Rejection Reason (Optional)</label>
                                    <textarea name="reason" class="form-control" rows="3" placeholder="Enter reason for rejection..."></textarea>
                                </div>
                                
                                <div class="user-card-footer">
                                    <button type="submit" name="action" value="reject" class="btn btn-danger">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                    <button type="submit" name="action" value="approve" class="btn btn-success">
                                        <i class="fas fa-check"></i> Approve Registration
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="card">
                    <div class="card-body">
                        <div class="empty-state">
                            <div class="empty-state-icon" style="background: #d4edda;">
                                <i class="fas fa-check-circle" style="color: var(--success);"></i>
                            </div>
                            <h3>All Caught Up!</h3>
                            <p>There are no pending registrations to review.</p>
                            <a href="dashboard.php" class="btn btn-primary" style="margin-top: 16px;">
                                <i class="fas fa-arrow-left"></i> Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
