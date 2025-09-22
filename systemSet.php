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
$settings = [];

// Default settings structure
$default_settings = [
    'site_name' => 'Retirement Plan Portal',
    'site_email' => 'admin@retirementplan.com',
    'items_per_page' => '10',
    'user_registration' => 'enabled',
    'job_post_approval' => 'disabled',
    'notifications_enabled' => 'enabled',
    'maintenance_mode' => 'disabled',
    'password_min_length' => '6',
    'session_timeout' => '30'
];

// Load current settings from database or use defaults
try {
    // In a real application, you would store settings in a database table
    // For this example, we'll simulate loading from a settings table
    $stmt = $pdo->prepare("SELECT * FROM settings");
    $stmt->execute();
    $db_settings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Convert database settings to key-value pairs
    foreach ($db_settings as $setting) {
        $settings[$setting['setting_name']] = $setting['setting_value'];
    }

    // Merge with defaults for any missing settings
    $settings = array_merge($default_settings, $settings);
} catch (PDOException $e) {
    // If settings table doesn't exist, use defaults
    $settings = $default_settings;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $site_name = $_POST['site_name'] ?? '';
    $site_email = $_POST['site_email'] ?? '';
    $items_per_page = $_POST['items_per_page'] ?? '';
    $user_registration = $_POST['user_registration'] ?? '';
    $job_post_approval = $_POST['job_post_approval'] ?? '';
    $notifications_enabled = $_POST['notifications_enabled'] ?? '';
    $maintenance_mode = $_POST['maintenance_mode'] ?? '';
    $password_min_length = $_POST['password_min_length'] ?? '';
    $session_timeout = $_POST['session_timeout'] ?? '';

    // Validate inputs
    if (empty($site_name) || empty($site_email)) {
        $error = 'Site name and email are required.';
    } elseif (!filter_var($site_email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!is_numeric($items_per_page) || $items_per_page < 5 || $items_per_page > 100) {
        $error = 'Items per page must be a number between 5 and 100.';
    } elseif (!is_numeric($password_min_length) || $password_min_length < 4 || $password_min_length > 20) {
        $error = 'Password minimum length must be between 4 and 20 characters.';
    } elseif (!is_numeric($session_timeout) || $session_timeout < 5 || $session_timeout > 1440) {
        $error = 'Session timeout must be between 5 and 1440 minutes.';
    } else {
        try {
            // In a real application, you would save to a settings table
            // For this example, we'll simulate saving to a database

            // Begin transaction
            $pdo->beginTransaction();

            // Clear existing settings
            $stmt = $pdo->prepare("DELETE FROM settings");
            $stmt->execute();

            // Prepare insert statement
            $stmt = $pdo->prepare("INSERT INTO settings (setting_name, setting_value) VALUES (?, ?)");

            // Save all settings
            $settings_to_save = [
                'site_name' => $site_name,
                'site_email' => $site_email,
                'items_per_page' => $items_per_page,
                'user_registration' => $user_registration,
                'job_post_approval' => $job_post_approval,
                'notifications_enabled' => $notifications_enabled,
                'maintenance_mode' => $maintenance_mode,
                'password_min_length' => $password_min_length,
                'session_timeout' => $session_timeout
            ];

            foreach ($settings_to_save as $name => $value) {
                $stmt->execute([$name, $value]);
            }

            $pdo->commit();

            // Update current settings
            $settings = $settings_to_save;

            // Log the activity
            $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, activity_type, details) VALUES (?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], 'update_settings', 'Updated system settings']);

            $success = 'System settings updated successfully.';
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Error saving settings: ' . $e->getMessage();
        }
    }
}

// Handle maintenance mode toggle
if (isset($_GET['toggle_maintenance'])) {
    try {
        $new_value = $settings['maintenance_mode'] === 'enabled' ? 'disabled' : 'enabled';
        $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_name = 'maintenance_mode'");
        $stmt->execute([$new_value]);

        $settings['maintenance_mode'] = $new_value;

        // Log the activity
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, activity_type, details) VALUES (?, ?, ?)");
        $action = $new_value === 'enabled' ? 'Enabled' : 'Disabled';
        $stmt->execute([$_SESSION['user_id'], 'toggle_maintenance', $action . ' maintenance mode']);

        $success = 'Maintenance mode ' . $new_value . '.';
        header("Location: systemSet.php?success=" . urlencode($success));
        exit;
    } catch (PDOException $e) {
        $error = 'Error toggling maintenance mode: ' . $e->getMessage();
    }
}

// Handle clear cache action
if (isset($_GET['clear_cache'])) {
    try {
        // In a real application, you would clear cached files/data here
        // For this example, we'll just log the action

        // Log the activity
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, activity_type, details) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], 'clear_cache', 'Cleared system cache']);

        $success = 'System cache cleared successfully.';
        header("Location: systemSet.php?success=" . urlencode($success));
        exit;
    } catch (PDOException $e) {
        $error = 'Error clearing cache: ' . $e->getMessage();
    }
}

// Determine current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Retirement Plan</title>
    <link rel="stylesheet" href="style.css">

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

        /* Dashboard Header */
        .dashboard-header {
            /* Changed gradient from blue-gray to maroon → pink */
            background: linear-gradient(135deg, #800000 0%, #ff4d88 100%);
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

        /* Dashboard Content */
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

        h2 {
            color: #2c3e50;
            margin-bottom: 25px;
            font-weight: 600;
            border-bottom: 2px solid #800000; /* only color changed */
            padding-bottom: 12px;
            font-size: 24px;
        }

        .error {
            background-color: #fee;
            color: #c33;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #c33;
            font-weight: 500;
        }

        .success {
            background-color: #efe;
            color: #363;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #363;
            font-weight: 500;
        }

        .settings-form {
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            padding: 25px;
            margin-bottom: 25px;
        }

        .form-section {
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .form-section:last-child {
            border-bottom: none;
        }

        .form-section h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            padding-bottom: 5px;
            border-bottom: 2px solid #800000;
            display: inline-block;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }

        .form-group label {
            width: 250px;
            font-weight: 600;
            color: #2c3e50;
        }

        .form-control {
            flex: 1;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            font-family: 'Poppins', sans-serif;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .form-select {
            flex: 1;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            background-color: white;
            font-family: 'Poppins', sans-serif;
            transition: border-color 0.3s ease;
        }

        .form-select:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .form-help {
            font-size: 0.85rem;
            color: #7f8c8d;
            margin-top: 5px;
            margin-left: 250px;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            margin-right: 15px;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;   /* remove underline */
            }

        /* Primary Button */
        .btn-primary {
            /* Updated to bright maroon → candy pink */
            background: linear-gradient(135deg, #800000 0%, #ff4d88 100%);
            color: white;
            box-shadow: 0 2px 5px rgba(128, 0, 0, 0.3); /* softer shadow in maroon */
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            /* Reverse gradient direction for hover effect */
            background: linear-gradient(135deg, #ff4d88 0%, #800000 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(128, 0, 0, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            color: white;
            box-shadow: 0 2px 5px rgba(46, 204, 113, 0.3);
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(46, 204, 113, 0.4);
        }

        .btn-warning {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            color: white;
            box-shadow: 0 2px 5px rgba(243, 156, 18, 0.3);
        }

        .btn-warning:hover {
            background: linear-gradient(135deg, #e67e22 0%, #f39c12 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(243, 156, 18, 0.4);
        }

        .btn-danger {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            box-shadow: 0 2px 5px rgba(231, 76, 60, 0.3);
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, #c0392b 0%, #e74c3c 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(231, 76, 60, 0.4);
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .action-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            padding: 20px;
            text-align: center;
            transition: transform 0.3s ease;
        }

        .action-card:hover {
            transform: translateY(-5px);
        }

        .action-card h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-weight: 600;
        }

        /* Add spacing for buttons inside action cards */
        .action-card .btn {
            margin-top: 12px;   /* creates a gap from the text above */
            display: inline-block;
        }


        .status-indicator {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            margin-bottom: 15px;
            font-weight: 500;
        }

        .status-enabled {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            color: white;
        }

        .status-disabled {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
        }

        .system-info {
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            padding: 25px;
            margin-top: 25px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 15px;
        }

        .info-item {
            padding: 20px;
            /* Changed background gradient from gray → light gray 
            to subtle maroon → pink tint */
            background: linear-gradient(135deg, #fff0f5 0%, #ffe6ee 100%);
            border-radius: 8px;
            /* Changed border-left highlight from blue to maroon */
            border-left: 4px solid #800000;
            transition: transform 0.3s ease;
        }

        .info-item:hover {
            transform: translateY(-3px);
        }

        .info-item h4 {
            margin: 0 0 5px 0;
            color: #2c3e50;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .info-item p {
            margin: 0;
            font-size: 1.2rem;
            font-weight: bold;
            /* Changed text color from blue to candy pink */
            color: #ff4d88;
        }

    </style>
</head>
<body>
    <!-- Include Sidebar -->
    <?php
    // Set current page for active menu highlighting
    $current_page = 'systemSet.php';
    include 'sidebarAdmin.php';
    ?>

    <!-- Main Content -->
    <div class="content">
        <div class="dashboard-header">
            <h1>System Settings - Pro Society Portal</h1>
        </div>

        <div class="dashboard-content">
            <h2>System Configuration</h2>

            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <div class="quick-actions">
                <div class="action-card">
                    <h3>Maintenance Mode</h3>
                    <span class="status-indicator status-<?php echo $settings['maintenance_mode'] === 'enabled' ? 'enabled' : 'disabled'; ?>">
                        <?php echo ucfirst($settings['maintenance_mode']); ?>
                    </span>
                    <p>When enabled, the site will be unavailable to regular users.</p>
                    <a href="systemSet.php?toggle_maintenance=true" class="btn btn-<?php echo $settings['maintenance_mode'] === 'enabled' ? 'success' : 'warning'; ?>">
                        <?php echo $settings['maintenance_mode'] === 'enabled' ? 'Disable' : 'Enable'; ?> Maintenance Mode
                    </a>
                </div>

                <div class="action-card">
                    <h3>System Cache</h3>
                    <p>Clear temporary cached files to refresh system data.</p>
                    <a href="systemSet.php?clear_cache=true" class="btn btn-warning">Clear System Cache</a>
                </div>

                <div class="action-card">
                    <h3>Backup Database</h3>
                    <p>Create a backup of the current database.</p>
                    <button class="btn btn-primary" onclick="alert('Database backup functionality would be implemented here.')">Backup Now</button>
                </div>
            </div>

            <form method="POST" action="" class="settings-form">
                <div class="form-section">
                    <h3>General Settings</h3>
                    <div class="form-group">
                        <label for="site_name">Site Name</label>
                        <input type="text" id="site_name" name="site_name" class="form-control" value="<?php echo htmlspecialchars($settings['site_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="site_email">Site Email</label>
                        <input type="email" id="site_email" name="site_email" class="form-control" value="<?php echo htmlspecialchars($settings['site_email']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="items_per_page">Items Per Page</label>
                        <input type="number" id="items_per_page" name="items_per_page" class="form-control" min="5" max="100" value="<?php echo htmlspecialchars($settings['items_per_page']); ?>" required>
                    </div>
                    <div class="form-help">Number of items to display per page in lists and tables</div>
                </div>

                <div class="form-section">
                    <h3>User Management</h3>
                    <div class="form-group">
                        <label for="user_registration">User Registration</label>
                        <select id="user_registration" name="user_registration" class="form-select">
                            <option value="enabled" <?php echo $settings['user_registration'] === 'enabled' ? 'selected' : ''; ?>>Enabled</option>
                            <option value="disabled" <?php echo $settings['user_registration'] === 'disabled' ? 'selected' : ''; ?>>Disabled</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="password_min_length">Password Minimum Length</label>
                        <input type="number" id="password_min_length" name="password_min_length" class="form-control" min="4" max="20" value="<?php echo htmlspecialchars($settings['password_min_length']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="session_timeout">Session Timeout (minutes)</label>
                        <input type="number" id="session_timeout" name="session_timeout" class="form-control" min="5" max="1440" value="<?php echo htmlspecialchars($settings['session_timeout']); ?>" required>
                    </div>
                    <div class="form-help">After how many minutes of inactivity should users be logged out</div>
                </div>

                <div class="form-section">
                    <h3>Job Management</h3>
                    <div class="form-group">
                        <label for="job_post_approval">Job Post Approval</label>
                        <select id="job_post_approval" name="job_post_approval" class="form-select">
                            <option value="enabled" <?php echo $settings['job_post_approval'] === 'enabled' ? 'selected' : ''; ?>>Enabled</option>
                            <option value="disabled" <?php echo $settings['job_post_approval'] === 'disabled' ? 'selected' : ''; ?>>Disabled</option>
                        </select>
                    </div>
                    <div class="form-help">When enabled, new job posts require admin approval before being published</div>
                </div>

                <div class="form-section">
                    <h3>Notifications</h3>
                    <div class="form-group">
                        <label for="notifications_enabled">Email Notifications</label>
                        <select id="notifications_enabled" name="notifications_enabled" class="form-select">
                            <option value="enabled" <?php echo $settings['notifications_enabled'] === 'enabled' ? 'selected' : ''; ?>>Enabled</option>
                            <option value="disabled" <?php echo $settings['notifications_enabled'] === 'disabled' ? 'selected' : ''; ?>>Disabled</option>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Save Settings</button>
                <button type="reset" class="btn btn-danger">Reset Changes</button>
            </form>

            <div class="system-info">
                <h3>System Information</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <h4>PHP Version</h4>
                        <p><?php echo phpversion(); ?></p>
                    </div>
                    <div class="info-item">
                        <h4>Database Version</h4>
                        <p><?php try { echo $pdo->getAttribute(PDO::ATTR_SERVER_VERSION); } catch (Exception $e) { echo 'Unknown'; } ?></p>
                    </div>
                    <div class="info-item">
                        <h4>Total Users</h4>
                        <p><?php try { $stmt = $pdo->query("SELECT COUNT(*) FROM users"); echo $stmt->fetchColumn(); } catch (Exception $e) { echo 'N/A'; } ?></p>
                    </div>
                    <div class="info-item">
                        <h4>Total Jobs</h4>
                        <p><?php try { $stmt = $pdo->query("SELECT COUNT(*) FROM jobs"); echo $stmt->fetchColumn(); } catch (Exception $e) { echo 'N/A'; } ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
