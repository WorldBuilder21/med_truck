<?php
session_start();
require_once '../db/config.php';

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: ../views/auth/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hospital_id'])) {
    try {
        $conn->begin_transaction();

        // Check if there's already a pending request for this doctor and hospital
        $stmt = $conn->prepare("
            SELECT request_id, status 
            FROM hospital_enrollment_requests 
            WHERE doctor_id = ? AND hospital_id = ? AND status = 'pending'
        ");
        $stmt->bind_param("ii", $_SESSION['user_id'], $_POST['hospital_id']);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            throw new Exception("You already have a pending request for this hospital.");
        }

        // Create new enrollment request
        $stmt = $conn->prepare("
            INSERT INTO hospital_enrollment_requests (doctor_id, hospital_id, notes) 
            VALUES (?, ?, ?)
        ");
        $notes = isset($_POST['notes']) ? $_POST['notes'] : null;
        $stmt->bind_param("iis", $_SESSION['user_id'], $_POST['hospital_id'], $notes);
        $stmt->execute();

        $conn->commit();
        $_SESSION['success'] = "Enrollment request sent successfully. Please wait for admin approval.";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = $e->getMessage();
    }

    header("Location: ../views/doctor/hospitals.php");
    exit();
}

header("Location: ../views/doctor/hospitals.php");
exit();
