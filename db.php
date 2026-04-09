<?php
// db.php - Database connection setup
$host = 'localhost';
$dbname = 'eco_land';
$db_user = 'root'; // Change if your MySQL user is different
$db_pass = '';     // Change if your MySQL has a password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>