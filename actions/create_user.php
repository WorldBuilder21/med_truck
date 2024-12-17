<?php
// actions/create_user.php
require_once '../db/config.php';
session_start();

// Check authentication and authorization
if (!isset($_SESSION['user_id'])) {
    header("Location: ../views/auth/login.php");
    exit();
}

// Only super_admin can create admin users
if ($_POST['user_type'] === 'admin' && $_SESSION['role'] !== 'super_admin') {
    $_SESSION['error'] = "You don't have permission to create admin users.";
    header("Location: ../views/admin/view_users.php");
    exit();
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Common user data
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $firstName = $conn->real_escape_string(htmlspecialchars($_POST['first_name']));
    $lastName = $conn->real_escape_string(htmlspecialchars($_POST['last_name']));
    $role = $conn->real_escape_string($_POST['user_type']);
    $phone = $conn->real_escape_string(htmlspecialchars($_POST['phone']));

    // Insert user
    $stmt = $conn->prepare("
        INSERT INTO users (email, password, first_name, last_name, role) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("sssss", $email, $password, $firstName, $lastName, $role);
    $stmt->execute();
    $userId = $conn->insert_id;

    // Handle role-specific details
    switch ($role) {
        case 'patient':
            $stmt = $conn->prepare("
                INSERT INTO patient_details (user_id, phone_number, date_of_birth, weight, height) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $dob = $conn->real_escape_string($_POST['dob']);
            $weight = floatval($_POST['weight']);
            $height = floatval($_POST['height']);
            $stmt->bind_param("issdd", $userId, $phone, $dob, $weight, $height);
            $stmt->execute();
            break;

        case 'doctor':
            $stmt = $conn->prepare("
                INSERT INTO doctor_details (user_id, phone_number) 
                VALUES (?, ?)
            ");
            $stmt->bind_param("is", $userId, $phone);
            $stmt->execute();
            break;

        case 'admin':
            // Only process if user is super_admin
            if ($_SESSION['role'] === 'super_admin') {
                $stmt = $conn->prepare("
                    INSERT INTO hospital_staff (user_id, hospital_id, position) 
                    VALUES (?, ?, 'Administrator')
                ");
                $hospitalId = intval($_POST['hospital_id']);
                $stmt->bind_param("ii", $userId, $hospitalId);
                $stmt->execute();
            }
            break;
    }

    // Commit transaction
    $conn->commit();
    $_SESSION['success'] = "User created successfully.";
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    $_SESSION['error'] = "Error creating user: " . $e->getMessage();
}

// Close statement and connection
if (isset($stmt)) {
    $stmt->close();
}
$conn->close();

header("Location: ../views/admin/view_users.php");
exit();
