<?php
require_once 'config.php';

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Redirect to appropriate dashboard based on role
switch ($_SESSION['role']) {
    case 'admin':
        header('Location: adminDash.php');
        break;
    case 'retiree':
        header('Location: retireeDash.php');
        break;
    case 'employer':
        header('Location: employerDash.php');
        break;
    default:
        // If role is not recognized, log out the user
        session_destroy();
        header('Location: login.php');
        break;
}
exit;
?>