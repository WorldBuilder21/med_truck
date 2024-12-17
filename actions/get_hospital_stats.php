<?php
// Get total patients in the hospital
function getTotalHospitalPatients($conn, $hospital_id)
{
    // Modified query to get patients through hospital staff and prescriptions
    $sql = "SELECT COUNT(DISTINCT u.user_id) as total 
            FROM users u
            JOIN prescriptions p ON u.user_id = p.patient_id
            JOIN doctor_details dd ON p.doctor_id = dd.user_id
            JOIN hospital_staff hs ON dd.user_id = hs.user_id
            WHERE hs.hospital_id = ? AND u.role = 'patient'";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return 0;
    }

    $stmt->bind_param("i", $hospital_id);
    if (!$stmt->execute()) {
        error_log("Execute failed: " . $stmt->error);
        return 0;
    }

    $result = $stmt->get_result();
    return $result->fetch_assoc()['total'] ?? 0;
}

// Get active doctors in the hospital
function getHospitalActiveDoctors($conn, $hospital_id)
{
    $sql = "SELECT COUNT(DISTINCT u.user_id) as total 
            FROM users u
            JOIN hospital_staff hs ON u.user_id = hs.user_id
            WHERE hs.hospital_id = ? AND u.role = 'doctor'";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return 0;
    }

    $stmt->bind_param("i", $hospital_id);
    if (!$stmt->execute()) {
        error_log("Execute failed: " . $stmt->error);
        return 0;
    }

    $result = $stmt->get_result();
    return $result->fetch_assoc()['total'] ?? 0;
}

// Get total medications in hospital inventory
function getHospitalMedications($conn, $hospital_id)
{
    $sql = "SELECT COUNT(DISTINCT i.medication_id) as total 
            FROM inventory i
            WHERE i.hospital_id = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return 0;
    }

    $stmt->bind_param("i", $hospital_id);
    if (!$stmt->execute()) {
        error_log("Execute failed: " . $stmt->error);
        return 0;
    }

    $result = $stmt->get_result();
    return $result->fetch_assoc()['total'] ?? 0;
}

// Get recent activity for the hospital
function getHospitalRecentActivity($conn, $hospital_id, $limit = 10)
{
    $sql = "SELECT 
                CASE 
                    WHEN l.id IS NOT NULL THEN 'Login'
                    WHEN il.log_id IS NOT NULL THEN 'Inventory'
                END as type,
                CASE
                    WHEN l.id IS NOT NULL THEN 
                        CASE 
                            WHEN l.success = 1 THEN 'Success'
                            ELSE 'Failed'
                        END
                    WHEN il.log_id IS NOT NULL THEN 
                        CONCAT(UPPER(il.action_type), ' ', ABS(il.quantity_changed), ' items')
                END as description,
                CASE
                    WHEN l.id IS NOT NULL THEN l.ip_address
                    ELSE NULL
                END as ip_address,
                u.first_name,
                u.last_name,
                u.role,
                CASE
                    WHEN l.id IS NOT NULL THEN 
                        CASE 
                            WHEN l.success = 1 THEN 'Success'
                            ELSE 'Failed'
                        END
                    ELSE 'Success'
                END as status,
                COALESCE(l.created_at, il.created_at) as timestamp
            FROM users u
            LEFT JOIN hospital_staff hs ON u.user_id = hs.user_id
            LEFT JOIN login_logs l ON u.user_id = l.user_id
            LEFT JOIN inventory_logs il ON u.user_id = il.performed_by
            LEFT JOIN inventory i ON il.inventory_id = i.inventory_id
            WHERE (hs.hospital_id = ? OR i.hospital_id = ?)
            AND (l.id IS NOT NULL OR il.log_id IS NOT NULL)
            ORDER BY COALESCE(l.created_at, il.created_at) DESC
            LIMIT ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return [];
    }

    $stmt->bind_param("iii", $hospital_id, $hospital_id, $limit);
    if (!$stmt->execute()) {
        error_log("Execute failed: " . $stmt->error);
        return [];
    }

    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Main function to get all hospital stats
function getHospitalStats($conn, $hospital_id)
{
    return [
        'totalPatients' => getTotalHospitalPatients($conn, $hospital_id),
        'activeDoctors' => getHospitalActiveDoctors($conn, $hospital_id),
        'totalMedications' => getHospitalMedications($conn, $hospital_id),
        'recentActivities' => getHospitalRecentActivity($conn, $hospital_id)
    ];
}
