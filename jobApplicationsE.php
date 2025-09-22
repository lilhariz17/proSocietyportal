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
$job = null;

// Pastikan job_id diberikan
if (!isset($_GET['job_id']) || empty($_GET['job_id'])) {
    header('Location: viewAllApplicationsE.php');
    exit;
}

$job_id = intval($_GET['job_id']);

try {
    // Ambil data job (hanya jika milik employer ini)
    $stmt = $pdo->prepare("SELECT job_id, title, location 
                           FROM jobs 
                           WHERE job_id = ? AND employer_id = ?");
    $stmt->execute([$job_id, $user_id]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$job) {
        $error = "Job tidak ditemukan atau bukan milik Anda.";
    } else {
        // Ambil aplikasi untuk job ini
        $stmt = $pdo->prepare("
            SELECT a.application_id, a.status, a.applied_at,
                   u.full_name AS applicant_name, u.email, u.phone, u.location, u.date_of_birth
            FROM applications a
            JOIN users u ON a.retiree_id = u.user_id
            WHERE a.job_id = ?
            ORDER BY a.applied_at DESC
        ");
        $stmt->execute([$job_id]);
        $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Log activity
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, activity_type, details) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, 'view_job_applications', "Viewed applications for job ID $job_id"]);
    }
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Applications - Retirement Plan</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .dashboard-header { background-color: #2ecc71; color: white; padding: 20px; text-align: center; }
        .dashboard-content { max-width: 1200px; margin: 20px auto; padding: 20px; }
        .back-btn { display: inline-block; margin-bottom: 20px; padding: 10px 15px; background-color: #7f8c8d; color: white; text-decoration: none; border-radius: 4px; }
        .back-btn:hover { background-color: #95a5a6; }
        .applications-table { width: 100%; border-collapse: collapse; margin-top: 20px; background-color: white; box-shadow: 0 2px 10px rgba(0,0,0,0.1); border-radius: 8px; overflow: hidden; }
        .applications-table th, .applications-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
        .applications-table th { background-color: #f5f7fa; font-weight: 600; color: #2c3e50; }
        .applications-table tr:hover { background-color: #f9f9f9; }
        .application-status { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 0.8rem; }
        /* Perbaikan: Memastikan class status berfungsi dengan benar */
        .status-pending { background-color: #f39c12; color: white; }
        .status-reviewed { background-color: #3498db; color: white; }
        .status-interview { background-color: #9b59b6; color: white; }
        .status-hired { background-color: #2ecc71; color: white; }
        .status-rejected { background-color: #e74c3c; color: white; }
        .no-applications { text-align: center; padding: 40px; color: #7f8c8d; font-style: italic; }
        @media (max-width: 768px) { .applications-table { display: block; overflow-x: auto; } }
    </style>
</head>
<body>
    <div class="dashboard-header">
        <h1>Job Applications - Retirement Plan Portal</h1>
    </div>

    <div class="dashboard-content">
        <a href="viewAllApplicationsE.php" class="back-btn">← Back to All Applications</a>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php elseif ($job): ?>
            <h2><?php echo htmlspecialchars($job['title']); ?> <span style="font-size:0.9rem;color:#7f8c8d;">(<?php echo htmlspecialchars($job['location']); ?>)</span></h2>

            <?php if (!empty($applications)): ?>
                <table class="applications-table">
                    <thead>
                        <tr>
                            <th>Applicant</th>
                            <th>Contact</th>
                            <th>Status</th>
                            <th>Applied At</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($applications as $app): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($app['applicant_name']); ?></strong>
                                <?php 
                                $birthdate = new DateTime($app['date_of_birth']);
                                $today = new DateTime();
                                $age = $today->diff($birthdate)->y;
                                ?>
                                <div style="font-size:0.85rem;color:#7f8c8d;">
                                    Age: <?php echo $age; ?> years • <?php echo htmlspecialchars($app['location']); ?>
                                </div>
                            </td>
                            <td>
                                <div>Email: 
                                    <a href="mailto:<?php echo htmlspecialchars($app['email']); ?>">
                                        <?php echo htmlspecialchars($app['email']); ?>
                                    </a>
                                </div>
                                <?php if (!empty($app['phone'])): ?>
                                <div>Phone: <?php echo htmlspecialchars($app['phone']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <!-- Perbaikan: Memastikan class status sesuai dengan nilai status -->
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
                            </td>
                            <td><?php echo date('M j, Y', strtotime($app['applied_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-applications">
                    <h3>No applications for this job</h3>
                    <p>This job has not received any applications yet.</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>