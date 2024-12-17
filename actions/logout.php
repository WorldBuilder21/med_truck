<?php
session_start();
require_once __DIR__ . './../db/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_logout'])) {
    // Log the logout action
    if (isset($_SESSION['user_id'])) {
        $stmt = $conn->prepare("INSERT INTO login_logs (user_id, email, login_time, ip_address, success) 
                               VALUES (?, ?, NOW(), ?, 1)");
        $stmt->bind_param("iss", $_SESSION['user_id'], $_SESSION['email'], $_SERVER['REMOTE_ADDR']);
        $stmt->execute();
        $stmt->close();
    }

    // Destroy the session
    session_destroy();

    // Clear the session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }

    // Close database connection
    $conn->close();

    // Send JSON response for AJAX requests
    if (
        !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'
    ) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'redirect' => '../views/auth/login.php']);
        exit;
    }

    // Regular form submission redirect
    header("Location: ../views/auth/login.php");
    exit();
} else {
    // Invalid request
    header("Location: ../views/auth/login.php");
    exit();
}
