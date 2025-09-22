<?php
// Database connection settings
$host = '127.0.0.1';
$dbname = 'retirement_plan'; // Pastikan ini sesuai dengan nama database Anda
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

// Define other global variables if needed
// $Sconn = $pdo; // Jika ada kode yang masih menggunakan $Sconn
?>