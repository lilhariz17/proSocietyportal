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
$applications = [];
$job = null;

// Get job ID from URL
$job_id = $_GET['job_id'] ?? 0;

if (!$job_id) {
    $error = 'No job specified.';
} else {
    try {
        // Get job details
        $stmt = $pdo->prepare("
            SELECT j.*, c.company_name 
            FROM jobs j 
            JOIN companies c ON j.company_id = c.company_id 
            WHERE j.job_id = ?
        ");
        $stmt->execute([$job_id]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$job) {
            $error = 'Job not found.';
        } else {
            // Get applications for this job
            $stmt = $pdo->prepare("
                SELECT a.*, u.full_name, u.email, u.phone, u.location, 
                       r.education, r.skills, r.work_history
                FROM applications a
                JOIN users u ON a.retiree_id = u.user_id
                LEFT JOIN retiree_profiles r ON a.retiree_id = r.retiree_id
                WHERE a.job_id = ?
                ORDER BY a.applied_at DESC
            ");
            $stmt->execute([$job_id]);
            $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Log the view activity
            $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, activity_type, details) VALUES (?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], 'view_applications', "Viewed applications for job: " . $job['title']]);
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}

// Handle application status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $application_id = $_POST['application_id'];
    $new_status = $_POST['new_status'];
    
    try {
        $stmt = $pdo->prepare("UPDATE applications SET status = ? WHERE application_id = ?");
        $stmt->execute([$new_status, $application_id]);
        
        // Log the activity
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, activity_type, details) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], 'update_application', "Updated application $application_id to $new_status"]);
        
        $success = 'Application status updated successfully.';
        header("Refresh: 0"); // Refresh the page
    } catch (PDOException $e) {
        $error = 'Error updating application: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Applications - Retirement Plan</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .dashboard-header {
            background-color: #2c3e50;
            color: white;
            padding: 20px;
            text-align: center;
        }
        .dashboard-content {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }
        .back-btn {
            display: inline-block;
            margin-bottom: 20px;
            padding: 10px 15px;
            background-color: #7f8c8d;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
        .back-btn:hover {
            background-color: #95a5a6;
        }
        .job-header {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .applications-count {
            background-color: #3498db;
            color: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 20px;
        }
        .count-number {
            font-size: 2rem;
            font-weight: bold;
            display: block;
        }
        .count-label {
            font-size: 1rem;
        }
        .applications-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            overflow: hidden;
        }
        .applications-table th, .applications-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .applications-table th {
            background-color: #f5f7fa;
            font-weight: 600;
            color: #2c3e50;
        }
        .applications-table tr:hover {
            background-color: #f9f9f9;
        }
        .application-status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
        }
        .status-pending {
            background-color: #f39c12;
            color: white;
        }
        .status-reviewed {
            background-color: #3498db;
            color: white;
        }
        .status-interview {
            background-color: #9b59b6;
            color: white;
        }
        .status-hired {
            background-color: #2ecc71;
            color: white;
        }
        .status-rejected {
            background-color: #e74c3c;
            color: white;
        }
        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
        }
        .btn-primary {
            background-color: #3498db;
            color: white;
        }
        .btn-primary:hover {
            background-color: #2980b9;
        }
        .applicant-details {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 6px;
            margin: 10px 0;
            border-left: 4px solid #3498db;
        }
        .status-form {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .status-select {
            padding: 5px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        .no-applications {
            text-align: center;
            padding: 40px;
            color: #7f8c8d;
            font-style: italic;
        }
        @media (max-width: 768px) {
            .applications-table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-header">
        <h1>Job Applications - Retirement Plan Portal</h1>
    </div>
    
    <div class="dashboard-content">
        <a href="manageJob.php" class="back-btn">← Back to Job List</a>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($job): ?>
            <div class="job-header">
                <h2><?php echo htmlspecialchars($job['title']); ?></h2>
                <p><strong>Company:</strong> <?php echo htmlspecialchars($job['company_name']); ?></p>
                <p><strong>Location:</strong> <?php echo htmlspecialchars($job['location']); ?></p>
            </div>
            
            <div class="applications-count">
                <span class="count-number"><?php echo count($applications); ?></span>
                <span class="count-label">Applications Received</span>
            </div>
            
            <?php if (!empty($applications)): ?>
                <table class="applications-table">
                    <thead>
                        <tr>
                            <th>Applicant</th>
                            <th>Contact</th>
                            <th>Location</th>
                            <th>Applied</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($applications as $application): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($application['full_name']); ?></strong>
                                    <div class="applicant-details">
                                        <?php if (!empty($application['skills'])): ?>
                                            <p><strong>Skills:</strong> <?php echo htmlspecialchars(substr($application['skills'], 0, 100)); ?>...</p>
                                        <?php endif; ?>
                                        <?php if (!empty($application['work_history'])): ?>
                                            <p><strong>Experience:</strong> <?php echo htmlspecialchars(substr($application['work_history'], 0, 100)); ?>...</p>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($application['email']); ?></div>
                                    <?php if (!empty($application['phone'])): ?>
                                        <div><?php echo htmlspecialchars($application['phone']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($application['location']); ?></td>
                                <td><?php echo date('M j, Y', strtotime($application['applied_at'])); ?></td>
                                <td>
                                    <span class="application-status status-<?php echo $application['status'] ?? 'pending'; ?>">
                                        <?php echo ucfirst($application['status'] ?? 'pending'); ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" class="status-form">
                                        <input type="hidden" name="application_id" value="<?php echo $application['application_id']; ?>">
                                        <select name="new_status" class="status-select">
                                            <option value="pending" <?php echo ($application['status'] ?? 'pending') === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="reviewed" <?php echo ($application['status'] ?? 'pending') === 'reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                                            <option value="interview" <?php echo ($application['status'] ?? 'pending') === 'interview' ? 'selected' : ''; ?>>Interview</option>
                                            <option value="hired" <?php echo ($application['status'] ?? 'pending') === 'hired' ? 'selected' : ''; ?>>Hired</option>
                                            <option value="rejected" <?php echo ($application['status'] ?? 'pending') === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                        </select>
                                        <button type="submit" name="update_status" class="btn btn-primary">Update</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-applications">
                    <h3>No applications received yet</h3>
                    <p>This job hasn't received any applications so far.</p>
                </div>
            <?php endif; ?>
            
        <?php elseif (!$error): ?>
            <div class="error">Job not found.</div>
        <?php endif; ?>
    </div>
</body>
</html>