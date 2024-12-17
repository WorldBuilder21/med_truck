<?php
session_start();
require_once '../db/config.php';

if ($_SERVER['REQUEST_METHOD'] != 'POST' || !isset($_SESSION['user_id'])) {
    header('Location: ../views/auth/login.php');
    exit();
}

try {
    $conn->begin_transaction();

    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];

    // Validate password strength
    if (strlen($newPassword) < 8) {
        throw new Exception('Password must be at least 8 characters long');
    }

    if (!preg_match('/[A-Z]/', $newPassword)) {
        throw new Exception('Password must contain at least one uppercase letter');
    }

    if (!preg_match('/[a-z]/', $newPassword)) {
        throw new Exception('Password must contain at least one lowercase letter');
    }

    if (!preg_match('/[0-9]/', $newPassword)) {
        throw new Exception('Password must contain at least one number');
    }

    if ($newPassword !== $confirmPassword) {
        throw new Exception('New passwords do not match');
    }

    // Verify current password
    $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if (!password_verify($currentPassword, $result['password'])) {
        throw new Exception('Current password is incorrect');
    }

    // Update password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
    $stmt->bind_param("si", $hashedPassword, $_SESSION['user_id']);
    $stmt->execute();

    // Log password change
    $stmt = $conn->prepare("
        INSERT INTO password_change_logs (user_id, changed_at, ip_address) 
        VALUES (?, NOW(), ?)
    ");
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt->bind_param("is", $_SESSION['user_id'], $ip);
    $stmt->execute();

    $conn->commit();
    $_SESSION['success'] = 'Password updated successfully';
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = $e->getMessage();
}

header('Location: ../views/admin/admin_account_settings.php');
exit();
