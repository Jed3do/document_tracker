<?php
session_start();

// 1. SET PHP TIMEZONE
// This ensures all date() and time() functions in PHP use Philippine Standard Time.
date_default_timezone_set('Asia/Manila');

// Database configuration
define('DB_HOST', 'localhost');
define('DB_PORT', '5432');
define('DB_NAME', 'DOCUMENT_TRACKERV2');
define('DB_USER', 'postgres');
define('DB_PASS', 'dolphin1561'); 

// Create connection
try {
    $pdo = new PDO("pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME, 
                   DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 2. SET DATABASE TIMEZONE
    // This ensures that PostgreSQL functions like CURRENT_TIMESTAMP or NOW() 
    // also use the correct local time.
    $pdo->exec("SET TIME ZONE 'Asia/Manila'");

} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>