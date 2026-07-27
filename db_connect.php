<?php
// Database configuration
$host = 'localhost';
$dbname = 'project'; // Replace with your actual database name in MySQL
$username = 'root';   // Default XAMPP username
$password = '';       // Default XAMPP password is empty

try {
    // Create a new PDO instance
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,          // Turn on error exceptions
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,     // Fetch associative arrays by default
        PDO::ATTR_EMULATE_PREPARES => false,                  // Use real prepared statements
    ]);
} catch (PDOException $e) {
    // Stop script and show connection error if it fails
    die("Database connection failed: " . $e->getMessage());
}