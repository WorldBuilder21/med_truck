<?php
session_start();
require_once '../db/config.php';

// Check authentication and authorization
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['super_admin', 'admin'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized access'
    ]);
    exit();
}

try {
    // Get and validate medication ID
    $medicationId = filter_input(INPUT_POST, 'medication_id', FILTER_VALIDATE_INT);
    if (!$medicationId) {
        throw new Exception('Invalid medication ID');
    }

    // Check if medication exists
    $stmt = $conn->prepare("SELECT medication_id FROM medications WHERE medication_id = ?");
    $stmt->bind_param("i", $medicationId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Medication not found');
    }

    // Check if medication is in use in any prescriptions
    $stmt = $conn->prepare("SELECT prescription_id FROM prescriptions WHERE medication_id = ? LIMIT 1");
    $stmt->bind_param("i", $medicationId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        throw new Exception('Cannot delete medication: It is currently being used in prescriptions');
    }

    // Check if medication is in inventory
    $stmt = $conn->prepare("SELECT inventory_id FROM inventory WHERE medication_id = ? LIMIT 1");
    $stmt->bind_param("i", $medicationId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        throw new Exception('Cannot delete medication: It exists in inventory');
    }

    // Begin transaction
    $conn->begin_transaction();

    // Delete the medication
    $stmt = $conn->prepare("DELETE FROM medications WHERE medication_id = ?");
    $stmt->bind_param("i", $medicationId);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        throw new Exception('Failed to delete medication');
    }

    // Commit transaction
    $conn->commit();

    // Set success message
    $_SESSION['success'] = 'Medication deleted successfully';

    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Medication deleted successfully'
    ]);
} catch (Exception $e) {
    // Rollback transaction if active
    if ($conn->connect_errno === 0) {
        $conn->rollback();
    }

    // Set error message
    $_SESSION['error'] = $e->getMessage();

    // Return JSON error response
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// Close database connections
if (isset($stmt)) {
    $stmt->close();
}
$conn->close();
