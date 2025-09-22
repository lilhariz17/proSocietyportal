<?php
require_once 'config.php';

// Redirect to login if not authenticated or not employer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';
$jobs = [];
$search = '';

// Get employer's company information
try {
    $stmt = $pdo->prepare("
        SELECT c.*, ep.job_title 
        FROM employer_profiles ep 
        JOIN companies c ON ep.company_id = c.company_id 
        WHERE ep.employer_id = ?
    ");
    $stmt->execute([$user_id]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$company) {
        $error = 'Company information not found. Please contact administrator.';
    }
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// Handle search
if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
}

// Handle job actions (delete, edit status)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_job'])) {
        $job_id_to_delete = $_POST['job_id'];
        
        try {
            $stmt = $pdo->prepare("SELECT * FROM jobs WHERE job_id = ? AND employer_id = ?");
            $stmt->execute([$job_id_to_delete, $user_id]);
            $job = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$job) {
                $error = 'Job not found or you do not have permission to delete it.';
            } else {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("DELETE FROM applications WHERE job_id = ?");
                $stmt->execute([$job_id_to_delete]);
                $stmt = $pdo->prepare("DELETE FROM jobs WHERE job_id = ?");
                $stmt->execute([$job_id_to_delete]);
                $pdo->commit();
                
                $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, activity_type, details) VALUES (?, ?, ?)");
                $stmt->execute([$user_id, 'delete_job', "Deleted job: " . $job['title']]);
                
                $success = 'Job deleted successfully.';
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Error deleting job: ' . $e->getMessage();
        }
    } elseif (isset($_POST['update_status'])) {
        $job_id_to_update = $_POST['job_id'];
        $new_status = $_POST['new_status'];
        
        try {
            $stmt = $pdo->prepare("SELECT * FROM jobs WHERE job_id = ? AND employer_id = ?");
            $stmt->execute([$job_id_to_update, $user_id]);
            $job = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$job) {
                $error = 'Job not found or you do not have permission to update it.';
            } else {
                $stmt = $pdo->prepare("UPDATE jobs SET status = ? WHERE job_id = ?");
                $stmt->execute([$new_status, $job_id_to_update]);
                
                $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, activity_type, details) VALUES (?, ?, ?)");
                $stmt->execute([$user_id, 'update_job_status', "Updated job status to $new_status: " . $job['title']]);
                
                $success = 'Job status updated successfully.';
            }
        } catch (PDOException $e) {
            $error = 'Error updating job status: ' . $e->getMessage();
        }
    }
}

// =========================
// Fetch employer's jobs
// =========================
try {
    // Use named placeholders for all parameters to avoid HY093 error
    $query = "SELECT 
                j.*, 
                COUNT(a.application_id) as application_count
              FROM jobs j
              LEFT JOIN applications a ON j.job_id = a.job_id
              WHERE j.employer_id = :employer_id";
    
    if (!empty($search)) {
        $query .= " AND (j.title LIKE :search OR j.location LIKE :search OR j.job_type LIKE :search)";
    }
    
    $query .= " GROUP BY j.job_id ORDER BY j.created_at DESC";
    
    $stmt = $pdo->prepare($query);
    
    // Bind parameters
    $stmt->bindValue(':employer_id', $user_id, PDO::PARAM_INT);
    if (!empty($search)) {
        $stmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
    }
    
    $stmt->execute();
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Error fetching jobs: ' . $e->getMessage();
}

// =========================
// Dashboard statistics
// =========================
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_jobs FROM jobs WHERE employer_id = ?");
    $stmt->execute([$user_id]);
    $total_jobs = $stmt->fetch(PDO::FETCH_ASSOC)['total_jobs'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as active_jobs FROM jobs WHERE employer_id = ? AND status = 'approved'");
    $stmt->execute([$user_id]);
    $active_jobs = $stmt->fetch(PDO::FETCH_ASSOC)['active_jobs'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as pending_jobs FROM jobs WHERE employer_id = ? AND status = 'pending'");
    $stmt->execute([$user_id]);
    $pending_jobs = $stmt->fetch(PDO::FETCH_ASSOC)['pending_jobs'];
    
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT a.job_id) as jobs_with_applications 
                          FROM applications a 
                          JOIN jobs j ON a.job_id = j.job_id 
                          WHERE j.employer_id = ?");
    $stmt->execute([$user_id]);
    $jobs_with_applications = $stmt->fetch(PDO::FETCH_ASSOC)['jobs_with_applications'];
} catch (PDOException $e) {
    // Silently fail for stats
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Jobs - Retirement Plan</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        /* =========================
        Full CSS (copied from your original) 
        with btn-danger red and action-buttons fixed
        ========================= */
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Poppins','Roboto',sans-serif; }
        body { display:flex; min-height:100vh; background-color:#f8f9fa; color:#333; margin:0; }

        .sidebar-employer { width:250px; background-color:#1a4b8c; color:white; height:100vh; position:fixed; overflow-y:auto; left:0; top:0; z-index:1000; display:flex; flex-direction:column; }
        .logo-container { padding:20px; text-align:center; border-bottom:1px solid #2d5c9e; }
        .logo { font-weight:600; font-size:20px; color:white; letter-spacing:0.5px; }
        .menu-section { margin-top:20px; flex:1; }
        .menu-title { padding:10px 20px; font-size:14px; font-weight:600; color:#a0aec0; text-transform:uppercase; letter-spacing:1px; }
        .menu-items { list-style-type:none; }
        .menu-items li { padding:12px 20px 12px 30px; transition:all 0.3s; cursor:pointer; }
        .menu-items li:hover { background-color:#2d5c9e; }
        .menu-items li a { color:white; text-decoration:none; display:block; width:100%; font-weight:500; font-size:15px; }
        .menu-items li.active { background-color:#2d5c9e; border-left:4px solid #4ecdc4; }
        .menu-items li.active a { font-weight:600; }
        .logout-container { margin-top:auto; padding:20px; border-top:1px solid #2d5c9e; }
        .logout-btn { display:block; width:100%; padding:12px; background-color:#e53e3e; color:white; text-align:center; text-decoration:none; border-radius:6px; font-weight:500; transition:all 0.3s ease; }
        .logout-btn:hover { background-color:#c53030; transform:translateY(-2px); }

        .content { flex:1; margin-left:250px; padding:0; }
        .dashboard-header { background:linear-gradient(135deg,#800000 0%,#ff63a5 100%); color:white; padding:25px 30px; box-shadow:0 4px 12px rgba(0,0,0,0.1); }
        .dashboard-content { max-width:1200px; margin:20px auto; padding:20px; }
        .company-info { background-color:white; border-radius:8px; box-shadow:0 2px 10px rgba(0,0,0,0.1); padding:20px; margin-bottom:20px; border-left:4px solid #800000; }
        .stats-container { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:20px; margin-bottom:30px; }
        .stat-card { background-color:white; border-radius:8px; box-shadow:0 2px 10px rgba(0,0,0,0.1); padding:20px; text-align:center; }
        .stat-number { font-size:2.5rem; font-weight:700; background:linear-gradient(180deg,#800000 0%,#ff63a5 100%); -webkit-background-clip:text; -webkit-text-fill-color:transparent; margin:15px 0; text-shadow:1px 1px 2px rgba(0,0,0,0.1); }
        .stat-label { color:#7f8c8d; font-size:1rem; font-weight:500; }

        .search-box { margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px; }
        .search-form { display:flex; flex:1; max-width:500px; }
        .search-input { padding:10px; border:1px solid #ddd; border-radius:4px 0 0 4px; flex:1; }
        .search-btn { padding:10px 15px; background:linear-gradient(135deg,#800000 0%,#ff63a5 100%); color:white; border:none; border-radius:0 4px 4px 0; cursor:pointer; }

        .jobs-table { width:100%; border-collapse:collapse; margin-top:20px; background-color:white; box-shadow:0 2px 10px rgba(0,0,0,0.1); border-radius:8px; overflow:hidden; }
        .jobs-table th, .jobs-table td { padding:12px 15px; text-align:left; border-bottom:1px solid #eee; }
        .jobs-table th { background-color:#f5f7fa; font-weight:600; color:#2c3e50; position:sticky; top:0; }
        .jobs-table tr:hover { background-color:#f9f9f9; }

        /* Buttons */
        .btn-success, .btn-info { background:linear-gradient(135deg,#800000 0%,#ff63a5 100%); color:white; padding:10px 15px; border-radius:6px; font-weight:500; text-decoration:none; display:inline-block; transition:all 0.3s ease; }
        .btn-success:hover, .btn-info:hover { background:linear-gradient(135deg,#ff63a5 0%,#800000 100%); }

        /* Delete button (red) */
        .btn-danger { background:linear-gradient(135deg,#e74c3c 0%,#c0392b 100%); color:white; padding:10px 15px; border-radius:6px; font-weight:500; text-decoration:none; display:inline-block; transition:all 0.3s ease; border:none; cursor:pointer; }
        .btn-danger:hover { background:linear-gradient(135deg,#c0392b 0%,#e74c3c 100%); }

        /* Action buttons flex row */
        .action-buttons { display:flex; gap:10px; justify-content:flex-start; }
        .action-buttons .btn-success, .action-buttons .btn-info { order:1; }
        .action-buttons .btn-danger { order:2; }

        /* =========================
        Warning / Clear Search Button
        ========================= */
        .btn-warning {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%); /* Orange gradient */
            color: white;
            padding: 10px 15px;
            border-radius: 6px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-warning:hover {
            background: linear-gradient(135deg, #e67e22 0%, #f39c12 100%);
            transform: translateY(-2px);
        }

        .job-status { display:inline-block; padding:3px 8px; border-radius:4px; font-size:0.8rem; }
        .status-pending { background-color:#f39c12; color:white; }
        .status-approved { background-color:#2ecc71; color:white; }
        .status-rejected { background-color:#e74c3c; color:white; }
        .status-closed { background-color:#7f8c8d; color:white; }

        .application-count { display:inline-flex; align-items:center; justify-content:center; width:25px; height:25px; background-color:#3498db; color:white; border-radius:50%; font-size:0.8rem; font-weight:bold; }

        @media (max-width:1200px){.jobs-table{display:block; overflow-x:auto;}}
        @media (max-width:768px){.sidebar-employer{width:100%; height:auto; position:relative;} .content{margin-left:0;} .logout-container{position:static; margin-top:20px;}}
    </style>
</head>
<body>
    <?php include 'sidebarEmployer.php'; ?>

    <div class="content">
        <div class="dashboard-header">
            <h1>Manage Your Jobs - Pro Society Portal</h1>
        </div>

        <div class="dashboard-content">
            <?php if ($error): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            <?php if ($success): ?><div class="success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

            <?php if ($company): ?>
                <div class="company-info">
                    <h3>Your Company: <?php echo htmlspecialchars($company['company_name']); ?></h3>
                    <p><strong>Industry:</strong> <?php echo htmlspecialchars($company['industry']); ?> • 
                       <strong>Location:</strong> <?php echo htmlspecialchars($company['location']); ?></p>
                </div>
            <?php endif; ?>

            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_jobs ?? 0; ?></div>
                    <div class="stat-label">Total Jobs Posted</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $active_jobs ?? 0; ?></div>
                    <div class="stat-label">Active Jobs</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $pending_jobs ?? 0; ?></div>
                    <div class="stat-label">Pending Approval</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $jobs_with_applications ?? 0; ?></div>
                    <div class="stat-label">Jobs with Applications</div>
                </div>
            </div>

            <div class="search-box">
                <form method="GET" class="search-form">
                    <input type="text" name="search" placeholder="Search your jobs by title, location, or type..." class="search-input" value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="search-btn">Search</button>
                </form>
                <div>
                    <?php if (!empty($search)): ?>
                        <a href="manageJobE.php" class="btn btn-warning">Clear Search</a>
                    <?php endif; ?>
                    <a href="postJob.php" class="btn btn-success">Post New Job</a>
                </div>
            </div>

            <?php if (!empty($jobs)): ?>
                <table class="jobs-table">
                    <thead>
                        <tr>
                            <th>Job Title</th>
                            <th>Type</th>
                            <th>Location</th>
                            <th>Applications</th>
                            <th>Status</th>
                            <th>Posted</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($jobs as $job): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($job['title']); ?></strong>
                                    <div class="view-applications">
                                        <a href="viewApplicationsE.php?job_id=<?php echo $job['job_id']; ?>" class="btn btn-info">
                                            View Applications (<?php echo $job['application_count']; ?>)
                                        </a>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($job['job_type']); ?></td>
                                <td><?php echo htmlspecialchars($job['location']); ?></td>
                                <td><span class="application-count"><?php echo $job['application_count']; ?></span></td>
                                <td>
                                    <?php 
                                    $status = $job['status'] ?? 'pending';
                                    $status_class = 'status-' . $status;
                                    ?>
                                    <span class="job-status <?php echo $status_class; ?>">
                                        <?php echo ucfirst($status); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($job['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($status === 'approved'): ?>
                                            <form method="POST" class="status-form">
                                                <input type="hidden" name="job_id" value="<?php echo $job['job_id']; ?>">
                                                <select name="new_status" class="status-select">
                                                    <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>Active</option>
                                                    <option value="closed" <?php echo $status === 'closed' ? 'selected' : ''; ?>>Close Job</option>
                                                </select>
                                                <button type="submit" name="update_status" class="btn btn-success">Update</button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" class="action-form" onsubmit="return confirm('Are you sure you want to delete this job? All applications will also be deleted.');">
                                            <input type="hidden" name="job_id" value="<?php echo $job['job_id']; ?>">
                                            <button type="submit" name="delete_job" class="btn btn-danger">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-jobs">
                    <h3>You haven't posted any jobs yet</h3>
                    <p>Get started by posting your first job opportunity for retirees.</p>
                    <a href="postJob.php" class="btn btn-success" style="margin-top: 15px;">Post Your First Job</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Simple confirmation for delete actions
        function confirmAction(message) {
            return confirm(message || 'Are you sure you want to perform this action?');
        }
    </script>
</body>
</html>
