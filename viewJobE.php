<?php
session_start();
require_once 'config.php'; // make sure this sets up $pdo correctly

// Redirect if not logged in as employer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header("Location: login.php");
    exit;
}

$error = '';
$jobs = [];
$employer_id = $_SESSION['user_id'];

try {
    // Fetch all jobs posted by this employer
    // Menggunakan job_id sebagai ganti id (kemungkinan nama kolom yang benar)
    $sql = "SELECT job_id, title, location, job_type, created_at FROM jobs WHERE employer_id = :employer_id ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['employer_id' => $employer_id]);
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <title>Your Job Postings - Retirement Plan</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
    /* Layout for sidebar and content */
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', 'Roboto', sans-serif; }
    body { display: flex; min-height: 100vh; background-color: #f8f9fa; margin: 0; color: #333; }
    .content { flex: 1; margin-left: 250px; padding: 0; }
    @media (max-width: 768px) { .content { margin-left: 0; } }

    /* Dashboard header - updated to match admin/employer gradient */
    .dashboard-header {
            background: linear-gradient(135deg, #800000 0%, #ff63a5 100%);
            color: white;
            padding: 25px 30px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    .dashboard-header h1 { font-weight: 600; font-size: 28px; margin: 0; letter-spacing: 0.5px; }

    .dashboard-content { max-width: 1000px; margin: 30px auto; padding: 0 20px; }

    /* Back link - keep gray gradient */
    .back-link {
        display: inline-block;
        margin-bottom: 20px;
        padding: 12px 20px;
        background: linear-gradient(135deg, #7f8c8d 0%, #95a5a6 100%);
        color: white;
        text-decoration: none;
        border-radius: 6px;
        font-weight: 500;
        transition: all 0.3s ease;
        box-shadow: 0 2px 5px rgba(127, 140, 141, 0.3);
    }
    .back-link:hover {
        background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(127, 140, 141, 0.4);
    }

    /* Page title */
    .page-title {
        color: #2c3e50;
        margin-bottom: 25px;
        padding-bottom: 15px;
        border-bottom: 2px solid #eee;
        font-weight: 600;
        font-size: 24px;
    }

    /* Job list container */
    .job-list { list-style: none; padding: 0; margin: 0; }
    .job-item {
        background: white;
        padding: 25px;
        margin-bottom: 20px;
        border-radius: 12px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .job-item:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
    }

    .job-title { font-size: 1.3rem; font-weight: 600; margin-bottom: 8px; color: #2c3e50; }
    .job-meta { font-weight: 500; color: #7f8c8d; margin-bottom: 12px; font-size: 1rem; }

    .job-actions { margin-top: 15px; display: flex; gap: 15px; flex-wrap: wrap; }

    /* Buttons */
    .btn { padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: 500; transition: all 0.3s ease; text-decoration: none; display: inline-block; text-align: center; }

    /* View button - updated to admin gradient */
    .btn-view {
        background: linear-gradient(180deg, #800000 0%, #ff63a5 100%);
        color: white;
        box-shadow: 0 2px 5px rgba(128,0,0,0.3);
    }
    .btn-view:hover {
        background: linear-gradient(180deg, #ff63a5 0%, #800000 100%);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(128,0,0,0.4);
    }

    /* Edit button - updated to match admin gradient */
    .btn-edit {
        background: linear-gradient(180deg, #800000 0%, #ff63a5 100%);
        color: white;
        box-shadow: 0 2px 5px rgba(128,0,0,0.3);
    }
    .btn-edit:hover {
        background: linear-gradient(180deg, #ff63a5 0%, #800000 100%);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(128,0,0,0.4);
    }

    /* Error message */
    .error-message {
        color: #e74c3c;
        background-color: #fde8e8;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 25px;
        border-left: 4px solid #e74c3c;
        font-weight: 500;
    }

    /* Application count badge - updated to admin gradient */
    .application-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 30px;
        height: 30px;
        background: linear-gradient(180deg, #3498db 0%, #2980b9 100%);
        color: white;
        border-radius: 50%;
        font-size: 0.9rem;
        font-weight: bold;
        margin-left: 10px;
        box-shadow: 0 2px 5px rgba(128,0,0,0.3);
    }

    /* No jobs container */
    .no-jobs {
        background: white;
        border-radius: 12px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        padding: 40px;
        text-align: center;
        color: #7f8c8d;
        font-size: 1.1rem;
    }
    .no-jobs a {
        display: inline-block;
        margin-top: 20px;
        padding: 12px 25px;
        background: linear-gradient(180deg, #800000 0%, #ff63a5 100%);
        color: white;
        text-decoration: none;
        border-radius: 6px;
        font-weight: 500;
        transition: all 0.3s ease;
    }
    .no-jobs a:hover {
        background: linear-gradient(180deg, #ff63a5 0%, #800000 100%);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(128,0,0,0.4);
    }
</style>

</head>
<body>
    <!-- Include Sidebar -->
    <?php include 'sidebarEmployer.php'; ?>
    
    <!-- Main Content -->
    <div class="content">
        <div class="dashboard-header">
            <h1>Your Job Postings - Pro Society Portal</h1>
        </div>
        
        <div class="dashboard-content">
            <h2 class="page-title">All Your Job Postings</h2>

            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php elseif (empty($jobs)): ?>
                <div class="no-jobs">
                    <p>You haven't posted any jobs yet.</p>
                    <a href="postJob.php">Post Your First Job</a>
                </div>
            <?php else: ?>
                <ul class="job-list">
                    <?php foreach ($jobs as $job): 
                        // Pastikan job_id ada sebelum digunakan
                        $job_id = isset($job['job_id']) ? $job['job_id'] : (isset($job['id']) ? $job['id'] : null);
                        
                        if (!$job_id) {
                            continue; // Skip jika tidak ada ID yang valid
                        }
                        
                        // Count applications for this job dynamically
                        try {
                            $stmtApps = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE job_id = :job_id");
                            $stmtApps->execute(['job_id' => $job_id]);
                            $application_count = $stmtApps->fetchColumn();
                        } catch (PDOException $e) {
                            $application_count = 0;
                        }
                    ?>
                        <li class="job-item">
                            <div class="job-title"><?php echo htmlspecialchars($job['title']); ?></div>
                            <div class="job-meta">
                                <?php echo htmlspecialchars($job['location']); ?> • <?php echo htmlspecialchars($job['job_type']); ?>
                            </div>
                            
                            <div class="job-meta">
                                Applications: <span class="application-count"><?php echo $application_count; ?></span>
                            </div>

                            <div class="job-actions">
                                <a href="viewApplicationsE.php?job_id=<?php echo $job_id; ?>"><button class="btn btn-view">View Applications</button></a>
                                <a href="editJobE.php?job_id=<?php echo $job_id; ?>"><button class="btn btn-edit">Edit Job</button></a>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>