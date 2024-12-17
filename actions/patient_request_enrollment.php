<?php
require_once __DIR__ . '/../db/config.php';
session_start();

// Verify user is authenticated and is a patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Unauthorized access";
    header("Location: ../views/auth/login.php");
    exit();
}

try {
    // Get and validate hospital ID
    $hospital_id = filter_input(INPUT_POST, 'hospital_id', FILTER_VALIDATE_INT);
    if (!$hospital_id) {
        throw new Exception('Invalid hospital selection');
    }

    // Get patient ID
    $patient_id = $_SESSION['user_id'];

    // Start transaction
    $conn->begin_transaction();

    // Check if patient already has a pending request at this hospital
    $stmt = $conn->prepare("
        SELECT request_id 
        FROM patient_enrollment_requests 
        WHERE patient_id = ? AND hospital_id = ? AND status = 'pending'
    ");
    $stmt->bind_param("ii", $patient_id, $hospital_id);
    $stmt->execute();

    if ($stmt->get_result()->num_rows > 0) {
        throw new Exception('You already have a pending request at this hospital');
    }

    // Check if patient is already assigned to any hospital
    $stmt = $conn->prepare("
        SELECT assignment_id 
        FROM doctor_patient_assignments 
        WHERE patient_id = ? AND status = 'active'
    ");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();

    if ($stmt->get_result()->num_rows > 0) {
        throw new Exception('You are already enrolled at a hospital. Please contact your current hospital for any changes.');
    }

    // Create the enrollment request
    $stmt = $conn->prepare("
        INSERT INTO patient_enrollment_requests 
        (patient_id, hospital_id, request_date, status) 
        VALUES (?, ?, NOW(), 'pending')
    ");
    $stmt->bind_param("ii", $patient_id, $hospital_id);

    if (!$stmt->execute()) {
        throw new Exception('Failed to submit enrollment request. Please try again later.');
    }

    // Get hospital name for success message
    $stmt = $conn->prepare("SELECT name FROM hospitals WHERE hospital_id = ?");
    $stmt->bind_param("i", $hospital_id);
    $stmt->execute();
    $hospital = $stmt->get_result()->fetch_assoc();

    // Log the request in login_logs
    $stmt = $conn->prepare("
        INSERT INTO login_logs 
        (user_id, login_time, ip_address, success) 
        VALUES (?, NOW(), ?, 1)
    ");
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt->bind_param("is", $patient_id, $ip);
    $stmt->execute();

    // Commit transaction
    $conn->commit();

    $_SESSION['success'] = "Your enrollment request has been submitted to " . htmlspecialchars($hospital['name']) .
        ". The hospital will review your request and assign you to a doctor.";
} catch (Exception $e) {
    // Rollback transaction on error
    try {
        $conn->rollback();
    } catch (Exception $rollbackError) {
        // Log rollback error but keep original error message
        error_log("Rollback failed: " . $rollbackError->getMessage());
    }

    $_SESSION['error'] = $e->getMessage();

    // Log the error
    error_log("Error in patient_request_enrollment.php: " . $e->getMessage());

    // Log failed attempt
    $stmt = $conn->prepare("
        INSERT INTO login_logs 
        (user_id, login_time, ip_address, success) 
        VALUES (?, NOW(), ?, 0)
    ");
    $ip = $_SERVER['REMOTE_ADDR'];
    if (isset($patient_id)) {
        $stmt->bind_param("is", $patient_id, $ip);
        $stmt->execute();
    }
} finally {
    // Close any open statements
    if (isset($stmt)) {
        $stmt->close();
    }

    // Close the database connection
    $conn->close();
}

// Redirect back to the hospitals page
header("Location: ../views/patient/home_page.php");
exit();
