<?php
session_start();
require_once '../db/config.php';

// Check authentication
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['super_admin', 'admin'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

// Validate medication ID
$medicationId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$medicationId) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid medication ID']);
    exit();
}

try {
    if ($_SESSION['role'] === 'admin') {
        // For hospital admin, check if medication exists in their hospital's inventory
        $stmt = $conn->prepare("
            SELECT m.*, i.quantity, i.expiry_date 
            FROM medications m
            INNER JOIN inventory i ON m.medication_id = i.medication_id
            WHERE m.medication_id = ? AND i.hospital_id = ?
        ");
        $stmt->bind_param("ii", $medicationId, $_SESSION['hospital_id']);
    } else {
        // For super admin, get medication details without hospital restriction
        $stmt = $conn->prepare("SELECT * FROM medications WHERE medication_id = ?");
        $stmt->bind_param("i", $medicationId);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Medication not found or not available in your hospital');
    }

    $medication = $result->fetch_assoc();

    // Return success response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'medication' => $medication
    ]);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$stmt->close();
$conn->close();
