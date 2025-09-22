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

$success = false;
$error = "";

// ✅ Fetch job details
$stmt = $pdo->prepare("
    SELECT j.title, c.company_name 
    FROM jobs j
    JOIN companies c ON j.company_id = c.company_id
    WHERE j.job_id = ?
");
$stmt->execute([$job_id]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);

// ✅ Check if already applied
if ($job_id > 0) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE retiree_id = ? AND job_id = ?");
    $stmt->execute([$retiree_id, $job_id]);
    $alreadyApplied = $stmt->fetchColumn();

    if ($alreadyApplied > 0) {
        $error = "You have already applied for this job.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO applications (retiree_id, job_id, status, applied_at) VALUES (?, ?, 'pending', NOW())");
            $stmt->execute([$retiree_id, $job_id]);
            $success = true;
        } catch (PDOException $e) {
            $error = "Error submitting application. Please try again.";
        }
    }
} else {
    $error = "Invalid job ID.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Apply Job</title>
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
        .message {
            padding: 20px;
            border-radius: 8px;
            font-weight: 500;
            margin-bottom: 20px;
        }
        .message.success { background: #e6ffed; color: #114b27; border: 1px solid #c3e6cb; }
        .message.error { background: #fff0f0; color: #7a2121; border: 1px solid #f5c6cb; }
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
    </style>
</head>
<body>
    <?php include 'sidebarRetiree.php'; ?>
    <div class="content">
        <div class="dashboard-header">Apply for Job - Pro Society Portal</div>
        <div class="dashboard-content">
            <?php if ($success): ?>
                <div class="message success">
                    Your application for <strong><?php echo htmlspecialchars($job['title']); ?></strong> 
                    at <strong><?php echo htmlspecialchars($job['company_name']); ?></strong> has been submitted.
                </div>
                <a href="viewApplicationsR.php" class="btn btn-primary">View Your Applications</a>
                <a href="searchJob.php" class="btn btn-secondary">Back to Job Search</a>
            <?php else: ?>
                <div class="message error"><?php echo htmlspecialchars($error); ?></div>
                <a href="job_details.php?id=<?php echo $job_id; ?>" class="btn btn-primary">Back to Job Details</a>
                <a href="searchJob.php" class="btn btn-secondary">Back to Job Search</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
