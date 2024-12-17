<?php
require_once __DIR__ . '/../../db/config.php';
session_start();

// Check authentication and authorization
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['super_admin', 'admin'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Get current page from URL parameter
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$logsPerPage = 15;
$offset = ($page - 1) * $logsPerPage;

// Set default filter
$logType = isset($_GET['type']) ? $_GET['type'] : 'all';

// Build the base queries
if ($_SESSION['role'] === 'admin') {
    // For hospital admin - only show logs related to their hospital
    $baseSql = "SELECT * FROM (
        SELECT 
            'login' as log_type,
            ll.id,
            ll.login_time as timestamp,
            COALESCE(u.first_name || ' ' || u.last_name, 'Unknown User') as user_name,
            u.email,
            CASE 3
                WHEN ll.success = 1 THEN 'Success'
                ELSE 'Failed'
            END as status,
            ll.ip_address,
            'Login Attempt' as action_description
        FROM login_logs ll
        LEFT JOIN users u ON ll.user_id = u.user_id
        LEFT JOIN hospital_staff hs ON u.user_id = hs.user_id
        WHERE hs.hospital_id = ?

        UNION ALL

        SELECT 
            'inventory' as log_type,
            il.log_id as id,
            il.created_at as timestamp,
            u.first_name || ' ' || u.last_name as user_name,
            u.email,
            'Success' as status,
            NULL as ip_address,
            CASE 
                WHEN il.action_type = 'add' THEN 'Added ' || il.quantity_changed || ' items'
                WHEN il.action_type = 'remove' THEN 'Removed ' || ABS(il.quantity_changed) || ' items'
                ELSE 'Adjusted inventory by ' || il.quantity_changed || ' items'
            END as action_description
        FROM inventory_logs il
        JOIN users u ON il.performed_by = u.user_id
        JOIN inventory i ON il.inventory_id = i.inventory_id
        WHERE i.hospital_id = ?
    ) combined_logs";

    $countSql = "SELECT COUNT(*) as total FROM (
        SELECT ll.id
        FROM login_logs ll
        JOIN users u ON ll.user_id = u.user_id
        JOIN hospital_staff hs ON u.user_id = hs.user_id
        WHERE hs.hospital_id = ?
        
        UNION ALL
        
        SELECT il.log_id
        FROM inventory_logs il
        JOIN inventory i ON il.inventory_id = i.inventory_id
        WHERE i.hospital_id = ?
    ) all_logs";
} else {
    // Keep the original queries for super admin
    // [Previous super admin queries remain unchanged]
}

// Add type filter if specified
$whereClause = "";
if ($logType !== 'all') {
    $whereClause = "WHERE log_type = ?";
}

// Get total count
if ($_SESSION['role'] === 'admin') {
    $countStmt = $conn->prepare($countSql);
    $countStmt->bind_param("ii", $_SESSION['hospital_id'], $_SESSION['hospital_id']);
} else {
    $countStmt = $conn->prepare($countSql);
}
$countStmt->execute();
$totalLogs = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalLogs / $logsPerPage);

// Get logs with pagination
$sql = $baseSql . ($whereClause ? " " . $whereClause : "") . " ORDER BY timestamp DESC LIMIT ? OFFSET ?";

// Get logs with pagination
if ($_SESSION['role'] === 'admin') {
    $sql = $baseSql;
    if ($logType !== 'all') {
        $sql .= " WHERE log_type = ? ORDER BY timestamp DESC LIMIT ? OFFSET ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "iiisi",
            $_SESSION['hospital_id'],
            $_SESSION['hospital_id'],
            $logType,
            $logsPerPage,
            $offset
        );
    } else {
        $sql .= " ORDER BY timestamp DESC LIMIT ? OFFSET ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "iiii",
            $_SESSION['hospital_id'],
            $_SESSION['hospital_id'],
            $logsPerPage,
            $offset
        );
    }
} else {
    // For super admin
    $sql = "SELECT * FROM (
        SELECT 
            'login' as log_type,
            ll.id,
            ll.login_time as timestamp,
            COALESCE(CONCAT(u.first_name, ' ', u.last_name), 'Unknown User') as user_name,
            u.email,
            CASE 
                WHEN ll.success = 1 THEN 'Success'
                ELSE 'Failed'
            END as status,
            ll.ip_address,
            'Login Attempt' as action_description
        FROM login_logs ll
        LEFT JOIN users u ON ll.user_id = u.user_id

        UNION ALL

        SELECT 
            'inventory' as log_type,
            il.log_id as id,
            il.created_at as timestamp,
            CONCAT(u.first_name, ' ', u.last_name) as user_name,
            u.email,
            'Success' as status,
            NULL as ip_address,
            CASE 
                WHEN il.action_type = 'add' THEN CONCAT('Added ', il.quantity_changed, ' items')
                WHEN il.action_type = 'remove' THEN CONCAT('Removed ', ABS(il.quantity_changed), ' items')
                ELSE CONCAT('Adjusted inventory by ', il.quantity_changed, ' items')
            END as action_description
        FROM inventory_logs il
        JOIN users u ON il.performed_by = u.user_id
    ) combined_logs";

    if ($logType !== 'all') {
        $sql .= " WHERE log_type = ? ORDER BY timestamp DESC LIMIT ? OFFSET ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sii", $logType, $logsPerPage, $offset);
    } else {
        $sql .= " ORDER BY timestamp DESC LIMIT ? OFFSET ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $logsPerPage, $offset);
    }
}

$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Logs - MedTrack</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.1/flowbite.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/alpinejs/2.3.0/alpine.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100">
    <nav class="bg-white shadow-sm">
        <div class="container mx-auto px-4 py-3">
            <div class="flex items-center">
                <a href="javascript:history.back()" class="text-gray-600 hover:text-gray-900">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </a>
                <span class="ml-4 text-lg font-medium text-gray-900">Back</span>
            </div>
        </div>
    </nav>

    <main class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-900">
                <?php echo $_SESSION['role'] === 'admin' ? 'Hospital Activity Logs' : 'System Logs'; ?>
            </h1>
            <div class="flex space-x-2">
                <a href="?type=all" class="px-4 py-2 rounded-lg text-sm <?php echo $logType === 'all' ? 'bg-blue-600 text-white' : 'bg-blue-100 text-blue-600' ?>">
                    All Logs
                </a>
                <a href="?type=login" class="px-4 py-2 rounded-lg text-sm <?php echo $logType === 'login' ? 'bg-blue-600 text-white' : 'bg-blue-100 text-blue-600' ?>">
                    Login Logs
                </a>
                <a href="?type=inventory" class="px-4 py-2 rounded-lg text-sm <?php echo $logType === 'inventory' ? 'bg-blue-600 text-white' : 'bg-blue-100 text-blue-600' ?>">
                    Inventory Logs
                </a>
            </div>
        </div>

        <!-- Logs Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Timestamp</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Details</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('Y-m-d H:i:s', strtotime($log['timestamp'])); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($log['user_name']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($log['email']); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        <?php echo $log['log_type'] === 'login' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'; ?>">
                                        <?php echo ucfirst($log['log_type']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        <?php echo $log['status'] === 'Success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo $log['status']; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    <div><?php echo htmlspecialchars($log['action_description']); ?></div>
                                    <?php if ($log['ip_address']): ?>
                                        <div class="text-xs text-gray-400">IP: <?php echo htmlspecialchars($log['ip_address']); ?></div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                    <div class="flex-1 flex justify-between sm:hidden">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&type=<?php echo $logType; ?>"
                                class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                Previous
                            </a>
                        <?php endif; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&type=<?php echo $logType; ?>"
                                class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                Next
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-gray-700">
                                Showing
                                <span class="font-medium"><?php echo $offset + 1; ?></span>
                                to
                                <span class="font-medium"><?php echo min($offset + $logsPerPage, $totalLogs); ?></span>
                                of
                                <span class="font-medium"><?php echo $totalLogs; ?></span>
                                results
                            </p>
                        </div>
                        <div>
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <a href="?page=<?php echo $i; ?>&type=<?php echo $logType; ?>"
                                        class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium 
                                           <?php echo $i === $page ? 'bg-blue-50 text-blue-600' : 'bg-white text-gray-500 hover:bg-gray-50'; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                            </nav>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>

</html>