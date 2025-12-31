<?php
// config.php - Database Configuration

// Start output buffering at the VERY TOP
if (ob_get_level() == 0) {
    ob_start();
}

// Error reporting for development (comment out in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Database connection parameters
define('DB_HOST', 'localhost');
define('DB_PORT', '5432');
define('DB_NAME', 'ADBS');
define('DB_USER', 'postgres');
define('DB_PASSWORD', '@rule1143');

// Create database connection
function getDBConnection() {
    try {
        $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
        $pdo = new PDO($dsn, DB_USER, DB_PASSWORD);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check if user is a seller
function isSeller() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'seller';
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        ob_clean(); // Clear any output
        header("Location: login.php");
        exit();
    }
}

// Redirect if not seller
function requireSeller() {
    requireLogin();
    if (!isSeller()) {
        ob_clean(); // Clear any output
        header("Location: dashboard.php");
        exit();
    }
}

// Sanitize input
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Flush output buffer at the end
function flushOutput() {
    ob_end_flush();
}
?>