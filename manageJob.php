<?php
require_once 'config.php';

// Redirect to login if not authenticated or not admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Initialize variables
$error = '';
$success = '';
$jobs = [];
$search = '';

// Handle search
if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
}

// Handle job actions (delete, approve, reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_job'])) {
        $job_id_to_delete = $_POST['job_id'];
        
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Delete job applications first
            $stmt = $pdo->prepare("DELETE FROM applications WHERE job_id = ?");
            $stmt->execute([$job_id_to_delete]);
            
            // Then delete the job
            $stmt = $pdo->prepare("DELETE FROM jobs WHERE job_id = ?");
            $stmt->execute([$job_id_to_delete]);
            
            $pdo->commit();
            
            // Log the activity
            $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, activity_type, details) VALUES (?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], 'delete_job', "Deleted job ID: $job_id_to_delete"]);
            
            $success = 'Job deleted successfully.';
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Error deleting job: ' . $e->getMessage();
        }
    } elseif (isset($_POST['approve_job'])) {
        $job_id_to_approve = $_POST['job_id'];
        
        try {
            // In a real application, you might have an approval status field
            // For this example, we'll assume jobs are active once approved
            $stmt = $pdo->prepare("UPDATE jobs SET status = 'approved' WHERE job_id = ?");
            $stmt->execute([$job_id_to_approve]);
            
            // Log the activity
            $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, activity_type, details) VALUES (?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], 'approve_job', "Approved job ID: $job_id_to_approve"]);
            
            $success = 'Job approved successfully.';
        } catch (PDOException $e) {
            $error = 'Error approving job: ' . $e->getMessage();
        }
    } elseif (isset($_POST['reject_job'])) {
        $job_id_to_reject = $_POST['job_id'];
        
        try {
            $stmt = $pdo->prepare("UPDATE jobs SET status = 'rejected' WHERE job_id = ?");
            $stmt->execute([$job_id_to_reject]);
            
            // Log the activity
            $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, activity_type, details) VALUES (?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], 'reject_job', "Rejected job ID: $job_id_to_reject"]);
            
            $success = 'Job rejected successfully.';
        } catch (PDOException $e) {
            $error = 'Error rejecting job: ' . $e->getMessage();
        }
    }
}

// Get all jobs from the database with company information
try {
    $query = "SELECT 
                j.*, 
                c.company_name,
                c.industry,
                u.full_name as employer_name,
                COUNT(a.application_id) as application_count
              FROM jobs j
              JOIN companies c ON j.company_id = c.company_id
              JOIN users u ON j.employer_id = u.user_id
              LEFT JOIN applications a ON j.job_id = a.job_id";
    
    if (!empty($search)) {
        $query .= " WHERE j.title LIKE :search OR c.company_name LIKE :search OR j.location LIKE :search";
    }
    
    $query .= " GROUP BY j.job_id ORDER BY j.created_at DESC";
    
    $stmt = $pdo->prepare($query);
    
    if (!empty($search)) {
        $searchTerm = '%' . $search . '%';
        $stmt->bindValue(':search', $searchTerm);
    }
    
    $stmt->execute();
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Error fetching jobs: ' . $e->getMessage();
}

// Get statistics for the dashboard
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_jobs FROM jobs");
    $stmt->execute();
    $total_jobs = $stmt->fetch(PDO::FETCH_ASSOC)['total_jobs'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as pending_jobs FROM jobs WHERE status = 'pending' OR status IS NULL OR status = ''");
    $stmt->execute();
    $pending_jobs = $stmt->fetch(PDO::FETCH_ASSOC)['pending_jobs'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as active_jobs FROM jobs WHERE status = 'approved'");
    $stmt->execute();
    $active_jobs = $stmt->fetch(PDO::FETCH_ASSOC)['active_jobs'];
    
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT job_id) as jobs_with_applications FROM applications");
    $stmt->execute();
    $jobs_with_applications = $stmt->fetch(PDO::FETCH_ASSOC)['jobs_with_applications'];
} catch (PDOException $e) {
    // Silently fail for stats - they're not critical
}

// Determine current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Jobs - Retirement Plan</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        /* Layout untuk sidebar dan content */
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
        
        /* Dashboard Header */
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
        
        .dashboard-content {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .back-btn {
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
        .back-btn:hover {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(127, 140, 141, 0.4);
        }
        
        h2 {
            color: #2c3e50;
            margin-bottom: 25px;
            font-weight: 600;
            border-bottom: 2px solid #800000; /* only color changed */
            padding-bottom: 12px;
            font-size: 24px;
        }
        
        .error {
            background-color: #fee;
            color: #c33;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #c33;
            font-weight: 500;
        }
        
        .success {
            background-color: #efe;
            color: #363;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #363;
            font-weight: 500;
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
        .search-box {
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .search-form {
            display: flex;
            flex: 1;
            max-width: 500px;
        }
        .search-input {
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px 0 0 6px;
            flex: 1;
            font-size: 16px;
            font-family: 'Poppins', sans-serif;
            transition: border-color 0.3s ease;
        }
        .search-input:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
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
            margin-left: 10px;
            background: linear-gradient(135deg, #6c757d 0%, #adb5bd 100%); /* grey gradient */
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }

        .reset-btn:hover {
            background: linear-gradient(135deg, #adb5bd 0%, #6c757d 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(108, 117, 125, 0.4);
        }

        .jobs-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background-color: white;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            border-radius: 12px;
            overflow: hidden;
        }
        .jobs-table th, .jobs-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .jobs-table th {
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8eb 100%);
            font-weight: 600;
            color: #2c3e50;
            font-size: 16px;
            position: sticky;
            top: 0;
        }
        .jobs-table tr:hover {
            background-color: #f9f9f9;
        }
        .action-form {
            display: inline-block;
            margin-right: 5px;
            margin-bottom: 5px;
        }
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-danger {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            box-shadow: 0 2px 5px rgba(231, 76, 60, 0.3);
        }
        .btn-danger:hover {
            background: linear-gradient(135deg, #c0392b 0%, #e74c3c 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(231, 76, 60, 0.4);
        }
        .btn-success {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            color: white;
            box-shadow: 0 2px 5px rgba(46, 204, 113, 0.3);
        }
        .btn-success:hover {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(46, 204, 113, 0.4);
        }
        .btn-warning {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            color: white;
            box-shadow: 0 2px 5px rgba(243, 156, 18, 0.3);
        }
        .btn-warning:hover {
            background: linear-gradient(135deg, #e67e22 0%, #f39c12 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(243, 156, 18, 0.4);
        }
        .btn-info {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            box-shadow: 0 2px 5px rgba(52, 152, 219, 0.3);
        }
        .btn-info:hover {
            background: linear-gradient(135deg, #2980b9 0%, #3498db 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(52, 152, 219, 0.4);
        }
        .job-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        .status-pending {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            color: white;
        }
        .status-approved {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            color: white;
        }
        .status-rejected {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
        }
        .job-type {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 500;
            background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
            color: white;
        }
        .application-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            border-radius: 50%;
            font-size: 0.9rem;
            font-weight: bold;
            box-shadow: 0 2px 5px rgba(52, 152, 219, 0.3);
        }
        .pagination {
            margin-top: 20px;
            text-align: center;
        }
        .pagination a {
            display: inline-block;
            padding: 10px 15px;
            margin: 0 5px;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8eb 100%);
            color: #2c3e50;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .pagination a.active {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            box-shadow: 0 2px 5px rgba(52, 152, 219, 0.3);
        }
        .view-applications {
            margin-top: 10px;
            display: block;
        }
        .job-description {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        @media (max-width: 1200px) {
            .jobs-table {
                display: block;
                overflow-x: auto;
            }
        }
        /* Center "No jobs found" message */
        .no-jobs-message {
            text-align: center;
            margin-top: 30px;
            font-size: 1.1rem;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <!-- Include Sidebar -->
    <?php include 'sidebarAdmin.php'; ?>
    
    <!-- Main Content -->
    <div class="content">
        <div class="dashboard-header">
            <h1>Manage Jobs - Pro Society Portal</h1>
        </div>
        
        <div class="dashboard-content">
            <h2>Job Management</h2>
            
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_jobs ?? 0; ?></div>
                    <div class="stat-label">Total Jobs</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $pending_jobs ?? 0; ?></div>
                    <div class="stat-label">Pending Approval</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $active_jobs ?? 0; ?></div>
                    <div class="stat-label">Active Jobs</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $jobs_with_applications ?? 0; ?></div>
                    <div class="stat-label">Jobs with Applications</div>
                </div>
            </div>
            
            <div class="search-box">
                <form method="GET" class="search-form">
                    <input type="text" name="search" 
                        placeholder="Search jobs by title, company, or location..." 
                        class="search-input" 
                        value="<?php echo htmlspecialchars($search); ?>">

                    <!-- Search button -->
                    <button type="submit" class="search-btn">Search</button>

                    <!-- Reset button (only visible when search is active) -->
                    <?php if (!empty($search)) : ?>
                        <a href="manageJob.php" class="reset-btn">Reset</a>
                    <?php endif; ?>
                </form>
                <div>
            </div>
            
            <?php if (!empty($jobs)): ?>
                <table class="jobs-table">
                    <thead>
                        <tr>
                            <th>Job Title</th>
                            <th>Company</th>
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
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($job['company_name']); ?>
                                    <div style="font-size: 0.9rem; color: #7f8c8d;">
                                        <?php echo htmlspecialchars($job['employer_name']); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="job-type"><?php echo htmlspecialchars($job['job_type']); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($job['location']); ?></td>
                                <td>
                                    <span class="application-count"><?php echo $job['application_count']; ?></span>
                                </td>
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
                                        <?php if ($status === 'pending' || $status === ''): ?>
                                            <form method="POST" class="action-form">
                                                <input type="hidden" name="job_id" value="<?php echo $job['job_id']; ?>">
                                                <button type="submit" name="approve_job" class="btn btn-success">Approve</button>
                                            </form>
                                            <form method="POST" class="action-form">
                                                <input type="hidden" name="job_id" value="<?php echo $job['job_id']; ?>">
                                                <button type="submit" name="reject_job" class="btn btn-warning">Reject</button>
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
            <div class="error no-jobs-message">
                No jobs found. <?php echo !empty($search) ? 'Try a different search term.' : ''; ?>
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