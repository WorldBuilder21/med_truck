<?php
session_start();
require_once '../db/config.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Validate prescription ID
if (!isset($_GET['prescription_id']) || !is_numeric($_GET['prescription_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid prescription ID']);
    exit();
}

try {
    $prescriptionId = intval($_GET['prescription_id']);

    // Get prescription details and verify doctor's access
    $stmt = $conn->prepare("
        SELECT 
            p.*,
            m.name as medication_name,
            m.dosage_form,
            m.strength
        FROM prescriptions p
        JOIN medications m ON p.medication_id = m.medication_id
        WHERE p.prescription_id = ? AND p.doctor_id = ?
    ");
    $stmt->bind_param("ii", $prescriptionId, $_SESSION['user_id']);
    $stmt->execute();
    $prescription = $stmt->get_result()->fetch_assoc();

    if (!$prescription) {
        throw new Exception('Prescription not found or access denied');
    }

    // Calculate total doses and adherence statistics
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_doses,
            SUM(CASE WHEN status = 'taken' THEN 1 ELSE 0 END) as doses_taken,
            SUM(CASE WHEN status = 'missed' THEN 1 ELSE 0 END) as doses_missed
        FROM dosage_tracking
        WHERE prescription_id = ?
    ");
    $stmt->bind_param("i", $prescriptionId);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();

    // Get last 7 days of tracking
    $stmt = $conn->prepare("
        SELECT 
            taken_at,
            status,
            notes
        FROM dosage_tracking
        WHERE prescription_id = ?
        AND taken_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY)
        ORDER BY taken_at DESC
    ");
    $stmt->bind_param("i", $prescriptionId);
    $stmt->execute();
    $lastSevenDays = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Calculate remaining doses
    $startDate = new DateTime($prescription['start_date']);
    $endDate = new DateTime($prescription['end_date']);
    $today = new DateTime();
    $totalDays = $startDate->diff($endDate)->days + 1;
    $remainingDays = max(0, $endDate->diff($today)->days);

    // Based on frequency, calculate doses per day
    $dosesPerDay = 1; // Default
    if (stripos($prescription['frequency'], 'twice') !== false || stripos($prescription['frequency'], '2 times') !== false) {
        $dosesPerDay = 2;
    } elseif (stripos($prescription['frequency'], 'three times') !== false || stripos($prescription['frequency'], '3 times') !== false) {
        $dosesPerDay = 3;
    }

    $dosesRemaining = $remainingDays * $dosesPerDay;

    // Format last seven days data
    $formattedDays = array_map(function ($day) {
        return [
            'date' => date('M j', strtotime($day['taken_at'])),
            'time' => date('h:i A', strtotime($day['taken_at'])),
            'status' => $day['status'],
            'notes' => $day['notes']
        ];
    }, $lastSevenDays);

    // Calculate adherence rate
    $adherenceRate = 0;
    if ($stats['total_doses'] > 0) {
        $adherenceRate = round(($stats['doses_taken'] / $stats['total_doses']) * 100, 1);
    }

    // Prepare response data
    $response = [
        'prescription' => [
            'id' => $prescription['prescription_id'],
            'medication' => $prescription['medication_name'],
            'strength' => $prescription['strength'],
            'dosage_form' => $prescription['dosage_form'],
            'frequency' => $prescription['frequency'],
            'start_date' => $prescription['start_date'],
            'end_date' => $prescription['end_date']
        ],
        'adherenceRate' => $adherenceRate,
        'dosesTaken' => $stats['doses_taken'],
        'dosesMissed' => $stats['doses_missed'],
        'dosesRemaining' => $dosesRemaining,
        'lastSevenDays' => $formattedDays,
        'summary' => [
            'totalDays' => $totalDays,
            'remainingDays' => $remainingDays,
            'dosesPerDay' => $dosesPerDay
        ]
    ];

    // Send response
    header('Content-Type: application/json');
    echo json_encode($response);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
