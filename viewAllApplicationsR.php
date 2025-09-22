<?php
session_start();
require_once 'config.php';

// Redirect if not logged in as retiree
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'retiree') {
    header("Location: login.php");
    exit;
}

$error = '';
$applications = [];

try {
    // Fetch all applications made by the retiree
    $sql = "SELECT a.*, j.title, c.company_name 
            FROM applications a
            JOIN jobs j ON a.job_id = j.job_id
            JOIN companies c ON j.company_id = c.company_id
            WHERE a.retiree_id = :user_id
            ORDER BY a.applied_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Applications - Retirement Plan</title>
    <style>
    /* General layout with sidebar */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Poppins', 'Roboto', sans-serif;
    }

    body {
        display: flex;
        min-height: 100vh;
        background-color: #f8f9fa;
        color: #333;
    }

    .content {
        flex: 1;
        margin-left: 250px; /* space for sidebar */
        padding: 0;
    }

    @media (max-width: 768px) {
        .content {
            margin-left: 0;
        }
    }

    /* Dashboard header with sidebar gradient */
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

    /* Main content container */
    .dashboard-content {
        max-width: 1000px;
        margin: 30px auto;
        padding: 0 20px;
    }

    .page-title {
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #ff63a5; /* pink accent border */
        color: #800000; /* sidebar maroon for title */
        font-weight: 600;
        font-size: 1.5rem;
    }

    /* Back link */
    .back-link {
        display: inline-block;
        margin-bottom: 20px;
        color: #800000; /* sidebar maroon */
        text-decoration: none;
        font-weight: 500;
    }

    .back-link:hover {
        color: #ff63a5; /* pink hover accent */
        text-decoration: underline;
    }

    /* Applications list */
    .application-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .application-item {
        background: white;
        padding: 20px;
        margin-bottom: 15px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .application-item:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 15px rgba(0,0,0,0.1);
    }

    .job-title {
        font-size: 1.2rem;
        font-weight: 600;
        margin-bottom: 5px;
        color: #800000; /* sidebar maroon for consistency */
    }

    .company-name {
        font-weight: 500;
        color: #8b8789ff; /* pink accent for company name */
        margin-bottom: 10px;
    }

    /* Status badges */
    .application-status {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.9rem;
        font-weight: 500;
        color: white;
    }

    .status-pending {
        background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
    }

    .status-approved,
    .status-accepted {
        background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
    }

    .status-rejected {
        background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
    }

    /* ✅ New interview status badge with purple gradient */
    .status-interview {
        background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
    }

    .activity-time {
        color: #7f8c8d;
        font-size: 0.9rem;
        margin-top: 5px;
    }
</style>

</head>
<body>
    <!-- Sidebar -->
    <?php include 'sidebarRetiree.php'; ?>

    <!-- Main Content -->
    <div class="content">
        <div class="dashboard-header">
            <h1>Your Job Applications - Pro Society Portal</h1>
        </div>

        <div class="dashboard-content">
            <h2 class="page-title">All Applications</h2>

            <?php if ($error): ?>
                <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
            <?php elseif (empty($applications)): ?>
                <p>You haven't applied to any jobs yet.</p>
            <?php else: ?>
                <ul class="application-list">
                    <?php foreach ($applications as $app): ?>
                        <li class="application-item">
                            <div class="job-title"><?php echo htmlspecialchars($app['title']); ?></div>
                            <div class="company-name"><?php echo htmlspecialchars($app['company_name']); ?></div>
                            <div>
                                Status:
                                <?php 
                                    $status = !empty($app['status']) ? strtolower($app['status']) : 'pending';
                                ?>
                                <span class="application-status status-<?php echo $status; ?>">
                                    <?php echo ucfirst(htmlspecialchars($status)); ?>
                                </span>
                            </div>
                            <div class="activity-time">
                                Applied on: <?php echo date('M j, Y', strtotime($app['applied_at'])); ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
