<?php
session_start();
require_once '../db/config.php';

if ($_SERVER['REQUEST_METHOD'] != 'POST' || !isset($_SESSION['user_id'])) {
    header('Location: ../views/auth/login.php');
    exit();
}

try {
    $conn->begin_transaction();

    $password = $_POST['password'];

    // Verify password
    $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if (!password_verify($password, $result['password'])) {
        throw new Exception('Incorrect password');
    }

    // Delete role-specific data first (due to foreign key constraints)
    switch ($_SESSION['role']) {
        case 'doctor':
            // Delete doctor's prescriptions
            $stmt = $conn->prepare("UPDATE prescriptions SET status = 'cancelled' WHERE doctor_id = ?");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();

            // Delete from doctor_details
            $stmt = $conn->prepare("DELETE FROM doctor_details WHERE user_id = ?");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();

            // Remove from hospital_staff
            $stmt = $conn->prepare("DELETE FROM hospital_staff WHERE user_id = ?");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();

            // Delete pending enrollment requests
            $stmt = $conn->prepare("DELETE FROM hospital_enrollment_requests WHERE doctor_id = ?");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            break;

        case 'patient':
            // Update prescriptions status
            $stmt = $conn->prepare("UPDATE prescriptions SET status = 'cancelled' WHERE patient_id = ?");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();

            // Delete from patient_details
            $stmt = $conn->prepare("DELETE FROM patient_details WHERE user_id = ?");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();

            // Delete alert settings
            $stmt = $conn->prepare("DELETE FROM alert_settings WHERE user_id = ?");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            break;

        case 'admin':
            // An admin cannot delete their account if they're the only admin for their hospital
            $stmt = $conn->prepare("
                SELECT COUNT(*) as admin_count 
                FROM hospital_staff 
                WHERE hospital_id = ? AND position = 'Administrator'
            ");
            $stmt->bind_param("i", $_SESSION['hospital_id']);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();

            if ($result['admin_count'] <= 1) {
                throw new Exception('Cannot delete account: You are the only administrator for your hospital');
            }

            // Delete from hospital_staff
            $stmt = $conn->prepare("DELETE FROM hospital_staff WHERE user_id = ?");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            break;
    }

    // Delete user's login logs
    $stmt = $conn->prepare("DELETE FROM login_logs WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();

    // Delete password change logs
    $stmt = $conn->prepare("DELETE FROM password_change_logs WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();

    // Finally, delete the user
    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();

    $conn->commit();

    // Clear session and redirect to login
    session_destroy();
    header('Location: ../views/auth/login.php?message=Account+deleted+successfully');
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = $e->getMessage();
    header('Location: ../views/admin/admin_account_settings.php');
}
exit();
