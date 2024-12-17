<?php
session_start();
require_once '../db/config.php';

// Check authentication and authorization
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['super_admin', 'admin'])) {
    header("Location: ../../../views/auth/login.php");
    exit();
}

// Function to sanitize input data
function sanitizeInput($conn, $data)
{
    return mysqli_real_escape_string($conn, trim(strip_tags($data)));
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Get and validate user ID
    $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    if (!$userId) {
        throw new Exception('Invalid user ID');
    }

    // Get current user data to determine their role
    $stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $userRole = $result->fetch_assoc()['role'];

    if (!$userRole) {
        throw new Exception('User not found');
    }

    // Sanitize common user data
    $email = sanitizeInput($conn, $_POST['email']);
    $firstName = sanitizeInput($conn, $_POST['first_name']);
    $lastName = sanitizeInput($conn, $_POST['last_name']);

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }

    // Check if email is already taken by another user
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
    $stmt->bind_param("si", $email, $userId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        throw new Exception('Email is already in use');
    }

    // Handle password change if requested
    if (isset($_POST['change_password']) && $_POST['change_password'] === 'on') {
        $password = $_POST['password'];
        $passwordConfirmation = $_POST['password_confirmation'];

        if ($password !== $passwordConfirmation) {
            throw new Exception('Passwords do not match');
        }

        if (strlen($password) < 8) {
            throw new Exception('Password must be at least 8 characters long');
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Update user with new password
        $stmt = $conn->prepare("UPDATE users SET email = ?, first_name = ?, last_name = ?, password = ? WHERE user_id = ?");
        $stmt->bind_param("ssssi", $email, $firstName, $lastName, $hashedPassword, $userId);
    } else {
        // Update user without password change
        $stmt = $conn->prepare("UPDATE users SET email = ?, first_name = ?, last_name = ? WHERE user_id = ?");
        $stmt->bind_param("sssi", $email, $firstName, $lastName, $userId);
    }

    $stmt->execute();

    // Handle role-specific updates
    switch ($userRole) {
        case 'doctor':
            $phone = sanitizeInput($conn, $_POST['doctor_phone']);
            $specialization = sanitizeInput($conn, $_POST['specialization']);
            $licenseNumber = sanitizeInput($conn, $_POST['license_number']);

            // Check if doctor details exist
            $stmt = $conn->prepare("SELECT id FROM doctor_details WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                // Update existing doctor details
                $stmt = $conn->prepare("UPDATE doctor_details SET phone_number = ?, specialization = ?, license_number = ? WHERE user_id = ?");
                $stmt->bind_param("sssi", $phone, $specialization, $licenseNumber, $userId);
            } else {
                // Insert new doctor details
                $stmt = $conn->prepare("INSERT INTO doctor_details (user_id, phone_number, specialization, license_number) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("isss", $userId, $phone, $specialization, $licenseNumber);
            }
            $stmt->execute();
            break;

        case 'patient':
            $phone = sanitizeInput($conn, $_POST['patient_phone']);
            $dob = $_POST['date_of_birth'];
            $weight = !empty($_POST['weight']) ? filter_var($_POST['weight'], FILTER_VALIDATE_FLOAT) : null;
            $height = !empty($_POST['height']) ? filter_var($_POST['height'], FILTER_VALIDATE_INT) : null;
            $bloodType = sanitizeInput($conn, $_POST['blood_type']);
            $allergies = sanitizeInput($conn, $_POST['allergies']);

            // Validate date format
            if ($dob && !strtotime($dob)) {
                throw new Exception('Invalid date format');
            }

            // Validate numeric fields
            if ($weight && ($weight <= 0 || $weight > 500)) {
                throw new Exception('Invalid weight value');
            }
            if ($height && ($height <= 0 || $height > 300)) {
                throw new Exception('Invalid height value');
            }

            // Check if patient details exist
            $stmt = $conn->prepare("SELECT id FROM patient_details WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                // Update existing patient details
                $stmt = $conn->prepare("UPDATE patient_details SET phone_number = ?, date_of_birth = ?, weight = ?, height = ?, blood_type = ?, allergies = ? WHERE user_id = ?");
                $stmt->bind_param("ssdiisi", $phone, $dob, $weight, $height, $bloodType, $allergies, $userId);
            } else {
                // Insert new patient details
                $stmt = $conn->prepare("INSERT INTO patient_details (user_id, phone_number, date_of_birth, weight, height, blood_type, allergies) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("issdiis", $userId, $phone, $dob, $weight, $height, $bloodType, $allergies);
            }
            $stmt->execute();
            break;
    }

    // Log the update
    $stmt = $conn->prepare("INSERT INTO login_logs (user_id, email, login_time, ip_address, success) VALUES (?, ?, NOW(), ?, 1)");
    $stmt->bind_param("iss", $_SESSION['user_id'], $_SESSION['email'], $_SERVER['REMOTE_ADDR']);
    $stmt->execute();

    // Commit transaction
    $conn->commit();

    $_SESSION['success'] = 'User updated successfully';

    // Return JSON response for AJAX requests
    if (
        !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'
    ) {
        echo json_encode(['success' => true, 'message' => 'User updated successfully']);
        exit;
    }

    // Redirect for regular form submissions
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();

    $_SESSION['error'] = $e->getMessage();

    // Return JSON response for AJAX requests
    if (
        !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'
    ) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }

    // Redirect for regular form submissions
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

// Close statement and connection
$stmt->close();
$conn->close();
