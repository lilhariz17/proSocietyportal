<?php
// Database connection settings
$host = '127.0.0.1';
$dbname = 'retirement_plan'; 
$username = 'root';
$password = '';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Create connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Determine current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Retirement Plan Portal</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        /* Sidebar with Bright Maroon → Candy Pink gradient */
        .sidebar-admin {
            width: 250px;
            background: linear-gradient(180deg, #800000 0%, #ff63a5 100%);
            color: white;
            height: 100vh;
            position: fixed;
            overflow-y: auto;
            left: 0;
            top: 0;
            z-index: 1000;
            display: flex;
            flex-direction: column;
        }

        .logo-container {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }

        .logo {
            font-weight: 600;
            font-size: 24px;
            color: white;
            letter-spacing: 0.5px;
        }

        .menu-section {
            margin-top: 20px;
            flex: 1;
        }

        .menu-title {
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 600;
            color: rgba(255,255,255,0.85);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .menu-items {
            list-style-type: none;
        }

        .menu-items li {
            padding: 12px 20px 12px 30px;
            transition: all 0.3s;
            cursor: pointer;
        }

        .menu-items li:hover {
            background-color: rgba(255, 255, 255, 0.15);
        }

        .menu-items li a {
            color: white;
            text-decoration: none;
            display: block;
            width: 100%;
            font-weight: 500;
            font-size: 15px;
        }

        .menu-items li.active {
            background-color: rgba(255, 255, 255, 0.25);
            border-left: 4px solid #fff;
        }

        .menu-items li.active a {
            font-weight: 600;
        }

        .logout-container {
            margin-top: auto;
            padding: 20px;
            border-top: 1px solid rgba(255,255,255,0.2);
        }

        /* Logout button stays red */
        .logout-btn {
            display: block;
            width: 100%;
            padding: 12px;
            background-color: #e53e3e;
            color: white;
            text-align: center;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background-color: #c53030;
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .sidebar-admin {
                width: 100%;
                height: auto;
                position: relative;
            }
            .logout-container {
                position: static;
                margin-top: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar Admin -->
    <div class="sidebar-admin">
        <div class="logo-container">
            <div class="logo">Pro Society Portal</div>
        </div>
        
        <div class="menu-section">
            <div class="menu-title">Admin Menu</div>
            <ul class="menu-items">
                <li class="<?php echo $current_page == 'adminDash.php' ? 'active' : ''; ?>">
                    <a href="adminDash.php">Dashboard</a>
                </li>
                <li class="<?php echo $current_page == 'manageUser.php' ? 'active' : ''; ?>">
                    <a href="manageUser.php">User Management</a>
                </li>
                <li class="<?php echo $current_page == 'generateReport.php' ? 'active' : ''; ?>">
                    <a href="generateReport.php">Generate Reports</a>
                </li>
                <li class="<?php echo $current_page == 'systemSet.php' ? 'active' : ''; ?>">
                    <a href="systemSet.php">System Settings</a>
                </li>
                <li class="<?php echo $current_page == 'manageJob.php' ? 'active' : ''; ?>">
                    <a href="manageJob.php">Job Listing</a>
                </li>
            </ul>
        </div>
        
        <div class="logout-container">
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
</body>
</html>
