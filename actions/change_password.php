<?php
session_start();
require_once '../db/config.php';

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Location: ../../views/auth/forgot_password.php');
    exit();
}

try {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $passwordConfirmation = $_POST['password_confirmation'];

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }

    // Validate passwords match
    if ($password !== $passwordConfirmation) {
        throw new Exception('Passwords do not match');
    }

    // Validate password length
    if (strlen($password) < 8) {
        throw new Exception('Password must be at least 8 characters long');
    }

    // Check if user exists
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new Exception('No account found with this email address');
    }

    // Hash new password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Update password
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
    $stmt->execute([$hashedPassword, $user['user_id']]);

    // Log the password change
    $stmt = $pdo->prepare("
        INSERT INTO password_change_logs (user_id, changed_at, ip_address) 
        VALUES (?, NOW(), ?)
    ");
    $stmt->execute([$user['user_id'], $_SERVER['REMOTE_ADDR']]);

    // Set success message and redirect
    $_SESSION['success'] = 'Password has been reset successfully. Please login with your new password.';
    header('Location: ../views/auth/login.php');
    exit();
} catch (Exception $e) {
    header('Location: ../../views/auth/forgot_password.php?error=' . urlencode($e->getMessage()));
    exit();
}
