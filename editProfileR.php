<?php
session_start();
require_once 'config.php';

// === Ensure PDO connection exists ===
if (!isset($pdo) || !($pdo instanceof PDO)) {
    die("Database connection not established. Check config.php (expected \$pdo PDO instance).");
}

// === Redirect if not logged in or not retiree ===
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'retiree') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

$retiree_profile = null;

// === Fetch retiree profile (if exists) ===
try {
    $profile_query = "SELECT * FROM retiree_profiles WHERE retiree_id = ?";
    $stmt = $pdo->prepare($profile_query);
    $stmt->execute([$user_id]);
    $retiree_profile = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Database error (fetch profile): " . $e->getMessage();
    error_log($error_message);
}

// === Initialize form values (from DB or empty if new) ===
$name            = $retiree_profile['name'] ?? '';
$age             = $retiree_profile['age'] ?? '';
$location        = $retiree_profile['location'] ?? '';
$education       = $retiree_profile['education'] ?? '';
$skills          = $retiree_profile['skills'] ?? '';
$work_history    = $retiree_profile['work_history'] ?? '';
$job_preferences = $retiree_profile['job_preferences'] ?? '';
$phone_number    = $retiree_profile['phone_number'] ?? ''; // ✅ New column

// === Handle form submission ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name            = trim($_POST['name'] ?? '');
    $age             = trim($_POST['age'] ?? '');
    $location        = trim($_POST['location'] ?? '');
    $education       = trim($_POST['education'] ?? '');
    $skills          = trim($_POST['skills'] ?? '');
    $work_history    = trim($_POST['work_history'] ?? '');
    $job_preferences = trim($_POST['job_preferences'] ?? '');
    $phone_number    = trim($_POST['phone_number'] ?? ''); // ✅ Capture phone input

    // === Simple server-side validation ===
    if (empty($skills) || empty($job_preferences) || empty($name) || empty($age) || empty($location) || empty($phone_number)) {
        $error_message = "Full Name, Age, Location, Phone Number, Skills, and Job Preferences are required fields.";
    } else {
        try {
            if ($retiree_profile) {
                // === Update existing profile (include phone_number) ===
                $update_query = "UPDATE retiree_profiles 
                                 SET name = ?, age = ?, location = ?, education = ?, skills = ?, work_history = ?, job_preferences = ?, phone_number = ?
                                 WHERE retiree_id = ?";
                $stmt = $pdo->prepare($update_query);
                $ok = $stmt->execute([$name, $age, $location, $education, $skills, $work_history, $job_preferences, $phone_number, $user_id]);

                $success_message = $ok ? "Profile updated successfully!" : "Failed to update profile.";
            } else {
                // === Insert new profile (include phone_number) ===
                $insert_query = "INSERT INTO retiree_profiles 
                                 (retiree_id, name, age, location, education, skills, work_history, job_preferences, phone_number)
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($insert_query);
                $ok = $stmt->execute([$user_id, $name, $age, $location, $education, $skills, $work_history, $job_preferences, $phone_number]);

                $success_message = $ok ? "Profile created successfully!" : "Failed to create profile.";
            }

            // Refresh profile data
            $stmt = $pdo->prepare("SELECT * FROM retiree_profiles WHERE retiree_id = ?");
            $stmt->execute([$user_id]);
            $retiree_profile = $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            $error_message = "Error saving profile: " . $e->getMessage();
            error_log($error_message);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - Retirement Plan Portal</title>
    <style>
    /* General layout */
    body {
        background-color: #f4f6f8;
        margin: 0;
        padding: 0;
        font-family: 'Poppins','Roboto',sans-serif;
        color: #333;
        display: flex;
    }

    .content { flex: 1; margin-left: 250px; }
        @media (max-width: 768px) { .content { margin-left: 0; } }

    /* ✅ Unified dashboard header with sidebar gradient */
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

    /* Profile section card */
    .profile-section { 
        background-color: white; 
        border-radius: 8px; 
        padding: 25px; 
        box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
        margin-bottom: 30px; 
    }

    .profile-section h2 { 
        margin-bottom: 20px; 
        color: #2c3e50; 
        border-bottom: 2px solid #800000; /* Sidebar maroon for consistency */
        padding-bottom: 10px; 
    }

    .form-group { 
        margin-bottom: 20px; 
    }

    .form-group label { 
        display:block; 
        margin-bottom:8px; 
        font-weight:500; 
        color:#2c3e50; 
    }

    .form-group input, 
    .form-group textarea, 
    .form-group select {
        width:100%; 
        padding:12px; 
        border:1px solid #ddd; 
        border-radius:6px; 
        font-size:15px;
    }

    .form-group textarea { 
        min-height:100px; 
        resize: vertical; 
    }

    .form-actions { 
        display:flex; 
        justify-content:flex-end; 
        gap:15px; 
        margin-top:25px; 
    }

    /* Buttons */
    .btn { 
        padding: 10px 18px; 
        border: none; 
        border-radius: 6px; 
        cursor: pointer; 
        text-decoration: none; 
        font-size: 14px; 
        transition: all 0.3s ease; 
        font-weight: 500; 
    }

    /* Primary button with sidebar gradient */
    .btn-primary { 
        background: linear-gradient(135deg, #800000 0%, #ff63a5 100%);
        color: white; 
    }

    .btn-primary:hover { 
        background: linear-gradient(135deg, #ff63a5 0%, #800000 100%);
        transform: translateY(-2px); 
    }

    /* Secondary button remains unchanged */
    .btn-secondary { 
        background-color: #95a5a6; 
        color: white; 
    }

    .btn-secondary:hover { 
        background-color: #7f8c8d; 
        transform: translateY(-2px); 
    }

    /* Alerts */
    .alert { 
        padding:15px; 
        border-radius:6px; 
        margin-bottom:20px; 
        font-weight: 500; 
    }

    .alert-success { 
        background: linear-gradient(135deg, #800000 0%, #ff63a5 100%);
        color: white; 
        border: 1px solid rgba(255,255,255,0.3);
    }

    .alert-error { 
        background: linear-gradient(135deg, #ff63a5 0%, #800000 100%);
        color: white; 
        border: 1px solid rgba(255,255,255,0.3);
    }
</style>

</head>
<body>
    <!-- Sidebar -->
    <?php include 'sidebarRetiree.php'; ?>

    <div class="content">
        <!-- ✅ Unified header -->
        <div class="dashboard-header">
            <h1>Edit Profile - Pro Society Portal</h1>
        </div>

        <!-- Profile Form -->
        <section class="profile-section">
            <h2>Edit Your Personal Information & Preferences</h2>

            <!-- Success/Error Alerts -->
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <form method="POST" id="profileForm">
                <div class="form-group">
                    <label for="name">Full Name *</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
                </div>

                <div class="form-group">
                    <label for="age">Age *</label>
                    <input type="number" id="age" name="age" min="40" max="70" value="<?php echo htmlspecialchars($age); ?>" required>
                </div>

                <div class="form-group">
                    <label for="location">Location *</label>
                    <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($location); ?>" required>
                </div>

                <!-- ✅ New phone number field -->
                <div class="form-group">
                    <label for="phone_number">Phone Number *</label>
                    <input type="text" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($phone_number); ?>" required>
                </div>

                <div class="form-group">
                    <label for="education">Education</label>
                    <textarea id="education" name="education"><?php echo htmlspecialchars($education); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="skills">Skills *</label>
                    <textarea id="skills" name="skills" required><?php echo htmlspecialchars($skills); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="work_history">Work History</label>
                    <textarea id="work_history" name="work_history"><?php echo htmlspecialchars($work_history); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="job_preferences">Job Preferences *</label>
                    <select id="job_preferences" name="job_preferences" required>
                        <option value="">-- Select Job Type --</option>
                        <option value="Part Time" <?php if ($job_preferences=="Part Time") echo "selected"; ?>>Part Time</option>
                        <option value="Full Time" <?php if ($job_preferences=="Full Time") echo "selected"; ?>>Full Time</option>
                        <option value="Volunteer" <?php if ($job_preferences=="Volunteer") echo "selected"; ?>>Volunteer</option>
                        <option value="Freelance" <?php if ($job_preferences=="Freelance") echo "selected"; ?>>Freelance</option>
                    </select>
                </div>

                <div class="form-actions">
                    <a href="retireeDash.php" class="btn btn-secondary">Cancel</a>
                    <button type="button" id="resetBtn" class="btn btn-secondary">Reset Form</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </section>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('profileForm');
        const resetBtn = document.getElementById('resetBtn');

        // === Confirmation before reset ===
        resetBtn.addEventListener('click', function () {
            if (confirm("⚠️ Are you sure you want to reset the form? All unsaved changes will be lost.")) {
                form.reset();
            }
        });

        // === Client-side validation (extra check before submit) ===
        form.addEventListener('submit', function (e) {
            let errors = [];
            const name = document.getElementById('name').value.trim();
            const age = document.getElementById('age').value.trim();
            const location = document.getElementById('location').value.trim();
            const phone = document.getElementById('phone_number').value.trim(); // ✅ New validation
            const skills = document.getElementById('skills').value.trim();
            const jobPref = document.getElementById('job_preferences').value.trim();

            if (name === "") errors.push("Full Name is required.");
            if (age === "") errors.push("Age is required.");
            if (location === "") errors.push("Location is required.");
            if (phone === "") errors.push("Phone Number is required.");
            if (skills === "") errors.push("Skills are required.");
            if (jobPref === "") errors.push("Job Preferences are required.");

            if (errors.length > 0) {
                e.preventDefault();
                alert("⚠️ Please fix the following issues:\n\n" + errors.join("\n"));
            }
        });

        // === Auto-hide success alert after 3 seconds ===
        const successAlert = document.querySelector('.alert-success');
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
