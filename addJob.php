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
$companies = [];
$employers = [];

// Get companies and employers for dropdowns
try {
    $stmt = $pdo->prepare("SELECT company_id, company_name FROM companies ORDER BY company_name");
    $stmt->execute();
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("SELECT u.user_id, u.full_name, c.company_name 
                          FROM users u 
                          JOIN employer_profiles ep ON u.user_id = ep.employer_id 
                          JOIN companies c ON ep.company_id = c.company_id 
                          WHERE u.role = 'employer' 
                          ORDER BY u.full_name");
    $stmt->execute();
    $employers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_id = $_POST['company_id'] ?? '';
    $employer_id = $_POST['employer_id'] ?? '';
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $job_type = $_POST['job_type'] ?? '';
    $location = $_POST['location'] ?? '';
    $schedule = $_POST['schedule'] ?? '';
    
    // Validate inputs
    if (empty($company_id) || empty($employer_id) || empty($title) || empty($description) || 
        empty($job_type) || empty($location) || empty($schedule)) {
        $error = 'All fields are required.';
    } else {
        try {
            // Insert new job
            $stmt = $pdo->prepare("
                INSERT INTO jobs (company_id, employer_id, title, description, job_type, location, schedule, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'approved')
            ");
            $stmt->execute([$company_id, $employer_id, $title, $description, $job_type, $location, $schedule]);
            
            $job_id = $pdo->lastInsertId();
            
            // Log the activity
            $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, activity_type, details) VALUES (?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], 'create_job', "Created job: $title"]);
            
            $success = 'Job created successfully!';
            header("Refresh: 2; URL=viewJob.php?id=$job_id");
            
        } catch (PDOException $e) {
            $error = 'Error creating job: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Job - Retirement Plan</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .dashboard-header {
            background-color: #2c3e50;
            color: white;
            padding: 20px;
            text-align: center;
        }
        .dashboard-content {
            max-width: 800px;
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
        .job-form {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 25px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #2c3e50;
        }
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        .form-select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            background-color: white;
        }
        textarea.form-control {
            min-height: 150px;
            resize: vertical;
        }
        .form-help {
            font-size: 0.85rem;
            color: #7f8c8d;
            margin-top: 5px;
        }
        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            margin-right: 10px;
        }
        .btn-primary {
            background-color: #3498db;
            color: white;
        }
        .btn-primary:hover {
            background-color: #2980b9;
        }
        .btn-secondary {
            background-color: #7f8c8d;
            color: white;
        }
        .btn-secondary:hover {
            background-color: #95a5a6;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-header">
        <h1>Add New Job - Retirement Plan Portal</h1>
    </div>
    
    <div class="dashboard-content">
        <a href="manageJob.php" class="back-btn">← Back to Job List</a>
        
        <h2>Create New Job Posting</h2>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <div class="job-form">
            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="company_id">Company *</label>
                        <select id="company_id" name="company_id" class="form-select" required>
                            <option value="">Select Company</option>
                            <?php foreach ($companies as $company): ?>
                                <option value="<?php echo $company['company_id']; ?>" <?php echo isset($_POST['company_id']) && $_POST['company_id'] == $company['company_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($company['company_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="employer_id">Employer *</label>
                        <select id="employer_id" name="employer_id" class="form-select" required>
                            <option value="">Select Employer</option>
                            <?php foreach ($employers as $employer): ?>
                                <option value="<?php echo $employer['user_id']; ?>" <?php echo isset($_POST['employer_id']) && $_POST['employer_id'] == $employer['user_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($employer['full_name'] . ' - ' . $employer['company_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form    