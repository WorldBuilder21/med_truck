<?php
// actions/delete_user.php
require_once '../db/config.php';
session_start();

// Check if user is super admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    die('Unauthorized access');
}

if (isset($_POST['user_id'])) {
    $userId = intval($_POST['user_id']);

    // Start transaction
    $conn->begin_transaction();

    try {
        // First get the user's role
        $stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        // Delete role-specific details first
        switch ($user['role']) {
            case 'patient':
                $stmt = $conn->prepare("DELETE FROM patient_details WHERE user_id = ?");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                break;

            case 'doctor':
                $stmt = $conn->prepare("DELETE FROM doctor_details WHERE user_id = ?");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                break;

            case 'admin':
                $stmt = $conn->prepare("DELETE FROM hospital_staff WHERE user_id = ?");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                break;
        }

        // Finally delete the user
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();

        $conn->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }

    $stmt->close();
    $conn->close();
}
