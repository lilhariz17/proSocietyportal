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
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Retirement Plan Portal</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        /* Reset default styles */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Poppins', 'Roboto', sans-serif;
    }

    /* Sidebar container for Retiree */
    .sidebar-retiree {
        width: 250px;
        /* Gradient background similar to Employer sidebar */
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

    /* Logo section */
    .logo-container {
        padding: 20px;
        text-align: center;
        border-bottom: 1px solid rgba(255,255,255,0.2); /* Light separation line */
    }

    .logo {
        font-weight: 600;
        font-size: 25px;
        color: white;
        letter-spacing: 0.5px;
    }

    /* Menu section container */
    .menu-section {
        margin-top: 20px;
        flex: 1;
    }

    /* Menu section title */
    .menu-title {
        padding: 10px 20px;
        font-size: 14px;
        font-weight: 600;
        color: rgba(255,255,255,0.85); /* Slightly transparent white for titles */
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    /* Menu items list */
    .menu-items {
        list-style-type: none;
    }

    /* Individual menu items */
    .menu-items li {
        padding: 12px 20px 12px 30px;
        transition: all 0.3s ease;
        cursor: pointer;
    }

    /* Hover effect for menu items */
    .menu-items li:hover {
        background-color: rgba(255, 255, 255, 0.15); /* Light overlay on hover */
    }

    /* Menu links */
    .menu-items li a {
        color: white;
        text-decoration: none;
        display: block;
        width: 100%;
        font-weight: 500;
        font-size: 15px;
    }

    /* Active menu item styling */
    .menu-items li.active {
        background-color: rgba(255, 255, 255, 0.25); /* Highlight active menu */
        border-left: 4px solid #ff63a5; /* Accent color matching gradient end */
    }

    .menu-items li.active a {
        font-weight: 600;
    }

    /* Logout button section at the bottom */
    .logout-container {
        margin-top: auto;
        padding: 20px;
        border-top: 1px solid rgba(255,255,255,0.2);
    }

    /* Logout button style */
    .logout-btn {
        display: block;
        width: 100%;
        padding: 12px;
        background-color: #e53e3e; /* Red logout button */
        color: white;
        text-align: center;
        text-decoration: none;
        border-radius: 6px;
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .logout-btn:hover {
        background-color: #c53030; /* Darker red on hover */
        transform: translateY(-2px);
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .sidebar-retiree {
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
    <!-- Sidebar Retiree -->
    <div class="sidebar-retiree">
        <div class="logo-container">
            <div class="logo">Pro Society Portal</div>
        </div>

        <div class="menu-section">
    <div class="menu-title">Retiree Menu</div>
    <ul class="menu-items">
        <!-- Dashboard -->
        <li class="<?php echo $current_page == 'retireeDash.php' ? 'active' : ''; ?>">
            <a href="retireeDash.php">Dashboard</a>
        </li>

        <!-- Search Job -->
        <li class="<?php echo $current_page == 'searchJob.php' ? 'active' : ''; ?>">
            <a href="searchJob.php">Search Job</a>
        </li>

        <!-- Edit Profile -->
        <li class="<?php echo $current_page == 'editProfileR.php' ? 'active' : ''; ?>">
            <a href="editProfileR.php">Edit Profile</a>
        </li>

        <!-- View Job -->
        <li class="<?php echo $current_page == 'viewJobR.php' ? 'active' : ''; ?>">
            <a href="viewJobR.php">View Job</a>
        </li>

        <!-- View Application (highlight for multiple related pages) -->
        <li class="<?php echo in_array($current_page, ['viewAllApplicationsR.php', 'viewApplicationR.php']) ? 'active' : ''; ?>">
            <a href="viewAllApplicationsR.php">View Application</a>
        </li>

        <!-- View History (highlight for multiple related pages) -->
        <li class="<?php echo in_array($current_page, ['viewApplicationsR.php', 'viewHistoryR.php']) ? 'active' : ''; ?>">
            <a href="viewApplicationsR.php">View History</a>
        </li>
    </ul>
</div>


        <div class="logout-container">
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
</body>
</html>
