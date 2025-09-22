<?php
require_once 'config.php';

// Redirect to login if not authenticated or not admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Get statistics for dashboard
try {
    // Count users by role
    $stmt = $pdo->prepare("SELECT role, COUNT(*) as count FROM users GROUP BY role");
    $stmt->execute();
    $user_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Count jobs
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM jobs");
    $stmt->execute();
    $job_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Count applications
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM applications");
    $stmt->execute();
    $application_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get recent activity logs
    $stmt = $pdo->prepare("SELECT al.*, u.full_name 
                          FROM activity_logs al 
                          JOIN users u ON al.user_id = u.user_id 
                          ORDER BY al.created_at DESC 
                          LIMIT 5");
    $stmt->execute();
    $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Determine current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Pro Society Portal</title>
    <link rel="stylesheet" href="style.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
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

        /* Dashboard Header with Bright Maroon → Candy Pink gradient */
        .dashboard-header {
            background: linear-gradient(135deg, #800000 0%, #ff63a5 100%);
            color: white;
            padding: 25px 30px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        /* Dashboard Content */
        .dashboard-content {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .welcome-message {
            margin-bottom: 35px;
            background: #fff;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        .welcome-message h2 {
            font-weight: 600;
            color: #444;
            margin-bottom: 10px;
            font-size: 22px;
        }

        .welcome-message p {
            color: #666;
            font-size: 15px;
        }

        /* Stats Container */
        .stats-container {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 40px;
            flex-wrap: nowrap;
        }

        .stat-card {
            flex: 1;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            padding: 25px;
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-top: 4px solid #ff69b4; /* hot pink accent */
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }

        .stat-number {
            font-size: 2.2rem;
            font-weight: 700;
            color: #d87093; /* dusty rose text */
            margin: 10px 0;
        }

        .stat-label {
            color: #555;
            font-size: 1rem;
            font-weight: 500;
        }

        /* Dashboard Sections */
        .dashboard-section {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            padding: 25px;
            margin-bottom: 30px;
        }

        .section-title {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
            color: #444;
            font-weight: 600;
            font-size: 18px;
        }

        .activity-list {
            list-style: none;
        }

        .activity-item {
            padding: 15px 10px;
            border-bottom: 1px solid #f1f1f1;
            transition: background-color 0.2s;
        }

        .activity-item:hover {
            background-color: #f9f9f9;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-time {
            color: #888;
            font-size: 0.9rem;
            margin-top: 5px;
        }

        /* User Role Badge */
        .user-role-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            margin-left: 10px;
            font-weight: 500;
            background: linear-gradient(90deg, #ff69b4 0%, #d08c94 100%);
            color: #fff;
        }
    </style>
</head>
<body>
    <!-- Include Sidebar -->
    <?php include 'sidebarAdmin.php'; ?>
    
    <!-- Main Content -->
    <div class="content">
        <div class="dashboard-header">
            <h1>Admin Dashboard - Pro Society Portal</h1>
        </div>
        
        <div class="dashboard-content">
            <div class="welcome-message">
                <h2>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h2>
                <p>You are logged in as: <span class="user-role-badge">Administrator</span></p>
            </div>
            
            <div class="stats-container">
                <?php 
                $role_counts = [];
                foreach ($user_counts as $count) {
                    $role_counts[$count['role']] = $count['count'];
                }
                ?>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $role_counts['admin'] ?? 0; ?></div>
                    <div class="stat-label">Administrators</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $role_counts['employer'] ?? 0; ?></div>
                    <div class="stat-label">Employers</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $role_counts['retiree'] ?? 0; ?></div>
                    <div class="stat-label">Retirees</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $job_count; ?></div>
                    <div class="stat-label">Job Postings</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $application_count; ?></div>
                    <div class="stat-label">Applications</div>
                </div>
            </div>
            
            <div class="dashboard-section">
                <h3 class="section-title">Recent Activity</h3>
                <?php if (!empty($recent_activities)): ?>
                    <ul class="activity-list">
                        <?php foreach ($recent_activities as $activity): ?>
                            <li class="activity-item">
                                <strong><?php echo htmlspecialchars($activity['full_name']); ?></strong> 
                                <?php echo htmlspecialchars($activity['activity_type']); ?>: 
                                <?php echo htmlspecialchars($activity['details']); ?>
                                <div class="activity-time">
                                    <?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>No recent activity found.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
