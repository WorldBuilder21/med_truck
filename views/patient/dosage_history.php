<?php
session_start();
require_once __DIR__ . '/../../db/config.php';

// Check if user is logged in and is a patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../auth/login.php");
    exit();
}

// Get filter parameters
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$dateRange = isset($_GET['date_range']) ? $_GET['date_range'] : '7'; // Default to last 7 days

// Build the base query for dosage history
$query = "
    SELECT 
        dt.*,
        p.dosage_amount,
        p.frequency,
        m.name as medication_name,
        m.dosage_form,
        m.strength,
        CONCAT(u.first_name, ' ', u.last_name) as doctor_name,
        h.name as hospital_name
    FROM dosage_tracking dt
    JOIN prescriptions p ON dt.prescription_id = p.prescription_id
    JOIN medications m ON p.medication_id = m.medication_id
    JOIN users u ON p.doctor_id = u.user_id
    JOIN doctor_patient_assignments dpa ON p.patient_id = dpa.patient_id
    JOIN hospitals h ON dpa.hospital_id = h.hospital_id
    WHERE p.patient_id = ?
";

// Add status filter if not 'all'
if ($status !== 'all') {
    $query .= " AND dt.status = ?";
}

// Add date range filter
$query .= " AND dt.taken_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";

// Add ordering
$query .= " ORDER BY dt.taken_at DESC";

// Prepare and execute the query
$stmt = $conn->prepare($query);

// Bind parameters based on filters
if ($status !== 'all') {
    $stmt->bind_param("isi", $_SESSION['user_id'], $status, $dateRange);
} else {
    $stmt->bind_param("ii", $_SESSION['user_id'], $dateRange);
}

$stmt->execute();
$dosages = $stmt->get_result();

// Get summary statistics
$statsQuery = "
    SELECT 
        dt.status,
        COUNT(*) as count
    FROM dosage_tracking dt
    JOIN prescriptions p ON dt.prescription_id = p.prescription_id
    WHERE p.patient_id = ?
    AND dt.taken_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
    GROUP BY dt.status
";

$statsStmt = $conn->prepare($statsQuery);
$statsStmt->bind_param("ii", $_SESSION['user_id'], $dateRange);
$statsStmt->execute();
$statsResult = $statsStmt->get_result();

$stats = [
    'taken' => 0,
    'missed' => 0,
    'delayed' => 0
];

while ($row = $statsResult->fetch_assoc()) {
    $stats[$row['status']] = $row['count'];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dosage History - MedTrack</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.1/flowbite.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/alpinejs/2.3.0/alpine.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-50">
    <!-- Simple Header with Back Button -->
    <header class="bg-white shadow-sm sticky top-0 z-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex items-center">
                <button onclick="window.history.back()" class="mr-4 p-2 rounded-full hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </button>
                <h1 class="text-xl font-semibold text-gray-900">Dosage History</h1>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Status Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="mb-4 bg-green-50 border-l-4 border-green-400 p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-green-700"><?php echo $_SESSION['success']; ?></p>
                    </div>
                </div>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="mb-4 bg-red-50 border-l-4 border-red-400 p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-red-700"><?php echo $_SESSION['error']; ?></p>
                    </div>
                </div>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Filters -->
        <div class="mb-8">
            <form class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                        <select name="status" id="status"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            onchange="this.form.submit()">
                            <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="taken" <?php echo $status === 'taken' ? 'selected' : ''; ?>>Taken</option>
                            <option value="missed" <?php echo $status === 'missed' ? 'selected' : ''; ?>>Missed</option>
                            <option value="delayed" <?php echo $status === 'delayed' ? 'selected' : ''; ?>>Delayed</option>
                        </select>
                    </div>
                    <div>
                        <label for="date_range" class="block text-sm font-medium text-gray-700">Time Period</label>
                        <select name="date_range" id="date_range"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            onchange="this.form.submit()">
                            <option value="7" <?php echo $dateRange === '7' ? 'selected' : ''; ?>>Last 7 Days</option>
                            <option value="30" <?php echo $dateRange === '30' ? 'selected' : ''; ?>>Last 30 Days</option>
                            <option value="90" <?php echo $dateRange === '90' ? 'selected' : ''; ?>>Last 3 Months</option>
                            <option value="180" <?php echo $dateRange === '180' ? 'selected' : ''; ?>>Last 6 Months</option>
                        </select>
                    </div>
                </div>
            </form>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900">Taken</h3>
                <p class="text-3xl font-bold text-green-600 mt-2"><?php echo $stats['taken']; ?></p>
            </div>
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900">Missed</h3>
                <p class="text-3xl font-bold text-red-600 mt-2"><?php echo $stats['missed']; ?></p>
            </div>
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900">Delayed</h3>
                <p class="text-3xl font-bold text-yellow-600 mt-2"><?php echo $stats['delayed']; ?></p>
            </div>
        </div>

        <!-- Dosage History Table -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Detailed History</h2>
                <?php if ($dosages->num_rows > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Medication</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dosage</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Doctor</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hospital</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php while ($dosage = $dosages->fetch_assoc()): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($dosage['medication_name']); ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <?php echo htmlspecialchars($dosage['strength'] . ' ' . $dosage['dosage_form']); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">
                                                <?php echo htmlspecialchars($dosage['dosage_amount']); ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <?php echo htmlspecialchars($dosage['frequency']); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            Dr. <?php echo htmlspecialchars($dosage['doctor_name']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($dosage['hospital_name']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                <?php
                                                echo match ($dosage['status']) {
                                                    'taken' => 'bg-green-100 text-green-800',
                                                    'missed' => 'bg-red-100 text-red-800',
                                                    'delayed' => 'bg-yellow-100 text-yellow-800',
                                                    default => 'bg-gray-100 text-gray-800'
                                                };
                                                ?>">
                                                <?php echo ucfirst($dosage['status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo date('M j, Y g:i A', strtotime($dosage['taken_at'])); ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-gray-900 max-w-xs truncate">
                                                <?php echo $dosage['notes'] ? htmlspecialchars($dosage['notes']) : '-'; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <div class="text-gray-500">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                            </svg>
                            <p class="mt-4 text-sm text-gray-600">No dosage history found for the selected filters.</p>
                            <p class="mt-2 text-sm text-gray-500">Try adjusting your filters or check back later.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($dosages->num_rows > 0): ?>
                <div class="bg-gray-50 px-6 py-4 border-t border-gray-200">
                    <div class="text-sm text-gray-600">
                        Showing history for the last
                        <?php
                        echo match ($dateRange) {
                            '7' => 'week',
                            '30' => 'month',
                            '90' => '3 months',
                            '180' => '6 months',
                            default => $dateRange . ' days'
                        };
                        ?>
                        • Total entries: <?php echo $dosages->num_rows; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Export/Print Options -->
        <?php if ($dosages->num_rows > 0): ?>
            <div class="mt-6 flex justify-end space-x-4">
                <button onclick="window.print()" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <svg class="h-4 w-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    Print History
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Print Styles -->
    <style media="print">
        header,
        form,
        button {
            display: none !important;
        }

        body {
            background: white !important;
        }

        .shadow-sm {
            box-shadow: none !important;
        }

        .border {
            border: none !important;
        }

        @page {
            margin: 2cm;
        }
    </style>
</body>

</html>