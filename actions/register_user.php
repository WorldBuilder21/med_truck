<?php
session_start();
require_once '../db/config.php';

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Location: ../views/auth/register.php');
    exit();
}

function sanitizeInput($data)
{
    return htmlspecialchars(strip_tags(trim($data)));
}

try {
    // Get and sanitize common form data
    $userType = sanitizeInput($_POST['user_type']);
    $password = $_POST['password'];
    $passwordConfirm = $_POST['password_confirmation'];

    // Validate password
    if ($password !== $passwordConfirm) {
        throw new Exception('Passwords do not match');
    }

    if (strlen($password) < 8) {
        throw new Exception('Password must be at least 8 characters long');
    }

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Begin transaction
    $conn->begin_transaction();

    switch ($userType) {
        case 'admin':
            // Hospital Admin Registration
            $hospitalName = sanitizeInput($_POST['hospital_name']);
            $adminFirstName = sanitizeInput($_POST['admin_first_name']);
            $adminLastName = sanitizeInput($_POST['admin_last_name']);
            $adminEmail = sanitizeInput($_POST['admin_email']);
            $hospitalPhone = sanitizeInput($_POST['hospital_phone']);
            $hospitalAddress = sanitizeInput($_POST['hospital_address']);

            // Check if hospital email already exists
            $stmt = $conn->prepare("SELECT email FROM users WHERE email = ?");
            $stmt->bind_param("s", $adminEmail);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                throw new Exception('Email already registered');
            }

            // Insert hospital details
            $stmt = $conn->prepare("
                INSERT INTO hospitals (name, address, contact_number, email) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("ssss", $hospitalName, $hospitalAddress, $hospitalPhone, $adminEmail);
            $stmt->execute();
            $hospitalId = $conn->insert_id;

            // Insert admin user
            $stmt = $conn->prepare("
                INSERT INTO users (email, password, role, first_name, last_name) 
                VALUES (?, ?, 'admin', ?, ?)
            ");
            $stmt->bind_param("ssss", $adminEmail, $hashedPassword, $adminFirstName, $adminLastName);
            $stmt->execute();
            $userId = $conn->insert_id;

            // Link admin to hospital
            $stmt = $conn->prepare("
                INSERT INTO hospital_staff (user_id, hospital_id, position) 
                VALUES (?, ?, 'Administrator')
            ");
            $stmt->bind_param("ii", $userId, $hospitalId);
            $stmt->execute();
            break;

        case 'doctor':
            // Doctor Registration
            $firstName = sanitizeInput($_POST['first_name']);
            $lastName = sanitizeInput($_POST['last_name']);
            $email = sanitizeInput($_POST['email']);
            $phone = sanitizeInput($_POST['phone']);

            // Check if email already exists
            $stmt = $conn->prepare("SELECT email FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                throw new Exception('Email already registered');
            }

            // Insert doctor user
            $stmt = $conn->prepare("
                INSERT INTO users (email, password, role, first_name, last_name) 
                VALUES (?, ?, 'doctor', ?, ?)
            ");
            $stmt->bind_param("ssss", $email, $hashedPassword, $firstName, $lastName);
            $stmt->execute();
            $userId = $conn->insert_id;

            // Insert doctor details
            $stmt = $conn->prepare("
                INSERT INTO doctor_details (user_id, phone_number) 
                VALUES (?, ?)
            ");
            $stmt->bind_param("is", $userId, $phone);
            $stmt->execute();
            break;

        case 'patient':
            // Patient Registration
            $firstName = sanitizeInput($_POST['first_name']);
            $lastName = sanitizeInput($_POST['last_name']);
            $email = sanitizeInput($_POST['email']);
            $phone = sanitizeInput($_POST['phone']);
            $dob = sanitizeInput($_POST['dob']);
            $weight = floatval($_POST['weight']);
            $height = floatval($_POST['height']);

            // Check if email already exists
            $stmt = $conn->prepare("SELECT email FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                throw new Exception('Email already registered');
            }

            // Insert patient user
            $stmt = $conn->prepare("
                INSERT INTO users (email, password, role, first_name, last_name) 
                VALUES (?, ?, 'patient', ?, ?)
            ");
            $stmt->bind_param("ssss", $email, $hashedPassword, $firstName, $lastName);
            $stmt->execute();
            $userId = $conn->insert_id;

            // Insert patient details
            $stmt = $conn->prepare("
                INSERT INTO patient_details (user_id, phone_number, date_of_birth, weight, height) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("issdd", $userId, $phone, $dob, $weight, $height);
            $stmt->execute();
            break;

        default:
            throw new Exception('Invalid user type');
    }

    // Commit transaction
    $conn->commit();

    // Set success message
    $_SESSION['register_success'] = true;
    $_SESSION['message'] = 'Registration successful! Please log in.';

    // Redirect to login page
    header('Location: ../views/auth/login.php');
    exit();
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();

    $_SESSION['register_error'] = $e->getMessage();
    header('Location: ../views/auth/register.php');
    exit();
}
