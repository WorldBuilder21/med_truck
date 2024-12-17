<?php
session_start();
require_once '../db/config.php';

if ($_SERVER['REQUEST_METHOD'] != 'POST' || !isset($_SESSION['user_id'])) {
    header('Location: ../views/auth/login.php');
    exit();
}

try {
    $conn->begin_transaction();

    // Sanitize common input fields
    $firstName = trim(htmlspecialchars($_POST['first_name']));
    $lastName = trim(htmlspecialchars($_POST['last_name']));
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $phone = trim(htmlspecialchars($_POST['phone_number']));

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }

    // Check if email exists (excluding current user)
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
    $stmt->bind_param("si", $email, $_SESSION['user_id']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        throw new Exception('Email already exists');
    }

    // Update basic user information
    $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE user_id = ?");
    $stmt->bind_param("sssi", $firstName, $lastName, $email, $_SESSION['user_id']);
    $stmt->execute();

    // Role-specific updates
    switch ($_SESSION['role']) {
        case 'doctor':
            $specialization = trim(htmlspecialchars($_POST['specialization']));
            $licenseNumber = trim(htmlspecialchars($_POST['license_number']));

            $stmt = $conn->prepare("
                INSERT INTO doctor_details (user_id, phone_number, specialization, license_number) 
                VALUES (?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                phone_number = VALUES(phone_number),
                specialization = VALUES(specialization),
                license_number = VALUES(license_number)
            ");
            $stmt->bind_param("isss", $_SESSION['user_id'], $phone, $specialization, $licenseNumber);
            $stmt->execute();
            break;

        case 'patient':
            $dob = $_POST['date_of_birth'];
            $weight = floatval($_POST['weight']);
            $height = intval($_POST['height']);
            $bloodType = trim(htmlspecialchars($_POST['blood_type']));
            $allergies = trim(htmlspecialchars($_POST['allergies']));

            $stmt = $conn->prepare("
                INSERT INTO patient_details 
                (user_id, phone_number, date_of_birth, weight, height, blood_type, allergies) 
                VALUES (?, ?, ?, ?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                phone_number = VALUES(phone_number),
                date_of_birth = VALUES(date_of_birth),
                weight = VALUES(weight),
                height = VALUES(height),
                blood_type = VALUES(blood_type),
                allergies = VALUES(allergies)
            ");
            $stmt->bind_param("issidss", $_SESSION['user_id'], $phone, $dob, $weight, $height, $bloodType, $allergies);
            $stmt->execute();
            break;

        case 'admin':
            // Get the admin's hospital ID
            $stmt = $conn->prepare("SELECT hospital_id FROM hospital_staff WHERE user_id = ? AND position = 'Administrator'");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                throw new Exception('No hospital associated with this admin account');
            }

            $hospitalId = $result->fetch_assoc()['hospital_id'];

            // Update hospital information
            $hospitalName = trim(htmlspecialchars($_POST['hospital_name']));
            $hospitalEmail = filter_var($_POST['hospital_email'], FILTER_SANITIZE_EMAIL);
            $contactNumber = trim(htmlspecialchars($_POST['contact_number']));
            $address = trim(htmlspecialchars($_POST['address']));

            // Validate hospital email
            if (!filter_var($hospitalEmail, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid hospital email format');
            }

            // Check if hospital email exists (excluding current hospital)
            $stmt = $conn->prepare("SELECT hospital_id FROM hospitals WHERE email = ? AND hospital_id != ?");
            $stmt->bind_param("si", $hospitalEmail, $hospitalId);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                throw new Exception('Hospital email already exists');
            }

            // Update hospital details
            $stmt = $conn->prepare("
                    UPDATE hospitals 
                    SET name = ?, 
                        email = ?, 
                        contact_number = ?, 
                        address = ? 
                    WHERE hospital_id = ?
                ");
            $stmt->bind_param("ssssi", $hospitalName, $hospitalEmail, $contactNumber, $address, $hospitalId);
            $stmt->execute();

            // Update admin's phone number in hospital_staff table
            $stmt = $conn->prepare("
                    UPDATE hospital_staff 
                    SET phone_number = ?
                    WHERE user_id = ? AND hospital_id = ?
                ");
            $stmt->bind_param("sii", $phone, $_SESSION['user_id'], $hospitalId);
            $stmt->execute();
            break;
    }

    $conn->commit();

    // Update session variables
    $_SESSION['name'] = $firstName . ' ' . $lastName;
    $_SESSION['email'] = $email;

    $_SESSION['success'] = 'Profile updated successfully';
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = $e->getMessage();
}

header('Location: ../views/admin/admin_account_settings.php');
exit();
