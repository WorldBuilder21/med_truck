<?php
// update_hospital.php
session_start();
require_once '../db/config.php';

if ($_SERVER['REQUEST_METHOD'] != 'POST' || !isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../views/auth/login.php');
    exit();
}

try {
    // Sanitize input
    $hospitalName = trim(htmlspecialchars($_POST['hospital_name']));
    $contactNumber = trim(htmlspecialchars($_POST['contact_number']));
    $hospitalEmail = filter_var($_POST['hospital_email'], FILTER_SANITIZE_EMAIL);
    $address = trim(htmlspecialchars($_POST['address']));

    // Validate email
    if (!filter_var($hospitalEmail, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }

    // Begin transaction
    $conn->begin_transaction();

    // Update hospital information
    $stmt = $conn->prepare("
        UPDATE hospitals 
        SET name = ?, contact_number = ?, email = ?, address = ? 
        WHERE hospital_id = ?
    ");
    $stmt->bind_param("ssssi", $hospitalName, $contactNumber, $hospitalEmail, $address, $_SESSION['hospital_id']);
    $stmt->execute();

    if (isset($_SESSION['hospital_name'])) {
        $_SESSION['hospital_name'] = $hospitalName;
    }

    // Commit transaction
    $conn->commit();

    $_SESSION['success'] = 'Hospital information updated successfully';
    header('Location: ../views/admin/admin_account_settings.php');
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    $_SESSION['error'] = $e->getMessage();
    header('Location: ../views/admin/admin_account_settings.php');
}
