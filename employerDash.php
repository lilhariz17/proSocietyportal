<?php
require_once 'config.php';

// Redirect to login if not authenticated or not employer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get employer data
try {
    // Get employer profile and company info
    $stmt = $pdo->prepare("SELECT ep.*, c.company_name, c.industry, c.location as company_location 
                          FROM employer_profiles ep 
                          JOIN companies c ON ep.company_id = c.company_id 
                          WHERE ep.employer_id = ?");
    $stmt->execute([$user_id]);
    $employer_profile = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get job count
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM jobs WHERE employer_id = ?");
    $stmt->execute([$user_id]);
    $job_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get application count
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM applications a 
                          JOIN jobs j ON a.job_id = j.job_id 
                          WHERE j.employer_id = ?");
    $stmt->execute([$user_id]);
    $application_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get recent jobs
    $stmt = $pdo->prepare("SELECT j.*, COUNT(a.application_id) as application_count 
                          FROM jobs j 
                          LEFT JOIN applications a ON j.job_id = a.job_id 
                          WHERE j.employer_id = ? 
                          GROUP BY j.job_id 
                          ORDER BY j.created_at DESC 
                          LIMIT 5");
    $stmt->execute([$user_id]);
    $recent_jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent applications
    $stmt = $pdo->prepare("SELECT a.*, j.title, r.full_name as applicant_name 
                          FROM applications a 
                          JOIN jobs j ON a.job_id = j.job_id 
                          JOIN users r ON a.retiree_id = r.user_id 
                          WHERE j.employer_id = ? 
                          ORDER BY a.applied_at DESC 
                          LIMIT 5");
    $stmt->execute([$user_id]);
    $recent_applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
    <title>Employer Dashboard - Retirement Plan</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
    /* Layout for sidebar and content */
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

    /* Dashboard header - updated to match admin colors */
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
        max-width: 1200px;
        margin: 30px auto;
        padding: 0 20px;
    }

    /* Welcome message */
    .welcome-message {
        background: white;
        border-radius: 12px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        padding: 25px;
        margin-bottom: 25px;
    }

    .welcome-message h2 {
        color: #2c3e50;
        margin-bottom: 10px;
        font-weight: 600;
    }

    .user-role-badge {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.9rem;
        font-weight: 500;
        margin-left: 10px;
    }

    /* Employer badge - leave unchanged */
    .badge-employer {
        background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
        color: white;
    }

    /* Company summary section */
    .company-summary {
        background: white;
        border-radius: 12px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        padding: 25px;
        margin-bottom: 25px;
    }

    .company-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }

    .company-header h3 {
        color: #2c3e50;
        font-weight: 600;
    }

    /* Buttons - updated to match admin gradient */
    .company-header button,
    .btn-primary,
    .edit-button,
    .action-card a {
        background: linear-gradient(180deg, #800000 0%, #ff63a5 100%); /* Bright Maroon → Candy Pink */
        color: white;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-block;
        padding: 10px 15px;
    }

    .company-header button:hover,
    .btn-primary:hover,
    .edit-button:hover,
    .action-card a:hover {
        background: linear-gradient(180deg, #ff63a5 0%, #800000 100%); /* Reverse gradient on hover */
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    }

    /* Stats container */
    .stats-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    /* Stat card */
    .stat-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        padding: 25px;
        text-align: center;
        transition: transform 0.3s ease;
    }

    .stat-card:hover {
        transform: translateY(-5px);
    }

    /* Stat number - updated to match admin gradient style */
    .stat-number {
        font-size: 2.5rem;
        font-weight: 700;
        background: linear-gradient(180deg, #800000 0%, #ff63a5 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        margin: 15px 0;
        text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
    }

    .stat-label {
        color: #7f8c8d;
        font-size: 1rem;
        font-weight: 500;
    }

    /* Other sections remain the same */
    .dashboard-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 25px;
        margin-bottom: 30px;
    }

    .dashboard-section {
        background: white;
        border-radius: 12px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        padding: 25px;
    }

    .section-title {
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid #eee;
        color: #2c3e50;
        font-weight: 600;
        font-size: 1.2rem;
    }

    .job-list, .application-list {
        list-style: none;
    }

    .job-item, .application-item {
        padding: 15px;
        border-bottom: 1px solid #eee;
        transition: background-color 0.3s;
    }

    .job-item:hover, .application-item:hover {
        background-color: #f9f9f9;
    }

    .job-item:last-child, .application-item:last-child {
        border-bottom: none;
    }

    .job-title {
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 5px;
    }

    .application-status {
        display: inline-block;
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 500;
        margin-left: 10px;
    }

    /* Status badges remain the same */
    .status-pending { background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%); color: white; }
    .status-approved { background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%); color: white; }
    .status-rejected { background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); color: white; }

    .application-count {
        background: linear-gradient(180deg, #3498db 0%, #2980b9 100%);
        color: white;
        border-radius: 50%;
        width: 30px;
        height: 30px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
        font-weight: 600;
        margin-left: 10px;
    }

    /* Other buttons remain same */
    .btn-secondary { background: linear-gradient(135deg, #7f8c8d 0%, #95a5a6 100%); color: white; }
    .btn-secondary:hover { background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%); transform: translateY(-2px); 
    }
</style>

</head>
<body>
    <!-- Include Sidebar -->
    <?php include 'sidebarEmployer.php'; ?>
    
    <!-- Main Content -->
    <div class="content">
        <div class="dashboard-header">
            <h1>Employer Dashboard - Pro Society Portal</h1>
        </div>
        
        <div class="dashboard-content">
            <div class="welcome-message">
                <h2>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h2>
                <p>You are logged in as: <span class="user-role-badge badge-employer">Employer</span></p>
            </div>
            
            <?php if ($employer_profile): ?>
            <div class="company-summary">
                <div class="company-header">
                    <h3><?php echo htmlspecialchars($employer_profile['company_name']); ?></h3>
                    <a href="editProfileE.php" class="edit-button">Edit Company Info</a>
                </div>
                <p><strong>Industry:</strong> <?php echo htmlspecialchars($employer_profile['industry']); ?></p>
                <p><strong>Location:</strong> <?php echo htmlspecialchars($employer_profile['company_location']); ?></p>
                <p><strong>Your Role:</strong> <?php echo htmlspecialchars($employer_profile['job_title']); ?></p>
            </div>
            <?php endif; ?>
            
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $job_count; ?></div>
                    <div class="stat-label">Active Job Postings</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $application_count; ?></div>
                    <div class="stat-label">Total Applications</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">12</div>
                    <div class="stat-label">New Applications</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">8</div>
                    <div class="stat-label">Interviews Scheduled</div>
                </div>
            </div>
            
            <div class="dashboard-grid">
                <div class="dashboard-section">
                    <h3 class="section-title">Your Job Postings</h3>
                    <?php if (!empty($recent_jobs)): ?>
                        <ul class="job-list">
                            <?php foreach ($recent_jobs as $job): ?>
                                <li class="job-item">
                                    <div class="job-title"><?php echo htmlspecialchars($job['title']); ?></div>
                                    <div><?php echo htmlspecialchars($job['location']); ?> • <?php echo htmlspecialchars($job['job_type']); ?></div>
                                    <div>
                                        Applications: <span class="application-count"><?php echo $job['application_count']; ?></span>
                                    </div>
                                    <div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p>You haven't posted any jobs yet.</p>
                    <?php endif; ?>
                    <div class="text-center" style="margin-top: 15px;">
                        <a href="viewJobE.php" class="btn btn-primary">View All Jobs</a>
                    </div>
                </div>
                
                <div class="dashboard-section">
                    <h3 class="section-title">Recent Applications</h3>
                    <?php if (!empty($recent_applications)): ?>
                        <ul class="application-list">
                            <?php foreach ($recent_applications as $application): ?>
                                <li class="application-item">
                                    <div class="job-title"><?php echo htmlspecialchars($application['title']); ?></div>
                                    <div><strong>Applicant:</strong> <?php echo htmlspecialchars($application['applicant_name']); ?></div>
                                    <div>
                                        Status: 
                                        <span class="application-status status-<?php echo strtolower($application['status']); ?>">
                                            <?php echo htmlspecialchars($application['status']); ?>
                                        </span>
                                    </div>
                                    <div class="activity-time">
                                        Applied on: <?php echo date('M j, Y', strtotime($application['applied_at'])); ?>
                                    </div>
                                    <div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p>No recent applications.</p>
                    <?php endif; ?>
                    <div class="text-center" style="margin-top: 15px;">
                        <a href="viewAllApplicationsE.php" class="btn btn-primary">View All Applications</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>