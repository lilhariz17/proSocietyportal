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
$applications = [];
$job = null;

// Get job ID from URL
$job_id = $_GET['job_id'] ?? 0;

if (!$job_id) {
    $error = 'No job specified.';
} else {
    try {
        // Get job details - verify it belongs to this employer
        $stmt = $pdo->prepare("
            SELECT j.*, c.company_name 
            FROM jobs j 
            JOIN companies c ON j.company_id = c.company_id 
            WHERE j.job_id = ? AND j.employer_id = ?
        ");
        $stmt->execute([$job_id, $user_id]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$job) {
            $error = 'Job not found or you do not have permission to view applications for this job.';
        } else {
            // Get applications for this job
            $stmt = $pdo->prepare("
                SELECT a.*, u.full_name, u.email, u.phone, u.location, u.date_of_birth,
                       r.education, r.skills, r.work_history, r.job_preferences
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
            $stmt->execute([$user_id, 'view_applications', "Viewed applications for job: " . $job['title']]);
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}

// Handle application status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $application_id = $_POST['application_id'];
    $new_status = $_POST['new_status'];
    $notes = $_POST['notes'] ?? '';
    
    try {
        // Verify the application belongs to a job owned by this employer
        $stmt = $pdo->prepare("
            SELECT a.* FROM applications a
            JOIN jobs j ON a.job_id = j.job_id
            WHERE a.application_id = ? AND j.employer_id = ?
        ");
        $stmt->execute([$application_id, $user_id]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$application) {
            $error = 'Application not found or you do not have permission to update it.';
        } else {
            $stmt = $pdo->prepare("UPDATE applications SET status = ?, notes = ? WHERE application_id = ?");
            $stmt->execute([$new_status, $notes, $application_id]);
            
            // Log the activity
            $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, activity_type, details) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, 'update_application', "Updated application $application_id to $new_status"]);
            
            $success = 'Application status updated successfully.';
            header("Refresh: 0"); // Refresh the page
        }
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
    <title>Job Applications - Retirement Plan</title>
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

    .dashboard-content { max-width: 1200px; margin: 30px auto; padding: 0 20px; }

    /* Back button - keep gray */
    .back-btn {
        display: inline-block;
        margin-bottom: 20px;
        padding: 10px 15px;
        background-color: #7f8c8d;
        color: white;
        text-decoration: none;
        border-radius: 4px;
    }
    .back-btn:hover { background-color: #95a5a6; }

    /* Applications count box - updated gradient */
    .applications-count {
        background: linear-gradient(180deg, #800000 0%, #ff63a5 100%);
        color: white;
        padding: 15px;
        border-radius: 8px;
        text-align: center;
        margin-bottom: 20px;
    }
    .count-number { font-size: 2rem; font-weight: bold; display: block; }
    .count-label { font-size: 1rem; }

    /* Applications table */
    .applications-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
        background-color: white;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        border-radius: 8px;
        overflow: hidden;
    }
    .applications-table th, .applications-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
    .applications-table th { background-color: #f5f7fa; font-weight: 600; color: #2c3e50; }
    .applications-table tr:hover { background-color: #f9f9f9; }

    /* Status badges remain same */
    .application-status { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 0.8rem; }
    .status-pending { background-color: #f39c12; color: white; }
    .status-reviewed { background-color: #3498db; color: white; }
    .status-interview { background-color: #9b59b6; color: white; }
    .status-hired { background-color: #2ecc71; color: white; }
    .status-rejected { background-color: #e74c3c; color: white; }

    /* Buttons - update primary buttons to gradient */
    .btn-primary {
        background: linear-gradient(180deg, #800000 0%, #ff63a5 100%);
        color: white;
        border-radius: 4px;
        padding: 6px 12px;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 0.9rem;
    }
    .btn-primary:hover {
        background: linear-gradient(180deg, #ff63a5 0%, #800000 100%);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(128,0,0,0.4);
    }

    /* Edit button - updated to gradient */
    .edit-button {
        background: linear-gradient(180deg, #800000 0%, #ff63a5 100%);
        color: white;
        text-decoration: none;
        padding: 10px 15px;
        border-radius: 4px;
        font-weight: 500;
        transition: background-color 0.2s;
        display: inline-block;
    }
    .edit-button:hover {
        background: linear-gradient(180deg, #ff63a5 0%, #800000 100%);
    }

    /* Other elements remain unchanged */
    .job-header { background-color: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 20px; margin-bottom: 20px; }
    .applicant-details { background-color: #f9f9f9; padding: 15px; border-radius: 6px; margin: 10px 0; border-left: 4px solid #3498db; }
    .status-form { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
    .status-select, .notes-input { padding: 5px; border-radius: 4px; border: 1px solid #ddd; }
    .no-applications { text-align: center; padding: 40px; color: #7f8c8d; font-style: italic; }
    .applicant-profile { margin-top: 10px; padding: 10px; background-color: #f5f7fa; border-radius: 4px; border-left: 3px solid #2ecc71; }
    .profile-section h4 { margin: 5px 0; color: #2c3e50; font-size: 0.9rem; }
    .profile-content { font-size: 0.9rem; color: #34495e; }
    .contact-info { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px,1fr)); gap: 10px; margin-top: 10px; }
    .contact-item { padding: 8px; background-color: white; border-radius: 4px; border: 1px solid #eee; }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .applications-table { display: block; overflow-x: auto; }
        .status-form { flex-direction: column; align-items: stretch; }
        .notes-input { width: 100%; }
    }

    /* Messages remain unchanged */
    .error { background-color: #ffecec; color: #e74c3c; padding: 15px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid #e74c3c; }
    .success { background-color: #e7f7ed; color: #2ecc71; padding: 15px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid #2ecc71; }
</style>

</head>
<body>
    <!-- Include Sidebar -->
    <?php include 'sidebarEmployer.php'; ?>
    
    <!-- Main Content -->
    <div class="content">
        <div class="dashboard-header">
            <h1>Job Applications - Retirement Plan Portal</h1>
        </div>
        
        <div class="dashboard-content">
            <a href="viewJobE.php" class="back-btn">← Back to View Job</a>
            <a href="manageJobE.php" class="back-btn">← Back to Manage Job</a>
            
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
                    <p><strong>Job Type:</strong> <?php echo htmlspecialchars($job['job_type']); ?></p>
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
                                <th>Contact Information</th>
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
                                        <?php 
                                        $birthdate = new DateTime($application['date_of_birth']);
                                        $today = new DateTime();
                                        $age = $today->diff($birthdate)->y;
                                        ?>
                                        <div style="font-size: 0.9rem; color: #7f8c8d;">
                                            Age: <?php echo $age; ?> years • <?php echo htmlspecialchars($application['location']); ?>
                                        </div>
                                        
                                        <div class="applicant-profile">
                                            <?php if (!empty($application['skills'])): ?>
                                            <div class="profile-section">
                                                <h4>Skills:</h4>
                                                <div class="profile-content"><?php echo htmlspecialchars($application['skills']); ?></div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($application['work_history'])): ?>
                                            <div class="profile-section">
                                                <h4>Work Experience:</h4>
                                                <div class="profile-content"><?php echo nl2br(htmlspecialchars(substr($application['work_history'], 0, 200))); ?>...</div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($application['education'])): ?>
                                            <div class="profile-section">
                                                <h4>Education:</h4>
                                                <div class="profile-content"><?php echo htmlspecialchars($application['education']); ?></div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($application['job_preferences'])): ?>
                                            <div class="profile-section">
                                                <h4>Job Preferences:</h4>
                                                <div class="profile-content"><?php echo htmlspecialchars($application['job_preferences']); ?></div>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="contact-info">
                                            <div class="contact-item">
                                                <strong>Email:</strong><br>
                                                <a href="mailto:<?php echo htmlspecialchars($application['email']); ?>">
                                                    <?php echo htmlspecialchars($application['email']); ?>
                                                </a>
                                            </div>
                                            <?php if (!empty($application['phone'])): ?>
                                            <div class="contact-item">
                                                <strong>Phone:</strong><br>
                                                <?php echo htmlspecialchars($application['phone']); ?>
                                            </div>
                                            <?php endif; ?>
                                            <div class="contact-item">
                                                <strong>Location:</strong><br>
                                                <?php echo htmlspecialchars($application['location']); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($application['applied_at'])); ?></td>
                                    <td>
                                        <span class="application-status status-<?php echo $application['status'] ?? 'pending'; ?>">
                                            <?php echo ucfirst($application['status'] ?? 'pending'); ?>
                                        </span>
                                        <?php if (!empty($application['notes'])): ?>
                                            <div style="font-size: 0.8rem; color: #7f8c8d; margin-top: 5px;">
                                                Notes: <?php echo htmlspecialchars($application['notes']); ?>
                                            </div>
                                        <?php endif; ?>
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
                                            <input type="text" name="notes" class="notes-input" placeholder="Add notes..." value="<?php echo htmlspecialchars($application['notes'] ?? ''); ?>">
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
    </div>
</body>
</html>