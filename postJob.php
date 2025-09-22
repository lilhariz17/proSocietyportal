<?php
session_start();
require_once 'config.php';

// Redirect to login if not authenticated or not employer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$email   = $_SESSION['email'] ?? null;
$error   = '';
$success = '';
$company = null;

try {
    // Try to get company data through employer_profiles
    $stmt = $pdo->prepare("
        SELECT c.*, ep.job_title, ep.company_id AS ep_company_id
        FROM employer_profiles ep 
        JOIN companies c ON ep.company_id = c.company_id 
        WHERE ep.employer_id = ?
    ");
    $stmt->execute([$user_id]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);

    // If not found, fallback to companies based on email
    if (!$company && $email) {
        $stmt = $pdo->prepare("SELECT * FROM companies WHERE contact_email = ?");
        $stmt->execute([$email]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($company) {
            if (!empty($company['company_id']) && $company['company_id'] > 0) {
                $stmt = $pdo->prepare("
                    INSERT IGNORE INTO employer_profiles (employer_id, company_id, job_title) 
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$user_id, $company['company_id'], 'Employer']);
            }

            $stmt = $pdo->prepare("
                UPDATE employer_profiles 
                SET company_id = ? 
                WHERE employer_id = ? AND company_id = 0
            ");
            $stmt->execute([$company['company_id'], $user_id]);

            $stmt = $pdo->prepare("
                SELECT c.*, ep.job_title, ep.company_id AS ep_company_id
                FROM employer_profiles ep 
                JOIN companies c ON ep.company_id = c.company_id 
                WHERE ep.employer_id = ?
            ");
            $stmt->execute([$user_id]);
            $company = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }

    if (!$company) {
        $error = 'Company information not found. Please complete your employer profile first.';
    }
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title        = trim($_POST['title'] ?? '');
    $description  = trim($_POST['description'] ?? '');
    $job_type     = $_POST['job_type'] ?? '';
    $location     = $_POST['location'] ?? ($company ? $company['location'] : '');
    $schedule     = $_POST['schedule'] ?? '';
    $salary_range = $_POST['salary_range'] ?? '';

    if (empty($title) || empty($description) || empty($job_type) || empty($location) || empty($schedule)) {
        $error = 'Please fill in all required fields.';
    } elseif (strlen($title) < 2) {
        $error = 'Job title must be at least 2 characters long.';
    } elseif (strlen($description) < 10) {
        $error = 'Job description must be at least 10 characters long.';
    } elseif (!$company) {
        $error = 'Cannot post job without company information. Please complete your employer profile first.';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO jobs (company_id, employer_id, title, description, job_type, location, schedule, salary_range, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            $result = $stmt->execute([
                $company['company_id'],
                $user_id,
                $title,
                $description,
                $job_type,
                $location,
                $schedule,
                $salary_range
            ]);

            if ($result) {
                $success = 'Job posted successfully! It will be reviewed by administrators before being published.';
                $_POST = []; // Clear form
            } else {
                $error = 'Error posting job. Please try again.';
            }
        } catch (PDOException $e) {
            $error = 'Error posting job: ' . $e->getMessage();
        }
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post Job - Retirement Plan</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
   <style>
    /* === Layout === */
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

    /* Dashboard header - updated gradient to match dashboard theme */
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
        max-width: 1000px;
        margin: 30px auto;
        padding: 0 20px;
    }

    .job-form {
        background: white;
        border-radius: 12px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        padding: 30px;
    }

    .form-group {
        margin-bottom: 25px;
    }

    label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #2c3e50;
    }

    input[type="text"], textarea, select {
        width: 100%;
        padding: 12px 15px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-family: inherit;
        font-size: 1em;
        transition: border-color 0.3s ease;
    }

    input[type="text"]:focus, textarea:focus, select:focus {
        border-color: #ff63a5; /* matching theme highlight */
        outline: none;
        box-shadow: 0 0 0 3px rgba(255, 99, 165, 0.1);
    }

    textarea {
        min-height: 120px;
        resize: vertical;
    }

    /* Primary button - updated to match dashboard theme */
    .btn-primary {
        background: linear-gradient(135deg, #800000 0%, #ff63a5 100%);
        color: white;
        padding: 12px 25px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 500;
        font-size: 1em;
        text-decoration: none;
        display: inline-block;
        text-align: center;
        transition: all 0.3s ease;
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #ff63a5 0%, #800000 100%);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(128, 0, 0, 0.4);
    }

    /* Secondary button - keep gray gradient */
    .btn-secondary {
        background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
        color: white;
    }

    .btn-secondary:hover {
        background: linear-gradient(135deg, #7f8c8d 0%, #95a5a6 100%);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(127, 140, 141, 0.4);
    }

    /* Reset button same size as primary button */
    .btn-reset {
        background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        color: white;
        padding: 12px 25px; /* match .btn-primary padding */
        border: none;
        border-radius: 6px; /* match .btn-primary */
        font-size: 1rem;    /* match .btn-primary */
        font-weight: 500;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
        text-align: center;
        transition: all 0.3s ease;
    }

    .btn-reset:hover {
        background: linear-gradient(135deg, #c0392b 0%, #e74c3c 100%);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(192, 57, 43, 0.4);
    }


    /* Messages remain unchanged */
    .message {
        padding: 15px;
        border-radius: 6px;
        margin-bottom: 25px;
        font-weight: 500;
    }

    .success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .error {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    /* Company info card - unchanged */
    .company-info {
        background: #ecf0f1;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
    }

    .company-info h3 {
        margin-bottom: 10px;
        font-size: 18px;
        font-weight: 600;
    }

    .company-info p {
        margin: 5px 0;
        color: #2c3e50;
    }
</style>

</head>
<body>
    <!-- Sidebar Employer -->
    <?php include 'sidebarEmployer.php'; ?>

    <!-- Main Content -->
    <div class="content">
        <div class="dashboard-header">
            <h1>Post a Job - Pro Society Portal</h1>
        </div>
        
        <div class="dashboard-content">
            <h2>Create New Job Posting</h2>

            <?php if ($error): ?><div class="message error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if ($success): ?><div class="message success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

            <?php if ($company): ?>
                <div class="company-info">
                    <h3>Posting as:</h3>
                    <p><strong>Company:</strong> <?= htmlspecialchars($company['company_name']) ?></p>
                    <p><strong>Your Role:</strong> <?= htmlspecialchars($company['job_title'] ?? 'Employer') ?></p>
                    <p><strong>Industry:</strong> <?= htmlspecialchars($company['industry']) ?></p>
                    <p><strong>Location:</strong> <?= htmlspecialchars($company['location']) ?></p>
                </div>
            <?php endif; ?>

            <div class="job-form">
                <form method="POST" id="jobForm">
                    <div class="form-group">
                        <label for="title">Job Title *</label>
                        <input type="text" id="title" name="title"
                               value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="description">Job Description *</label>
                        <textarea id="description" name="description" required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="job_type">Job Type *</label>
                        <select id="job_type" name="job_type" required>
                            <option value="">Select Job Type</option>
                            <option value="Full-time" <?= ($_POST['job_type'] ?? '') === 'Full-time' ? 'selected' : '' ?>>Full-time</option>
                            <option value="Part-time" <?= ($_POST['job_type'] ?? '') === 'Part-time' ? 'selected' : '' ?>>Part-time</option>
                            <option value="Volunteer" <?= ($_POST['job_type'] ?? '') === 'Volunteer' ? 'selected' : '' ?>>Volunteer</option>
                            <option value="Freelance" <?= ($_POST['job_type'] ?? '') === 'Freelance' ? 'selected' : '' ?>>Freelance</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="schedule">Work Schedule *</label>
                        <select id="schedule" name="schedule" required>
                            <option value="">Select Schedule</option>
                            <option value="Monday to Friday" <?= ($_POST['schedule'] ?? '') === 'Monday to Friday' ? 'selected' : '' ?>>Monday to Friday</option>
                            <option value="Shift work" <?= ($_POST['schedule'] ?? '') === 'Shift work' ? 'selected' : '' ?>>Shift work</option>
                            <option value="Flexible hours" <?= ($_POST['schedule'] ?? '') === 'Flexible hours' ? 'selected' : '' ?>>Flexible hours</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="location">Location *</label>
                        <input type="text" id="location" name="location"
                               value="<?= htmlspecialchars($_POST['location'] ?? ($company['location'] ?? '')) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="salary_range">Salary Range (Optional)</label>
                        <input type="text" id="salary_range" name="salary_range"
                               value="<?= htmlspecialchars($_POST['salary_range'] ?? '') ?>">
                    </div>
                    <button type="submit" class="btn btn-primary" <?= !$company ? 'disabled' : '' ?>>Post Job</button>
                    <button type="reset" class="btn-reset" id="resetBtn">Reset</button>
                </form>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const resetBtn = document.getElementById('resetBtn');
        const successAlert = document.querySelector('.success');

        // === Confirmation before reset ===
        resetBtn.addEventListener('click', function (e) {
            if (!confirm("⚠️ Are you sure you want to reset the form? All unsaved changes will be lost.")) {
                e.preventDefault();
            }
        });

        // === Auto-hide success alert ===
        if (successAlert) {
            setTimeout(() => {
                successAlert.style.transition = "opacity 0.5s ease";
                successAlert.style.opacity = "0";
                setTimeout(() => successAlert.remove(), 500);
            }, 3000);
        }
    });
    </script>
</body>
</html>
