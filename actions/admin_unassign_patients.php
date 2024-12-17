<?php
session_start();
require_once '../db/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../views/auth/login.php");
    exit();
}

try {
    $conn->begin_transaction();

    // Validate assignment ID
    if (!isset($_POST['assignment_id']) || !is_numeric($_POST['assignment_id'])) {
        throw new Exception('Invalid assignment ID');
    }

    $assignmentId = intval($_POST['assignment_id']);

    // Verify the assignment belongs to a doctor in admin's hospital
    $stmt = $conn->prepare("
        SELECT dpa.*, 
               d.first_name as doctor_first_name, 
               d.last_name as doctor_last_name,
               p.first_name as patient_first_name,
               p.last_name as patient_last_name
        FROM doctor_patient_assignments dpa
        JOIN users d ON dpa.doctor_id = d.user_id
        JOIN users p ON dpa.patient_id = p.user_id
        JOIN hospital_staff hs ON dpa.doctor_id = hs.user_id
        WHERE dpa.assignment_id = ? 
        AND hs.hospital_id = ? 
        AND dpa.status = 'active'
    ");
    $stmt->bind_param("ii", $assignmentId, $_SESSION['hospital_id']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Assignment not found or not authorized to modify');
    }

    $assignment = $result->fetch_assoc();

    // Check for active prescriptions
    $stmt = $conn->prepare("
        SELECT COUNT(*) as active_prescriptions
        FROM prescriptions 
        WHERE doctor_id = ? 
        AND patient_id = ? 
        AND status = 'active'
    ");
    $stmt->bind_param("ii", $assignment['doctor_id'], $assignment['patient_id']);
    $stmt->execute();
    $prescriptionCount = $stmt->get_result()->fetch_assoc()['active_prescriptions'];

    // If there are active prescriptions, mark them as completed
    if ($prescriptionCount > 0) {
        $stmt = $conn->prepare("
            UPDATE prescriptions 
            SET status = 'completed',
                updated_at = CURRENT_TIMESTAMP,
                notes = CONCAT(IFNULL(notes, ''), '\nAutomatically completed due to patient unassignment by admin on ', NOW())
            WHERE doctor_id = ? 
            AND patient_id = ? 
            AND status = 'active'
        ");
        $stmt->bind_param("ii", $assignment['doctor_id'], $assignment['patient_id']);
        $stmt->execute();
    }

    // Update assignment status to inactive
    $stmt = $conn->prepare("
        UPDATE doctor_patient_assignments 
        SET status = 'inactive',
            notes = CONCAT(IFNULL(notes, ''), '\nUnassigned by admin on ', NOW())
        WHERE assignment_id = ?
    ");
    $stmt->bind_param("i", $assignmentId);
    $stmt->execute();

    // Log the unassignment
    $stmt = $conn->prepare("
        INSERT INTO login_logs 
            (user_id, email, login_time, ip_address, success, description) 
        VALUES (?, NULL, NOW(), ?, 1, ?)
    ");
    $ip = $_SERVER['REMOTE_ADDR'];
    $description = "Unassigned patient " . $assignment['patient_first_name'] . " " . $assignment['patient_last_name'] .
        " from Dr. " . $assignment['doctor_first_name'] . " " . $assignment['doctor_last_name'];
    $stmt->bind_param("iss", $_SESSION['user_id'], $ip, $description);
    $stmt->execute();

    $conn->commit();
    $_SESSION['success'] = "Patient unassigned successfully";
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = $e->getMessage();
}

header("Location: ../views/admin/manage_assignments.php");
exit();
