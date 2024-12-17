<?php
session_start();
require_once '../db/config.php';

// Check authentication and authorization
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['super_admin', 'admin'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

// Validate user ID
$userId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$userId) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
    exit();
}

try {
    // Get user's basic information and role
    $sql = "SELECT user_id, email, first_name, last_name, role FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('User not found');
    }

    $userData = $result->fetch_assoc();
    $role = $userData['role'];

    // Get role-specific details
    switch ($role) {
        case 'doctor':
            $sql = "SELECT phone_number, specialization, license_number 
                   FROM doctor_details 
                   WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $doctorDetails = $stmt->get_result()->fetch_assoc();

            if ($doctorDetails) {
                $userData = array_merge($userData, $doctorDetails);
            }
            break;

        case 'patient':
            $sql = "SELECT phone_number, date_of_birth, weight, height, blood_type, allergies 
                   FROM patient_details 
                   WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $patientDetails = $stmt->get_result()->fetch_assoc();

            if ($patientDetails) {
                // Format date for HTML date input
                if ($patientDetails['date_of_birth']) {
                    $patientDetails['date_of_birth'] = date('Y-m-d', strtotime($patientDetails['date_of_birth']));
                }
                $userData = array_merge($userData, $patientDetails);
            }
            break;

        case 'admin':
            $sql = "SELECT h.name as hospital_name, h.address as hospital_address, 
                          h.contact_number as phone_number, h.email as hospital_email
                   FROM hospital_staff hs
                   JOIN hospitals h ON hs.hospital_id = h.hospital_id
                   WHERE hs.user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $adminDetails = $stmt->get_result()->fetch_assoc();

            if ($adminDetails) {
                $userData = array_merge($userData, $adminDetails);
            }
            break;
    }

    // Return success response with user data
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'user' => $userData
    ]);
} catch (Exception $e) {
    // Return error response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// Close database connection
$stmt->close();
$conn->close();
