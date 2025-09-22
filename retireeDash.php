<?php
require_once 'config.php';

// Redirect to login if not authenticated or not retiree
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'retiree') {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get retiree profile and job data
try {
    // Get retiree profile
    $stmt = $pdo->prepare("SELECT * FROM retiree_profiles WHERE retiree_id = ?");
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get recent jobs
    $stmt = $pdo->prepare("SELECT j.*, c.company_name 
                          FROM jobs j 
                          JOIN companies c ON j.company_id = c.company_id 
                          ORDER BY j.created_at DESC 
                          LIMIT 5");
    $stmt->execute();
    $recent_jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get application count
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM applications WHERE retiree_id = ?");
    $stmt->execute([$user_id]);
    $application_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get recent applications
    $stmt = $pdo->prepare("SELECT a.*, j.title, c.company_name 
                          FROM applications a 
                          JOIN jobs j ON a.job_id = j.job_id 
                          JOIN companies c ON j.company_id = c.company_id 
                          WHERE a.retiree_id = ? 
                          ORDER BY a.applied_at DESC 
                          LIMIT 5");
    $stmt->execute([$user_id]);
    $recent_applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Determine current page for active menu highlight
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Retiree Dashboard - Retirement Plan</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        /* General Layout with Sidebar (same as Employer Dashboard) */
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
    margin-left: 250px; /* space for sidebar */
    padding: 0;
}

@media (max-width: 768px) {
    .content {
        margin-left: 0;
    }
}

/* Dashboard header - apply sidebar gradient */
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

/* Dashboard content container */
.dashboard-content {
    max-width: 1200px;
    margin: 30px auto;
    padding: 0 20px;
}

/* Welcome message card */
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

/* User role badge with sidebar gradient */
.user-role-badge {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 500;
    margin-left: 10px;
    background: linear-gradient(135deg, #800000 0%, #ff63a5 100%);
    color: white;
}

/* Profile summary, dashboard sections, and cards */
.profile-summary,
.dashboard-section,
.stat-card,
.action-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    padding: 25px;
    margin-bottom: 25px;
}

/* Profile header */
.profile-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

/* Profile header buttons with sidebar gradient */
.profile-header button {
    padding: 8px 16px;
    background: linear-gradient(135deg, #800000 0%, #ff63a5 100%);
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s ease;
}

.profile-header button:hover {
    background: linear-gradient(135deg, #ff63a5 0%, #800000 100%);
    transform: translateY(-2px);
}

/* Stats container */
.stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-number {
    font-size: 2.5rem;
    font-weight: 700;
    color: #800000; /* Updated to match sidebar main color */
    margin: 15px 0;
    text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
}

.stat-label {
    color: #7f8c8d;
    font-size: 1rem;
    font-weight: 500;
}

/* Dashboard grid layout */
.dashboard-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 25px;
    margin-bottom: 30px;
}

/* Section titles */
.section-title {
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #eee;
    color: #2c3e50;
    font-weight: 600;
    font-size: 1.2rem;
}

/* Job and application lists */
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

.company-name {
    color: #7f8c8d;
}

/* Application status badges - keep original colors or customize if needed */
.application-status {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
    margin-left: 10px;
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

.status-interview {
    background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
    color: white;
}

/* Action cards */
.action-card:hover {
    transform: translateY(-5px);
}

.action-card a {
    display: inline-block;
    padding: 10px 20px;
    background: linear-gradient(135deg, #800000 0%, #ff63a5 100%);
    color: white;
    text-decoration: none;
    border-radius: 6px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.action-card a:hover {
    background: linear-gradient(135deg, #ff63a5 0%, #800000 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(231, 76, 60, 0.4);
}

/* Primary buttons with sidebar gradient */
.btn-primary {
    background: linear-gradient(135deg, #800000 0%, #ff63a5 100%);
    color: white;
    text-decoration: none;
    padding: 10px 15px;
    border-radius: 4px;
    font-weight: 500;
    transition: all 0.3s ease;
    display: inline-block;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #ff63a5 0%, #800000 100%);
    transform: translateY(-2px);
}


/* Misc styles */
.activity-time {
    color: #7f8c8d;
    font-size: 0.9rem;
    margin-top: 5px;
}

.text-center {
    text-align: center;
}

    </style>
</head>
<body>
    <!-- Include Sidebar -->
    <?php include 'sidebarRetiree.php'; ?>

    <!-- Main Content -->
    <div class="content">
        <div class="dashboard-header">
            <h1>Retiree Dashboard - Pro Society Portal</h1>
        </div>
        
        <div class="dashboard-content">
            <div class="welcome-message">
                <h2>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h2>
                <p>You are logged in as: <span class="user-role-badge badge-retiree">Retiree</span></p>
            </div>

            <!-- Profile Section -->
            <div class="profile-summary">
                <div class="profile-header">
                    <h3>Your Profile</h3>
                    <a href="editProfileR.php" class="btn btn-primary">Edit Profile</a>
                </div>
                <?php if ($profile): ?>
                    <p><strong>Name:</strong> <?php echo !empty($profile['name']) ? htmlspecialchars($profile['name']) : 'Not specified'; ?></p>
                    <p><strong>Age:</strong> <?php echo !empty($profile['age']) ? htmlspecialchars($profile['age']) : 'Not specified'; ?></p>
                    <p><strong>Phone Number:</strong> <?php echo !empty($profile['phone_number']) ? htmlspecialchars($profile['phone_number']) : 'Not specified'; ?></p>
                    <p><strong>Education:</strong> <?php echo !empty($profile['education']) ? htmlspecialchars($profile['education']) : 'Not specified'; ?></p>
                    <p><strong>Location:</strong> <?php echo !empty($profile['location']) ? htmlspecialchars($profile['location']) : 'Not specified'; ?></p>
                    <p><strong>Skills:</strong> <?php echo !empty($profile['skills']) ? htmlspecialchars($profile['skills']) : 'Not specified'; ?></p>
                    <p><strong>Job Preferences:</strong> <?php echo !empty($profile['job_preferences']) ? htmlspecialchars($profile['job_preferences']) : 'Not specified'; ?></p>
                <?php else: ?>
                    <p>Complete your profile to get better job matches.</p>
                <?php endif; ?>
            </div>

            <!-- Stats -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $application_count; ?></div>
                    <div class="stat-label">Job Applications</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">5</div>
                    <div class="stat-label">Recommended Jobs</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">3</div>
                    <div class="stat-label">Interviews Scheduled</div>
                </div>
            </div>

            <!-- Job Opportunities + Applications -->
            <div class="dashboard-grid">
                <div class="dashboard-section">
                    <h3 class="section-title">Recent Job Opportunities</h3>
                    <?php if (!empty($recent_jobs)): ?>
                        <ul class="job-list">
                            <?php foreach ($recent_jobs as $job): ?>
                                <li class="job-item">
                                    <div class="job-title"><?php echo htmlspecialchars($job['title']); ?></div>
                                    <div class="company-name"><?php echo htmlspecialchars($job['company_name']); ?></div>
                                    <div><?php echo htmlspecialchars($job['location']); ?> • <?php echo htmlspecialchars($job['job_type']); ?></div>
                                    <a href="apply_Job.php?id=<?php echo $job['job_id']; ?>" class="btn btn-primary" style="margin-top: 10px; display: inline-block;">Apply Now</a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p>No recent job postings available.</p>
                    <?php endif; ?>
                    <div class="text-center" style="margin-top: 15px;">
                        <a href="viewJobR.php" class="btn btn-primary">View All Jobs</a>
                    </div>
                </div>

                <div class="dashboard-section">
                    <h3 class="section-title">Your Applications</h3>
                    <?php if (!empty($recent_applications)): ?>
                        <ul class="application-list">
                            <?php foreach ($recent_applications as $application): ?>
                                <li class="application-item">
                                    <div class="job-title"><?php echo htmlspecialchars($application['title']); ?></div>
                                    <div class="company-name"><?php echo htmlspecialchars($application['company_name']); ?></div>
                                    <div>
                                        Status: 
                                        <span class="application-status status-<?php echo strtolower($application['status']); ?>">
                                            <?php echo htmlspecialchars($application['status']); ?>
                                        </span>
                                    </div>
                                    <div class="activity-time">
                                        Applied on: <?php echo date('M j, Y', strtotime($application['applied_at'])); ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p>You haven't applied to any jobs yet.</p>
                    <?php endif; ?>
                    <div class="text-center" style="margin-top: 15px;">
                        <a href="viewAllApplicationsR.php" class="btn btn-primary">Your Applications</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
