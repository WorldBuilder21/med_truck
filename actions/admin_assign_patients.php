<?php
session_start();
require_once '../db/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../views/auth/login.php");
    exit();
}

try {
    $conn->begin_transaction();

    $patientId = filter_var($_POST['patient_id'], FILTER_VALIDATE_INT);
    $doctorId = filter_var($_POST['doctor_id'], FILTER_VALIDATE_INT);
    $notes = trim(htmlspecialchars($_POST['notes'] ?? ''));

    if (!$patientId || !$doctorId) {
        throw new Exception('Invalid patient or doctor selection');
    }

    // Verify doctor belongs to admin's hospital
    $stmt = $conn->prepare("
        SELECT hs.hospital_id 
        FROM hospital_staff hs
        WHERE hs.user_id = ? AND hs.hospital_id = ?
    ");
    $stmt->bind_param("ii", $doctorId, $_SESSION['hospital_id']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        throw new Exception('Selected doctor is not part of your hospital');
    }

    // Check if patient already has an active assignment
    $stmt = $conn->prepare("
        SELECT dpa.assignment_id, u.first_name, u.last_name 
        FROM doctor_patient_assignments dpa
        JOIN users u ON dpa.doctor_id = u.user_id
        WHERE dpa.patient_id = ? AND dpa.status = 'active'
    ");
    $stmt->bind_param("i", $patientId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $currentAssignment = $result->fetch_assoc();
        throw new Exception('Patient already assigned to Dr. ' .
            $currentAssignment['first_name'] . ' ' . $currentAssignment['last_name']);
    }

    // Create assignment
    $stmt = $conn->prepare("
        INSERT INTO doctor_patient_assignments 
            (doctor_id, patient_id, assigned_by, notes)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("iiis", $doctorId, $patientId, $_SESSION['user_id'], $notes);
    $stmt->execute();

    // Log the assignment
    $stmt = $conn->prepare("
        INSERT INTO login_logs 
            (user_id, email, login_time, ip_address, success, description) 
        VALUES (?, NULL, NOW(), ?, 1, ?)
    ");
    $ip = $_SERVER['REMOTE_ADDR'];
    $description = "Patient (ID: $patientId) assigned to Doctor (ID: $doctorId)";
    $stmt->bind_param("iss", $_SESSION['user_id'], $ip, $description);
    $stmt->execute();

    $conn->commit();
    $_SESSION['success'] = "Patient assigned successfully";
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = $e->getMessage();
}

header("Location: ../views/admin/manage_assignments.php");
exit();
