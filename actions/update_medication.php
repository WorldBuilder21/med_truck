<?php
session_start();
require_once '../db/config.php';

// Check authentication
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['super_admin', 'admin'])) {
    $_SESSION['error'] = 'Unauthorized access';
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit();
}

function sanitizeInput($conn, $data)
{
    return mysqli_real_escape_string($conn, trim(strip_tags($data)));
}

try {
    // Get and validate medication ID
    $medicationId = filter_input(INPUT_POST, 'medication_id', FILTER_VALIDATE_INT);
    if (!$medicationId) {
        throw new Exception('Invalid medication ID');
    }

    // Sanitize inputs
    $name = sanitizeInput($conn, $_POST['name']);
    $description = sanitizeInput($conn, $_POST['description']);
    $dosageForm = sanitizeInput($conn, $_POST['dosage_form']);
    $strength = sanitizeInput($conn, $_POST['strength']);
    $manufacturer = sanitizeInput($conn, $_POST['manufacturer']);

    // Validate required fields
    if (empty($name) || empty($dosageForm) || empty($strength)) {
        throw new Exception('Name, dosage form, and strength are required');
    }

    // Update medication
    $stmt = $conn->prepare("
        UPDATE medications SET 
            name = ?,
            description = ?,
            dosage_form = ?,
            strength = ?,
            manufacturer = ?
        WHERE medication_id = ?
    ");

    $stmt->bind_param("sssssi", $name, $description, $dosageForm, $strength, $manufacturer, $medicationId);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        throw new Exception('No changes made to medication');
    }

    $_SESSION['success'] = 'Medication updated successfully';

    // Return JSON response for AJAX requests
    if (
        !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'
    ) {
        echo json_encode(['success' => true]);
        exit;
    }

    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
} catch (Exception $e) {
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

    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

$stmt->close();
$conn->close();
