<?php
require_once __DIR__ . '/../db/config.php';

// Get total patients count
function getTotalPatients($conn)
{
    $sql = "SELECT COUNT(*) as total FROM users WHERE role = 'patient'";
    $result = $conn->query($sql);
    $data = $result->fetch_assoc();
    return $data['total'];
}

// Get active doctors count
function getActiveDoctors($conn)
{
    $sql = "SELECT COUNT(*) as total FROM users WHERE role = 'doctor'";
    $result = $conn->query($sql);
    $data = $result->fetch_assoc();
    return $data['total'];
}

// Get total hospitals count
function getTotalHospitals($conn)
{
    $sql = "SELECT COUNT(*) as total FROM hospitals";
    $result = $conn->query($sql);
    $data = $result->fetch_assoc();
    return $data['total'];
}

// Get total medications count
function getTotalMedications($conn)
{
    $sql = "SELECT COUNT(*) as total FROM medications";
    $result = $conn->query($sql);
    $data = $result->fetch_assoc();
    return $data['total'];
}

// Get recent activity
function getRecentActivity($conn, $limit = 10)
{
    $sql = "SELECT 
                'Login' as type,
                CASE 
                    WHEN success = 1 THEN 'Success'
                    ELSE 'Failed'
                END as status,
                CONCAT('Login attempt from ', ip_address) as description,
                u.first_name,
                u.last_name,
                u.role,
                l.login_time as timestamp,
                l.ip_address
            FROM login_logs l
            LEFT JOIN users u ON l.user_id = u.user_id
            ORDER BY login_time DESC
            LIMIT ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    $activities = [];
    while ($row = $result->fetch_assoc()) {
        $activities[] = $row;
    }

    return $activities;
}
