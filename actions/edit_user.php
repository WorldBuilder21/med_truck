<?php
// actions/edit_user.php
require_once '../db/config.php';
session_start();

// Check if user is super admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    die('Unauthorized access');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = intval($_POST['user_id']);

    // Start transaction
    $conn->begin_transaction();

    try {
        // Update basic user information
        $stmt = $conn->prepare("
            UPDATE users 
            SET first_name = ?, last_name = ?, email = ?
            WHERE user_id = ?
        ");
        $stmt->bind_param(
            "sssi",
            $_POST['first_name'],
            $_POST['last_name'],
            $_POST['email'],
            $userId
        );
        $stmt->execute();

        // Update role-specific information
        switch ($_POST['role']) {
            case 'patient':
                $stmt = $conn->prepare("
                    UPDATE patient_details 
                    SET phone_number = ?, date_of_birth = ?, weight = ?, height = ?
                    WHERE user_id = ?
                ");
                $stmt->bind_param(
                    "ssddi",
                    $_POST['phone'],
                    $_POST['dob'],
                    $_POST['weight'],
                    $_POST['height'],
                    $userId
                );
                break;

            case 'doctor':
                $stmt = $conn->prepare("
                    UPDATE doctor_details 
                    SET phone_number = ?
                    WHERE user_id = ?
                ");
                $stmt->bind_param("si", $_POST['phone'], $userId);
                break;

            case 'admin':
                if (isset($_POST['hospital_id'])) {
                    $stmt = $conn->prepare("
                        UPDATE hospital_staff 
                        SET hospital_id = ?
                        WHERE user_id = ?
                    ");
                    $stmt->bind_param("ii", $_POST['hospital_id'], $userId);
                }
                break;
        }

        if (isset($stmt)) {
            $stmt->execute();
        }

        $conn->commit();
        $_SESSION['success'] = "User updated successfully.";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error updating user: " . $e->getMessage();
    }

    header("Location: ../views/admin/view_users.php");
    exit();
}
