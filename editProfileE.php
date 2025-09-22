<?php
session_start();
require_once 'config.php';

// Debug: Check if connection is established
if (!isset($pdo)) {
    die("Database connection not established. Check config.php");
}

// Redirect to login if not authenticated or not employer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$error_message = '';
$success_message = '';
$company = [];
$empProfile = [];

try {
    // Get company_id and job_title from employer_profiles
    $stmt = $pdo->prepare("SELECT company_id, job_title FROM employer_profiles WHERE employer_id = ?");
    $stmt->execute([$user_id]);

    if ($stmt->rowCount() > 0) {
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        $company_id = $profile['company_id'];
        $empProfile = $profile;

        if ($company_id == 0) {
            $stmt = $pdo->prepare("SELECT company_id FROM companies WHERE contact_email = ?");
            $stmt->execute([$_SESSION['email'] ?? '']);
            $company_fix = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($company_fix) {
                $company_id = $company_fix['company_id'];
                $stmt = $pdo->prepare("UPDATE employer_profiles SET company_id = ? WHERE employer_id = ?");
                $stmt->execute([$company_id, $user_id]);
            } else {
                // Insert new company with phone_number column
                $insert_company = "INSERT INTO companies (company_name, industry, location, website, contact_email, phone_number) 
                                  VALUES ('Your Company Name', 'technology', 'Unknown', '', :email, '')";
                $stmt = $pdo->prepare($insert_company);
                $stmt->execute([':email' => $_SESSION['email'] ?? 'email@example.com']);
                $company_id = $pdo->lastInsertId();

                $stmt = $pdo->prepare("UPDATE employer_profiles SET company_id = ? WHERE employer_id = ?");
                $stmt->execute([$company_id, $user_id]);
            }
        }

        $stmt = $pdo->prepare("SELECT * FROM companies WHERE company_id = ?");
        $stmt->execute([$company_id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

    } else {
        // Create new employer profile if missing
        $insert_company = "INSERT INTO companies (company_name, industry, location, website, contact_email, phone_number) 
                          VALUES ('Your Company Name', 'technology', 'Unknown', '', :email, '')";
        
        $stmt = $pdo->prepare($insert_company);
        $stmt->execute([':email' => $_SESSION['email'] ?? 'email@example.com']);
        
        $company_id = $pdo->lastInsertId();
        
        $insert_profile = "INSERT INTO employer_profiles (employer_id, company_id, job_title) 
                          VALUES (:user_id, :company_id, 'Employer')";
        $stmt = $pdo->prepare($insert_profile);
        $stmt->execute([':user_id' => $user_id, ':company_id' => $company_id]);
        
        $stmt = $pdo->prepare("SELECT * FROM companies WHERE company_id = ?");
        $stmt->execute([$company_id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        $empProfile['job_title'] = 'Employer';
    }
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
    error_log("Database error: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['company_name'])) {
    try {
        $name = htmlspecialchars($_POST['company_name']);
        $industry = htmlspecialchars($_POST['industry']);
        $location = htmlspecialchars($_POST['location'] ?? 'Unknown');
        $website = htmlspecialchars($_POST['website'] ?? '');
        $email = htmlspecialchars($_POST['email']);
        $phone = htmlspecialchars($_POST['phone_number']);
        $job_title = htmlspecialchars($_POST['job_title']); // ✅ New field

        if (!isset($company_id)) {
            $insert_company = "INSERT INTO companies (company_name, industry, location, website, contact_email, phone_number) 
                              VALUES (:name, :industry, :location, :website, :email, :phone)";
            
            $stmt = $pdo->prepare($insert_company);
            $stmt->execute([
                ':name' => $name,
                ':industry' => $industry,
                ':location' => $location,
                ':website' => $website,
                ':email' => $email,
                ':phone' => $phone
            ]);
            
            $company_id = $pdo->lastInsertId();
            
            $insert_profile = "INSERT INTO employer_profiles (employer_id, company_id, job_title) 
                              VALUES (:user_id, :company_id, :job_title)";
            $stmt = $pdo->prepare($insert_profile);
            $stmt->execute([':user_id' => $user_id, ':company_id' => $company_id, ':job_title' => $job_title]);
            
            $success_message = "Company profile created successfully!";
        } else {
            $update_sql = "UPDATE companies SET 
                            company_name = :name, 
                            industry = :industry, 
                            location = :location, 
                            website = :website, 
                            contact_email = :email,
                            phone_number = :phone 
                            WHERE company_id = :company_id";
            
            $stmt = $pdo->prepare($update_sql);
            $stmt->execute([
                ':name' => $name,
                ':industry' => $industry,
                ':location' => $location,
                ':website' => $website,
                ':email' => $email,
                ':phone' => $phone,
                ':company_id' => $company_id
            ]);

            // ✅ Update employer_profiles with job title
            $update_profile = "UPDATE employer_profiles SET job_title = :job_title WHERE employer_id = :user_id";
            $stmt = $pdo->prepare($update_profile);
            $stmt->execute([':job_title' => $job_title, ':user_id' => $user_id]);

            $success_message = "Company profile updated successfully!";
        }
        
        $stmt = $pdo->prepare("SELECT * FROM companies WHERE company_id = ?");
        $stmt->execute([$company_id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        $empProfile['job_title'] = $job_title;
        
    } catch (PDOException $e) {
        $error_message = "Error saving profile: " . $e->getMessage();
        error_log("Save error: " . $e->getMessage());
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Company Profile - Retirement Plan</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
    /* Reset and layout */
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', 'Roboto', sans-serif; }
    body { display: flex; min-height: 100vh; background-color: #f8f9fa; margin: 0; color: #333; }
    .content { flex: 1; margin-left: 250px; padding: 0; }
    @media (max-width: 768px) { .content { margin-left: 0; } }

    /* Dashboard header - updated to match sidebar/admin gradient */
    .dashboard-header {
            background: linear-gradient(135deg, #800000 0%, #ff63a5 100%);
            color: white;
            padding: 25px 30px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    .dashboard-header h1 { font-weight: 600; font-size: 28px; margin: 0; letter-spacing: 0.5px; }

    .dashboard-content { max-width: 1000px; margin: 30px auto; padding: 0 20px; }

    /* Profile form styling */
    .profile-form { 
        background: white; 
        border-radius: 12px; 
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08); 
        padding: 30px; 
    }
    .form-group { margin-bottom: 25px; }
    label { display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50; }

    input[type="text"], input[type="email"], input[type="url"], input[type="tel"], textarea, select {
        width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 6px; font-family: inherit; font-size: 1em; transition: border-color 0.3s ease;
    }
    input[type="text"]:focus, input[type="email"]:focus, input[type="url"]:focus, input[type="tel"]:focus, textarea:focus, select:focus {
        border-color: #800000; /* Focus border updated to match admin gradient */
        outline: none;
        box-shadow: 0 0 0 3px rgba(128, 0, 0, 0.1); /* Slight transparent overlay of maroon */
    }

    /* Button group */
    .button-group { display: flex; gap: 15px; flex-wrap: wrap; margin-top: 30px; }

    /* Primary button - updated to match admin gradient */
    .btn { padding: 12px 25px; border: none; border-radius: 6px; cursor: pointer; font-weight: 500; font-size: 1em; text-decoration: none; display: inline-block; text-align: center; transition: all 0.3s ease; }
    .btn-primary { 
        background: linear-gradient(180deg, #800000 0%, #ff63a5 100%); /* Bright Maroon → Candy Pink */
        color: white; 
    }
    .btn-primary:hover { 
        background: linear-gradient(180deg, #ff63a5 0%, #800000 100%); /* Reverse gradient on hover */
        transform: translateY(-2px); 
        box-shadow: 0 4px 8px rgba(128,0,0,0.4); 
    }

    /* Cancel button - keep original colors */
    .btn-cancel { 
        background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%); 
        color: white; 
    }
    .btn-cancel:hover { 
        background: linear-gradient(135deg, #7f8c8d 0%, #95a5a6 100%); 
        transform: translateY(-2px); 
        box-shadow: 0 4px 8px rgba(127, 140, 141, 0.4); 
    }

    /* Reset button - keep original colors */
    .btn-reset { 
        background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); 
        color: white; 
    }
    .btn-reset:hover { 
        background: linear-gradient(135deg, #c0392b 0%, #e74c3c 100%); 
        transform: translateY(-2px); 
        box-shadow: 0 4px 8px rgba(192, 57, 43, 0.4); 
    }

    /* Message alerts */
    .message { padding: 15px; border-radius: 6px; margin-bottom: 25px; font-weight: 500; }
    .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
</style>

</head>
<body>
    <?php include 'sidebarEmployer.php'; ?>
    
    <div class="content">
        <div class="dashboard-header">
            <h1>Edit Company Profile - Pro Society Portal</h1>
        </div>
        
        <div class="dashboard-content">
            <div class="profile-form">
                <?php if (!empty($success_message)): ?>
                    <div class="message success"><?php echo $success_message; ?></div>
                <?php endif; ?>
                
                <?php if (!empty($error_message)): ?>
                    <div class="message error"><?php echo $error_message; ?></div>
                <?php endif; ?>
                
                <form id="companyForm" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <div class="form-group">
                        <label for="company_name">Company Name *</label>
                        <input type="text" id="company_name" name="company_name" value="<?php echo htmlspecialchars($company['company_name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="industry">Industry *</label>
                        <select id="industry" name="industry" required>
                            <option value="">Select Industry</option>
                            <option value="Technology" <?php if (($company['industry'] ?? '') == 'Technology') echo 'selected'; ?>>Technology</option>
                            <option value="Healthcare" <?php if (($company['industry'] ?? '') == 'Healthcare') echo 'selected'; ?>>Healthcare</option>
                            <option value="Finance" <?php if (($company['industry'] ?? '') == 'Finance') echo 'selected'; ?>>Finance</option>
                            <option value="Education" <?php if (($company['industry'] ?? '') == 'Education') echo 'selected'; ?>>Education</option>
                            <option value="Manufacturing" <?php if (($company['industry'] ?? '') == 'Manufacturing') echo 'selected'; ?>>Manufacturing</option>
                            <option value="Retail" <?php if (($company['industry'] ?? '') == 'Retail') echo 'selected'; ?>>Retail</option>
                            <option value="Other" <?php if (($company['industry'] ?? '') == 'Other') echo 'selected'; ?>>Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="location">Location *</label>
                        <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($company['location'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="website">Website</label>
                        <input type="url" id="website" name="website" value="<?php echo htmlspecialchars($company['website'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($company['contact_email'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="phone_number">Phone Number *</label>
                        <input type="tel" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($company['phone_number'] ?? ''); ?>" required>
                    </div>

                    <!-- ✅ New Job Title Field -->
                    <div class="form-group">
                        <label for="job_title">Job Title *</label>
                        <select id="job_title" name="job_title" required>
                            <option value="">Select Job Title</option>
                            <option value="CEO" <?php if (($empProfile['job_title'] ?? '') === 'CEO') echo 'selected'; ?>>CEO</option>
                            <option value="Manager" <?php if (($empProfile['job_title'] ?? '') === 'Manager') echo 'selected'; ?>>Manager</option>
                            <option value="Supervisor" <?php if (($empProfile['job_title'] ?? '') === 'Supervisor') echo 'selected'; ?>>Supervisor</option>
                        </select>
                    </div>
                    
                    <div class="button-group">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        <button type="button" id="resetBtn" class="btn btn-reset">Reset Form</button>
                        <button type="button" class="btn btn-cancel" onclick="window.history.back();">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('companyForm');
        const resetBtn = document.getElementById('resetBtn');

        resetBtn.addEventListener('click', function () {
            if (confirm("⚠️ Are you sure you want to reset the form? All unsaved changes will be lost.")) {
                form.reset();
            }
        });

        form.addEventListener('submit', function (e) {
            let errors = [];
            const name = document.getElementById('company_name').value.trim();
            const industry = document.getElementById('industry').value.trim();
            const location = document.getElementById('location').value.trim();
            const email = document.getElementById('email').value.trim();
            const phone = document.getElementById('phone_number').value.trim();
            const jobTitle = document.getElementById('job_title').value.trim();

            if (name === "") errors.push("Company Name is required.");
            if (industry === "") errors.push("Industry is required.");
            if (location === "") errors.push("Location is required.");
            if (email === "") errors.push("Email is required.");
            if (phone === "") errors.push("Phone Number is required.");
            if (jobTitle === "") errors.push("Job Title is required.");

            if (errors.length > 0) {
                e.preventDefault();
                alert("⚠️ Please fix the following issues:\n\n" + errors.join("\n"));
            }
        });

        const successAlert = document.querySelector('.message.success');
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
