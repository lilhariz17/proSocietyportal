<?php
session_start();
require_once 'config.php';

// ✅ Ensure retiree is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'retiree') {
    header("Location: login.php");
    exit();
}

$retiree_id = $_SESSION['user_id'];
$job_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// ✅ Fetch job details with application check
$stmt = $pdo->prepare("
    SELECT j.*, c.company_name,
           (SELECT COUNT(*) FROM applications a WHERE a.retiree_id = ? AND a.job_id = j.job_id) AS alreadyApplied
    FROM jobs j
    JOIN companies c ON j.company_id = c.company_id
    WHERE j.job_id = ?
");
$stmt->execute([$retiree_id, $job_id]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) {
    echo "Job not found.";
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Job Details</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            display: flex;
            min-height: 100vh;
            margin: 0;
            font-family: 'Poppins', sans-serif;
            background: #f8f9fa;
            color: #333;
        }
        .content {
            flex: 1;
            margin-left: 250px;
            padding: 0;
        }
        .dashboard-header {
            background: linear-gradient(135deg, #800000 0%, #ff63a5 100%);
            color: #fff;
            padding: 25px 30px;
            font-size: 24px;
            font-weight: 600;
        }
        .dashboard-content {
            max-width: 900px;
            margin: 30px auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            padding: 25px;
        }
        h2 { margin-top: 0; color: #2c3e50; }
        p { margin-bottom: 10px; }
        .btn {
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            display: inline-block;
            transition: 0.3s;
            margin-right: 10px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #800000 0%, #ff63a5 100%);
            color: #fff;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #800000 0%, #ff63a5 100%);
        }
        .btn-secondary {
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
            color: #fff;
        }
        .btn-secondary:hover {
            background: linear-gradient(135deg, #7f8c8d, #95a5a6);
        }
        .btn-disabled {
            background: #ccc;
            color: #666;
            cursor: not-allowed;
            pointer-events: none;
        }
    </style>
</head>
<body>
    <?php include 'sidebarRetiree.php'; ?>
    <div class="content">
        <div class="dashboard-header">Job Details - Pro Society Portal</div>
        <div class="dashboard-content">
            <h2><?php echo htmlspecialchars($job['title']); ?></h2>
            <p><strong>Company:</strong> <?php echo htmlspecialchars($job['company_name']); ?></p>
            <p><strong>Location:</strong> <?php echo htmlspecialchars($job['location']); ?></p>
            <p><strong>Type:</strong> <?php echo htmlspecialchars($job['job_type']); ?></p>
            <p><strong>Description:</strong><br><?php echo nl2br(htmlspecialchars($job['description'])); ?></p>

            <?php if ($job['alreadyApplied'] > 0): ?>
                <span class="btn btn-disabled">Already Applied</span>
            <?php else: ?>
                <a href="apply_job.php?id=<?php echo $job['job_id']; ?>" class="btn btn-primary">Apply Now</a>
            <?php endif; ?>
            <a href="searchJob.php" class="btn btn-secondary">Back to Job Search</a>
        </div>
    </div>
</body>
</html>
