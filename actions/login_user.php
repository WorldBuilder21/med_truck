<?php
session_start();
require_once '../db/config.php';

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Location: ../views/auth/login.php');
    exit();
}

try {
    // Get and sanitize input
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }

    // Prepare SQL statement to get user details
    $stmt = $conn->prepare("
        SELECT u.*, 
               CASE 
                   WHEN h.hospital_id IS NOT NULL THEN h.hospital_id
                   ELSE NULL 
               END as hospital_id
        FROM users u
        LEFT JOIN hospital_staff hs ON u.user_id = hs.user_id
        LEFT JOIN hospitals h ON hs.hospital_id = h.hospital_id
        WHERE u.email = ?
    ");

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        // Set session variables
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['email'] = $user['email'];

        // If it's an admin, store hospital ID
        if ($user['role'] === 'admin' && $user['hospital_id']) {
            $_SESSION['hospital_id'] = $user['hospital_id'];
        }

        // Log successful login
        $log_stmt = $conn->prepare("
            INSERT INTO login_logs (user_id, login_time, ip_address, success) 
            VALUES (?, NOW(), ?, 1)
        ");
        $ip = $_SERVER['REMOTE_ADDR'];
        $log_stmt->bind_param("is", $user['user_id'], $ip);
        $log_stmt->execute();

        // Redirect based on role
        switch ($user['role']) {
            case 'super_admin':
                header('Location: ../views/admin/dashboard.php');
                break;
            case 'admin':
                header('Location: ../views/admin/dashboard.php');
                break;
            case 'doctor':
                header('Location: ../views/doctor/hospitals.php');
                break;
            case 'patient':
                header('Location: ../views/patient/home_page.php');
                break;
            default:
                throw new Exception('Invalid user role');
        }
        exit();
    } else {
        // Log failed login attempt
        $log_stmt = $conn->prepare("
            INSERT INTO login_logs (email, login_time, ip_address, success) 
            VALUES (?, NOW(), ?, 0)
        ");
        $ip = $_SERVER['REMOTE_ADDR'];
        $log_stmt->bind_param("ss", $email, $ip);
        $log_stmt->execute();

        throw new Exception('Invalid credentials');
    }
} catch (Exception $e) {
    $_SESSION['login_error'] = $e->getMessage();
    header('Location: ../views/auth/login.php?error=1');
    exit();
}











