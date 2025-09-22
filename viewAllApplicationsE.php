<?php
require_once 'config.php';

// Redirect jika belum login atau bukan employer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';
$applications = [];

try {
    // Ambil semua aplikasi untuk job milik employer ini
    $stmt = $pdo->prepare("
        SELECT a.application_id, a.status, a.applied_at, a.notes,
               j.job_id, j.title, j.location AS job_location,
               u.full_name AS applicant_name, u.email, u.phone, u.location, u.date_of_birth
        FROM applications a
        JOIN jobs j ON a.job_id = j.job_id
        JOIN users u ON a.retiree_id = u.user_id
        WHERE j.employer_id = ?
        ORDER BY a.applied_at DESC
    ");
    $stmt->execute([$user_id]);
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Log activity
    $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, activity_type, details) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, 'view_all_applications', "Viewed all applications"]);
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// Determine current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Applications - Retirement Plan</title>
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
        
        .dashboard-header {
            background: linear-gradient(135deg, #2c3e50 0%, #4a6580 100%);
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
        
        .error {
            background-color: #fde8e8;
            color: #e74c3c;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            border-left: 4px solid #e74c3c;
            font-weight: 500;
        }
        
        .applications-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            border-radius: 12px;
            overflow: hidden;
        }
        
        .applications-table th, .applications-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .applications-table th {
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8eb 100%);
            font-weight: 600;
            color: #2c3e50;
            font-size: 16px;
        }
        
        .applications-table tr:hover {
            background-color: #f9f9f9;
        }
        
        .applications-table a.job-title-link {
            color: inherit;
            text-decoration: none;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .applications-table a.job-title-link:hover {
            text-decoration: underline;
            color: #3498db;
        }
        
        .application-status {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .status-pending { 
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            color: white; 
        }
        
        .status-reviewed { 
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white; 
        }
        
        .status-interview { 
            background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
            color: white; 
        }
        
        .status-hired { 
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            color: white; 
        }
        
        .status-rejected { 
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white; 
        }
        
        .no-applications {
            text-align: center;
            padding: 60px 40px;
            color: #7f8c8d;
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            margin-top: 20px;
        }
        
        .no-applications h3 {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 15px;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #2980b9 0%, #3498db 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(52, 152, 219, 0.4);
        }
        
        .contact-link {
            color: #3498db;
            text-decoration: none;
        }
        
        .contact-link:hover {
            text-decoration: underline;
        }
        
        .applicant-info {
            font-size: 0.9rem;
            color: #7f8c8d;
            margin-top: 5px;
        }
        
        .notes-text {
            font-size: 0.85rem;
            color: #7f8c8d;
            margin-top: 5px;
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
    <!-- Include Sidebar -->
    <?php include 'sidebarEmployer.php'; ?>
    
    <!-- Main Content -->
    <div class="content">
        <div class="dashboard-header">
            <h1>All Applications - Retirement Plan Portal</h1>
        </div>

        <div class="dashboard-content">
            <a href="viewJobE.php" class="back-btn">← Back to View Job</a>

        <div class="dashboard-content">
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (!empty($applications)): ?>
                <table class="applications-table">
                    <thead>
                        <tr>
                            <th>Job Title</th>
                            <th>Applicant</th>
                            <th>Contact</th>
                            <th>Status</th>
                            <th>Applied At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($applications as $app): ?>
                        <tr>
                            <td>
                                <a href="jobApplicationsE.php?job_id=<?php echo $app['job_id']; ?>" class="job-title-link">
                                    <?php echo htmlspecialchars($app['title']); ?>
                                </a>
                                <div class="applicant-info">
                                    <?php echo htmlspecialchars($app['job_location']); ?>
                                </div>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($app['applicant_name']); ?></strong>
                                <?php 
                                $birthdate = new DateTime($app['date_of_birth']);
                                $today = new DateTime();
                                $age = $today->diff($birthdate)->y;
                                ?>
                                <div class="applicant-info">
                                    Age: <?php echo $age; ?> years • <?php echo htmlspecialchars($app['location']); ?>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <a href="mailto:<?php echo htmlspecialchars($app['email']); ?>" class="contact-link">
                                        <?php echo htmlspecialchars($app['email']); ?>
                                    </a>
                                </div>
                                <?php if (!empty($app['phone'])): ?>
                                <div class="applicant-info">
                                    Phone: <?php echo htmlspecialchars($app['phone']); ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                $statusClass = '';
                                switch(strtolower($app['status'])) {
                                    case 'pending':
                                        $statusClass = 'status-pending';
                                        break;
                                    case 'reviewed':
                                        $statusClass = 'status-reviewed';
                                        break;
                                    case 'interview':
                                        $statusClass = 'status-interview';
                                        break;
                                    case 'hired':
                                        $statusClass = 'status-hired';
                                        break;
                                    case 'rejected':
                                        $statusClass = 'status-rejected';
                                        break;
                                    default:
                                        $statusClass = 'status-pending';
                                }
                                ?>
                                <span class="application-status <?php echo $statusClass; ?>">
                                    <?php echo ucfirst($app['status']); ?>
                                </span>
                                <?php if (!empty($app['notes'])): ?>
                                    <div class="notes-text">
                                        Notes: <?php echo htmlspecialchars($app['notes']); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($app['applied_at'])); ?></td>
                            <td>
                                <a href="jobApplicationsE.php?job_id=<?php echo $app['job_id']; ?>" class="btn btn-primary">View Details</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-applications">
                    <h3>No applications found</h3>
                    <p>Your jobs have not received any applications yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>