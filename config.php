<?php
session_start();

$host = "localhost";
$dbname = "gymnasium_db";
$username = "root";
$password = "";

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database connection failed. Check XAMPP MySQL.");
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function logged_in() {
    return isset($_SESSION['user']) || isset($_SESSION['attendee_id']);
}

function staff_only() {
    if (
        !isset($_SESSION['user']) ||
        !in_array($_SESSION['user']['Role'], ['Admin', 'Staff'])
    ) {
        header("Location: dashboard.php");
        exit;
    }
}
?>