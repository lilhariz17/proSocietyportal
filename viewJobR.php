<?php
session_start();
require_once 'config.php';

// Redirect if not logged in as retiree
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'retiree') {
    header("Location: login.php");
    exit();
}

$error = '';
$jobs = [];

try {
    // Fetch all jobs with company names
    $sql = "SELECT j.*, c.company_name 
            FROM jobs j 
            JOIN companies c ON j.company_id = c.company_id 
            ORDER BY j.created_at DESC";
    $stmt = $pdo->query($sql);
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Jobs - Retirement Plan Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
    /* General layout reset */
    * { 
        margin: 0; 
        padding: 0; 
        box-sizing: border-box; 
        font-family: 'Poppins','Roboto',sans-serif; 
    }

    body { 
        display: flex; 
        min-height: 100vh; 
        background-color: #f8f9fa; 
        color: #333; 
    }

    /* Content area beside sidebar */
    .content { 
        flex: 1; 
        margin-left: 250px; 
    }
    @media (max-width: 768px) { 
        .content { margin-left: 0; } 
    }

    /* ✅ Dashboard header with sidebar gradient */
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

    /* Main content */
    .dashboard-content {
        max-width: 1000px;
        margin: 30px auto;
        padding: 0 20px;
    }
    .page-title {
        font-weight: 600;
        font-size: 1.4rem;
        color: #800000; /* sidebar maroon for title */
        margin-bottom: 20px;
        border-bottom: 2px solid #ff63a5; /* pink accent for consistency */
        padding-bottom: 10px;
    }

    /* Job List */
    .job-list { 
        list-style: none; 
        margin: 0; 
        padding: 0; 
    }
    .job-item {
        background: white;
        border-radius: 12px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        padding: 20px;
        margin-bottom: 20px;
        transition: transform .2s, box-shadow .2s;
    }
    .job-item:hover { 
        transform: translateY(-3px); 
        box-shadow: 0 8px 20px rgba(0,0,0,0.12); 
    }
    .job-title { 
        font-size: 1.2rem; 
        font-weight: 600; 
        color: #800000; /* sidebar maroon for consistency */ 
        margin-bottom: 8px; 
    }
    .company-name { 
        color: #988b91ff; /* pink accent for company name */ 
        font-weight: 500; 
        margin-bottom: 10px; 
    }

    /* Apply button with sidebar gradient */
    .btn-apply {
        display: inline-block;
        background: linear-gradient(135deg, #800000 0%, #ff63a5 100%);
        color: white;
        border: none;
        padding: 10px 18px;
        border-radius: 6px;
        font-weight: 500;
        cursor: pointer;
        transition: all .3s ease;
        text-decoration: none;
    }
    .btn-apply:hover {
        background: linear-gradient(135deg, #ff63a5 0%, #800000 100%);
        transform: translateY(-2px);
        box-shadow: 0 4px 10px rgba(255,99,165,0.3); /* subtle pink shadow */
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
        text-decoration: underline; 
        color: #ff63a5; /* hover pink accent */ 
    }
</style>

</head>
<body>
    <!-- Sidebar -->
    <?php include 'sidebarRetiree.php'; ?>

    <!-- Main Content -->
    <div class="content">
        <div class="dashboard-header">
            <h1>Browse Jobs - Pro Society Portal</h1>
        </div>

        <div class="dashboard-content">
            <h2 class="page-title">Available Opportunities</h2>

            <?php if ($error): ?>
                <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
            <?php elseif (empty($jobs)): ?>
                <p>No job postings available at the moment.</p>
            <?php else: ?>
                <ul class="job-list">
                    <?php foreach ($jobs as $job): ?>
                        <li class="job-item">
                            <div class="job-title"><?php echo htmlspecialchars($job['title']); ?></div>
                            <div class="company-name"><?php echo htmlspecialchars($job['company_name']); ?></div>
                            <div><?php echo htmlspecialchars($job['location']); ?> • <?php echo htmlspecialchars($job['job_type']); ?></div>
                            <div style="margin-top: 15px;">
                                <a href="apply_Job.php?id=<?php echo $job['job_id']; ?>" class="btn-apply">Apply Now</a>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
