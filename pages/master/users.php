<?php
/**
 * Master Control Panel - User Management
 * View and manage all registered users
 */
require_once '../../includes/config.php';

// Check if user is logged in and is Master
if (!isLoggedIn() || !isMaster()) {
    redirect('../../index.php');
}

$conn = getDBConnection();

// Handle user actions - MySQLi version
if (isset($_GET['action']) && isset($_GET['id'])) {
    $userId = intval($_GET['id']);
    $action = $_GET['action'];
    
    if ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE users SET status = 'approved' WHERE id = ? AND role != 'master'");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
        setFlashMessage('User has been approved successfully.', 'success');
    } elseif ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE users SET status = 'rejected' WHERE id = ? AND role != 'master'");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
        setFlashMessage('User has been rejected.', 'success');
    } elseif ($action === 'deactivate') {
        $stmt = $conn->prepare("UPDATE users SET status = 'inactive' WHERE id = ? AND role != 'master'");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
        setFlashMessage('User has been deactivated.', 'success');
    } elseif ($action === 'activate') {
        $stmt = $conn->prepare("UPDATE users SET status = 'approved' WHERE id = ? AND role != 'master'");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
        setFlashMessage('User has been activated.', 'success');
    } elseif ($action === 'delete') {
        // Delete user and related data
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role != 'master'");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
        setFlashMessage('User has been deleted permanently.', 'success');
    }
    
    redirect('users.php');
}

// Get filter parameters
$statusFilter = $_GET['status'] ?? 'all';
$roleFilter = $_GET['role'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';

// Build query
$whereClause = "WHERE role != 'master'";
$params = [];

if ($statusFilter !== 'all') {
    $whereClause .= " AND status = ?";
    $params[] = $statusFilter;
}

if ($roleFilter !== 'all') {
    $whereClause .= " AND role = ?";
    $params[] = $roleFilter;
}

if (!empty($searchQuery)) {
    $whereClause .= " AND (full_name LIKE ? OR username LIKE ? OR email LIKE ?)";
    $searchParam = "%$searchQuery%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

// Get users - MySQLi version
$query = "SELECT * FROM users $whereClause ORDER BY created_at DESC";
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}
$stmt->close();

// Get statistics - MySQLi version
$result = $conn->query("SELECT 
    COUNT(*) as total,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
    COUNT(CASE WHEN status IN ('active', 'approved') THEN 1 END) as active,
    COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected,
    COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive
FROM users WHERE role != 'master'");
$stats = $result->fetch_assoc();

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                    <a href="users.php" class="nav-item active">
                        <i class="fas fa-users"></i>
                        User Management
                    </a>
                    <a href="pending-registrations.php" class="nav-item">
                        <i class="fas fa-user-clock"></i>
                        Pending Approvals
                        <?php if ($stats['pending'] > 0): ?>
                            <span class="badge badge-warning" style="margin-left: auto;"><?php echo $stats['pending']; ?></span>
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
                <h1><i class="fas fa-users"></i> User Management</h1>
            </div>
            
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total']; ?></h3>
                        <span>Total Users</span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['active']; ?></h3>
                        <span>Active Users</span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['pending']; ?></h3>
                        <span>Pending</span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon danger">
                        <i class="fas fa-ban"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['rejected'] + $stats['inactive']; ?></h3>
                        <span>Rejected/Inactive</span>
                    </div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="card">
                <div class="card-body">
                    <form method="GET" action="" style="display: flex; gap: 16px; flex-wrap: wrap; align-items: flex-end;">
                        <div class="form-group" style="flex: 1; min-width: 200px; margin-bottom: 0;">
                            <label>Search</label>
                            <input type="text" name="search" class="form-control" placeholder="Search by name, username, or email" 
                                   value="<?php echo htmlspecialchars($searchQuery); ?>">
                        </div>
                        <div class="form-group" style="min-width: 150px; margin-bottom: 0;">
                            <label>Status</label>
                            <select name="status" class="form-control">
                                <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="approved" <?php echo $statusFilter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="form-group" style="min-width: 150px; margin-bottom: 0;">
                            <label>Role</label>
                            <select name="role" class="form-control">
                                <option value="all" <?php echo $roleFilter === 'all' ? 'selected' : ''; ?>>All Roles</option>
                                <option value="student" <?php echo $roleFilter === 'student' ? 'selected' : ''; ?>>Student</option>
                                <option value="teacher" <?php echo $roleFilter === 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                                <option value="seller" <?php echo $roleFilter === 'seller' ? 'selected' : ''; ?>>Seller</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary" style="height: fit-content;">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                        <a href="users.php" class="btn btn-secondary" style="height: fit-content;">
                            <i class="fas fa-undo"></i> Reset
                        </a>
                    </form>
                </div>
            </div>
            
            <!-- Users Table -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-list"></i> All Users</h3>
                    <span style="color: var(--gray-dark); font-size: 14px;">
                        Showing <?php echo count($users); ?> user(s)
                    </span>
                </div>
                <div class="card-body">
                    <?php if (count($users) > 0): ?>
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>User</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Registered</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td>#<?php echo $user['id']; ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
                                                <br>
                                                <small style="color: var(--gray-dark);">@<?php echo htmlspecialchars($user['username']); ?></small>
                                                <br>
                                                <small style="color: var(--gray-dark);"><?php echo htmlspecialchars($user['email']); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo $user['role']; ?>">
                                                    <?php echo ucfirst($user['role']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($user['status'] === 'approved' || $user['status'] === 'active'): ?>
                                                    <span class="badge badge-approved">
                                                        <i class="fas fa-check"></i> Approved
                                                    </span>
                                                <?php elseif ($user['status'] === 'pending'): ?>
                                                    <span class="badge badge-pending">
                                                        <i class="fas fa-clock"></i> Pending
                                                    </span>
                                                <?php elseif ($user['status'] === 'rejected'): ?>
                                                    <span class="badge badge-rejected">
                                                        <i class="fas fa-times"></i> Rejected
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger">
                                                        <i class="fas fa-ban"></i> Inactive
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('M d, Y h:i A', strtotime($user['created_at'])); ?></td>
                                            <td>
                                                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                                    <a href="view-user.php?id=<?php echo $user['id']; ?>" class="btn btn-secondary btn-sm">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                    
                                                    <?php if ($user['status'] === 'pending'): ?>
                                                        <a href="?action=approve&id=<?php echo $user['id']; ?>" 
                                                           class="btn btn-success btn-sm">
                                                            <i class="fas fa-check"></i>
                                                        </a>
                                                        <a href="?action=reject&id=<?php echo $user['id']; ?>" 
                                                           class="btn btn-danger btn-sm">
                                                            <i class="fas fa-times"></i>
                                                        </a>
                                                    <?php elseif ($user['status'] === 'approved' || $user['status'] === 'active'): ?>
                                                        <a href="?action=deactivate&id=<?php echo $user['id']; ?>" 
                                                           class="btn btn-warning btn-sm">
                                                            <i class="fas fa-ban"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="?action=activate&id=<?php echo $user['id']; ?>" 
                                                           class="btn btn-success btn-sm">
                                                            <i class="fas fa-check"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <a href="?action=delete&id=<?php echo $user['id']; ?>" 
                                                       class="btn btn-danger btn-sm">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <h3>No users found</h3>
                            <p>No users match your search criteria.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
