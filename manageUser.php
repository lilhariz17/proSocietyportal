<?php
require_once 'config.php';

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect to login if not authenticated or not admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Initialize variables
$error = '';
$success = '';
$users = [];
$search_term = '';

// Handle search functionality
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['search'])) {
    $search_term = trim($_GET['search']);
}

// Handle user actions (delete, edit role)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_user'])) {
        $user_id_to_delete = $_POST['user_id'];
        
        try {
            $pdo->beginTransaction();

            // Delete related records first
            $pdo->prepare("DELETE FROM applications WHERE retiree_id = ?")->execute([$user_id_to_delete]);
            $pdo->prepare("DELETE FROM retiree_profiles WHERE retiree_id = ?")->execute([$user_id_to_delete]);
            $pdo->prepare("DELETE FROM employer_profiles WHERE employer_id = ?")->execute([$user_id_to_delete]);
            $pdo->prepare("DELETE FROM activity_logs WHERE user_id = ?")->execute([$user_id_to_delete]);
            $pdo->prepare("DELETE FROM notifications WHERE user_id = ?")->execute([$user_id_to_delete]);
            $pdo->prepare("DELETE FROM users WHERE user_id = ?")->execute([$user_id_to_delete]);

            $pdo->commit();

            // Log deletion
            $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, activity_type, details) VALUES (?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], 'delete_user', "Deleted user ID: $user_id_to_delete"]);

            $success = 'User deleted successfully.';
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Error deleting user: ' . $e->getMessage();
        }
    } elseif (isset($_POST['update_role'])) {
        $user_id_to_update = $_POST['user_id'];
        $new_role = $_POST['new_role'];
        
        try {
            $pdo->prepare("UPDATE users SET role = ? WHERE user_id = ?")->execute([$new_role, $user_id_to_update]);

            // Log update
            $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, activity_type, details) VALUES (?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], 'update_role', "Changed user ID: $user_id_to_update to role: $new_role"]);

            $success = 'User role updated successfully.';
        } catch (PDOException $e) {
            $error = 'Error updating user role: ' . $e->getMessage();
        }
    }
}

// Fetch users
try {
    if (!empty($search_term)) {
        $stmt = $pdo->prepare("SELECT * FROM users 
                              WHERE full_name LIKE :search OR email LIKE :search OR location LIKE :search 
                              ORDER BY created_at DESC");
        $stmt->execute([':search' => "%$search_term%"]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users ORDER BY created_at DESC");
        $stmt->execute();
    }
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Error fetching users: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Retirement Plan</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Reset + base styling */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            display: flex;
            min-height: 100vh;
            background-color: #fafafa;
            margin: 0;
            color: #333;
        }

        /* Sidebar */
        .sidebar {
            width: 250px;
            background: #2c3e50;
            min-height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            overflow-y: auto;
        }

        /* Content shifted by sidebar */
        .content {
            flex: 1;
            margin-left: 250px;
            padding: 0;
        }

        @media (max-width: 768px) {
            .content {
                margin-left: 0;
            }
        }

        /* Dashboard header with gradient */
        .dashboard-header {
            background: linear-gradient(135deg, #800000 0%, #ff63a5 100%);
            color: white;
            padding: 25px 30px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .dashboard-header h1 {
            font-weight: 600;
            font-size: 28px;
            margin: 0;
            letter-spacing: 0.5px;
        }

        /* Dashboard content container */
        .dashboard-content {
            max-width: 1200px; /* same as adminDash */
            margin: 30px auto;
            padding: 0 20px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

       h2 {
            color: #2c3e50;
            margin-bottom: 25px;
            font-weight: 600;
            border-bottom: 2px solid #800000;
            padding-bottom: 12px;
            font-size: 24px;
        }

        .section-box {
           max-width: 1200px; /* same as adminDash */
            margin: 30px auto;
            padding: 0 20px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }



        /* Messages */
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        /* Search box */
        .search-box {
            margin-bottom: 20px;
        }
        .search-form {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        .search-input {
            flex: 1;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 15px;
        }
        .search-btn {
            padding: 12px 20px;
            margin-left: 10px;
            background: linear-gradient(135deg, #800000 0%, #ff63a5 100%);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .search-btn:hover {
            opacity: 0.9;
        }
        .reset-btn {
            padding: 12px 20px;
            background: linear-gradient(135deg, #7f8c8d 0%, #95a5a6 100%);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            margin-left: 10px;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .reset-btn:hover {
            opacity: 0.9;
        }

        /* Table styling */
        .users-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .users-table th, .users-table td {
            padding: 14px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .users-table th {
            background-color: #f2f2f2;
        }

        /* Role badges */
        .user-role-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            color: #fff;
        }
        .badge-retiree { background-color: #3498db; }
        .badge-employer { background-color: #27ae60; }
        .badge-admin { background-color: #e67e22; }

        /* Action buttons container */
        .action-container {
            display: flex;
            align-items: center;
            gap: 10px; /* space between buttons */
        }

        /* Each form stays compact (so buttons can sit next to each other) */
        .action-form {
            display: inline-flex; /* keep form and its button inline */
            margin: 0; /* remove extra spacing */
        }

        .btn {
            padding: 8px 14px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s;
        }
        .btn-primary {
            background: linear-gradient(135deg, #800000 0%, #ff63a5 100%);
            color: white;
        }
        .btn-primary:hover {
            opacity: 0.9;
        }
        .btn-danger {
            background-color: #e74c3c;
            color: white;
        }
        .btn-danger:hover {
            background-color: #c0392b;
        }
        .current-user-text {
            font-style: italic;
            color: #7f8c8d;
        }
        .role-select {
            padding: 6px;
            border-radius: 4px;
            border: 1px solid #ccc;
        }
    </style>
</head>
<body>
    <?php 
    $current_page = 'manageUser.php';
    include 'sidebarAdmin.php'; 
    ?>
    
    <div class="content">
        <div class="dashboard-header">
            <h1>Manage Users - Pro Society Portal</h1>
        </div>
        
        <div class="dashboard-content">
            <h2>User Management</h2>
        </div>

        <div class="section-box">
            
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <!-- Search Box -->
            <div class="search-box">
                <form method="GET" class="search-form">
                    <input type="text" name="search" placeholder="Search users by name, email, or location..." 
                        class="search-input" value="<?php echo htmlspecialchars($search_term); ?>">
                    <button type="submit" class="search-btn"><i class="fas fa-search"></i> Search</button>
                    
                    <?php if (!empty($search_term)): ?>
                        <a href="manageUser.php" class="reset-btn"><i class="fas fa-sync-alt"></i> Reset</a>
                    <?php endif; ?>
                </form>
                <div>
                    Total Users: <span class="total-users"><?php echo count($users); ?></span>
                </div>
            </div>

            <?php if (!empty($users)): ?>
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Phone</th>
                            <th>Location</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="user-role-badge badge-<?php echo htmlspecialchars($user['role']); ?>">
                                        <?php echo ucfirst(htmlspecialchars($user['role'])); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                <td><?php echo htmlspecialchars($user['location']); ?></td>
                                <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <div class="action-container">
                                        <!-- Update Role -->
                                        <form method="POST" class="action-form">
                                            <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                            <select name="new_role" class="role-select">
                                                <option value="retiree" <?php echo $user['role'] === 'retiree' ? 'selected' : ''; ?>>Retiree</option>
                                                <option value="employer" <?php echo $user['role'] === 'employer' ? 'selected' : ''; ?>>Employer</option>
                                                <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                            </select>
                                            <button type="submit" name="update_role" class="btn btn-primary">
                                                <i class="fas fa-pen"></i> Update
                                            </button>
                                        </form>

                                        <!-- Delete -->
                                        <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                            <form method="POST" class="action-form" 
                                                  onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                                                <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                <button type="submit" name="delete_user" class="btn btn-danger">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="current-user-text">Current user</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="error">No users found.</div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
