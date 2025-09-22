<?php
require_once 'config.php';

// Redirect if not logged in or not employer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';
$job = null;

// Get job_id from URL
$job_id = $_GET['job_id'] ?? 0;

if (!$job_id) {
    $error = 'No job specified.';
} else {
    try {
        // Get job owned by this employer
        $stmt = $pdo->prepare("
            SELECT * FROM jobs
            WHERE job_id = ? AND employer_id = ?
        ");
        $stmt->execute([$job_id, $user_id]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$job) {
            $error = 'Job not found or you do not have permission to edit this job.';
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_job'])) {
    $title = $_POST['title'];
    $location = $_POST['location'];
    $job_type = $_POST['job_type'];
    $description = $_POST['description'];

    if (empty($title) || empty($location) || empty($job_type) || empty($description)) {
        $error = 'All fields are required.';
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE jobs
                SET title = ?, location = ?, job_type = ?, description = ?
                WHERE job_id = ? AND employer_id = ?
            ");
            $stmt->execute([$title, $location, $job_type, $description, $job_id, $user_id]);

            // Log activity
            $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, activity_type, details) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, 'update_job', "Updated job $job_id"]);

            $success = 'Job updated successfully.';

            // Refresh job data
            $stmt = $pdo->prepare("SELECT * FROM jobs WHERE job_id = ? AND employer_id = ?");
            $stmt->execute([$job_id, $user_id]);
            $job = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $error = 'Error updating job: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Job - Retirement Plan</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
    body {
        display: flex;
        min-height: 100vh;
        background-color: #f8f9fa;
        margin: 0;
        font-family: 'Poppins', 'Roboto', sans-serif;
    }

    .content {
        flex: 1;
        margin-left: 250px; /* leave space for sidebar */
        padding: 0;
    }

    @media (max-width: 768px) {
        .content {
            margin-left: 0;
        }
    }

    /* Dashboard header - updated gradient to match admin/employer theme */
    .dashboard-header {
            background: linear-gradient(135deg, #800000 0%, #ff63a5 100%);
            color: white;
            padding: 25px 30px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .dashboard-header h1 {
        margin: 0;
        font-size: 26px;
        font-weight: 600;
    }

    .dashboard-content {
        max-width: 900px;
        margin: 30px auto;
        padding: 0 20px;
    }

    /* Back button remains gray */
    .back-btn {
        display: inline-block;
        margin-bottom: 20px;
        padding: 10px 15px;
        background: linear-gradient(135deg, #7f8c8d 0%, #95a5a6 100%);
        color: white;
        text-decoration: none;
        border-radius: 6px;
        font-weight: 500;
    }
    .back-btn:hover { opacity: 0.9; }

    /* Form card */
    .form-card {
        background-color: white;
        border-radius: 12px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        padding: 25px;
    }

    .form-card h2 {
        margin-bottom: 20px;
        color: #2c3e50;
        font-weight: 600;
    }

    .form-group { margin-bottom: 18px; }

    .form-group label {
        display: block;
        font-weight: 600;
        margin-bottom: 6px;
        color: #2c3e50;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 12px;
        border-radius: 6px;
        border: 1px solid #ddd;
        font-size: 1rem;
    }

    .form-group textarea { min-height: 140px; }

    /* Primary button - updated gradient to match dashboard */
    .btn-primary {
        background: linear-gradient(180deg, #800000 0%, #ff63a5 100%);
        color: white;
        padding: 12px 25px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 1rem;
        font-weight: 500;
        transition: all 0.3s ease;
    }
    .btn-primary:hover {
        background: linear-gradient(180deg, #ff63a5 0%, #800000 100%);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(128,0,0,0.4);
    }

    /* Success message - keep green gradient */
    .success {
        background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
        color: white;
        padding: 12px;
        border-radius: 6px;
        margin-bottom: 20px;
        font-weight: 500;
    }

    /* Error message - keep red gradient */
    .error {
        background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        color: white;
        padding: 12px;
        border-radius: 6px;
        margin-bottom: 20px;
        font-weight: 500;
    }
</style>

</head>
<body>
    <!-- Include Employer Sidebar -->
    <?php include 'sidebarEmployer.php'; ?>

    <!-- Main Content -->
    <div class="content">
        <div class="dashboard-header">
            <h1>Edit Job - Retirement Plan Portal</h1>
        </div>

        <div class="dashboard-content">
            <a href="viewJobE.php" class="back-btn">← Back to View Job</a>

            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if ($job): ?>
            <div class="form-card">
                <h2>Edit Job Details</h2>
                <form method="POST">
                    <div class="form-group">
                        <label for="title">Job Title</label>
                        <input type="text" name="title" id="title" value="<?php echo htmlspecialchars($job['title']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="location">Location</label>
                        <input type="text" name="location" id="location" value="<?php echo htmlspecialchars($job['location']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="job_type">Job Type</label>
                        <select name="job_type" id="job_type" required>
                            <option value="Full-time" <?php echo ($job['job_type'] === 'Full-time') ? 'selected' : ''; ?>>Full-time</option>
                            <option value="Part-time" <?php echo ($job['job_type'] === 'Part-time') ? 'selected' : ''; ?>>Part-time</option>
                            <option value="Contract" <?php echo ($job['job_type'] === 'Contract') ? 'selected' : ''; ?>>Contract</option>
                            <option value="Freelance" <?php echo ($job['job_type'] === 'Freelance') ? 'selected' : ''; ?>>Freelance</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="description">Job Description</label>
                        <textarea name="description" id="description" required><?php echo htmlspecialchars($job['description']); ?></textarea>
                    </div>

                    <button type="submit" name="update_job" class="btn-primary">Update Job</button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
