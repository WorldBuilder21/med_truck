<?php
session_start();
require_once __DIR__ . '/../../db/config.php';

// Check if user is logged in and is a patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../auth/login.php");
    exit();
}

function isActive($page_name)
{
    $current_page = basename($_SERVER['PHP_SELF']);
    return $current_page === $page_name ? 'bg-blue-600 text-white' : 'hover:bg-blue-600 hover:text-white';
}

// Get active prescriptions
$stmt = $conn->prepare("
    SELECT 
        p.*,
        m.name as medication_name,
        m.dosage_form,
        m.strength,
        CONCAT(u.first_name, ' ', u.last_name) as doctor_name,
        h.name as hospital_name,
        (
            SELECT COUNT(*) 
            FROM dosage_tracking dt 
            WHERE dt.prescription_id = p.prescription_id 
            AND dt.status = 'taken'
        ) as doses_taken,
        (
            SELECT COUNT(*) 
            FROM dosage_tracking dt 
            WHERE dt.prescription_id = p.prescription_id 
            AND dt.status = 'missed'
        ) as doses_missed
    FROM prescriptions p
    JOIN medications m ON p.medication_id = m.medication_id
    JOIN users u ON p.doctor_id = u.user_id
    JOIN doctor_patient_assignments dpa ON p.doctor_id = dpa.doctor_id
    JOIN hospitals h ON dpa.hospital_id = h.hospital_id
    WHERE p.patient_id = ? 
    ORDER BY p.status ASC, p.created_at DESC
");

$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$prescriptions = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Prescriptions - MedTrack</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.1/flowbite.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/alpinejs/2.3.0/alpine.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-50" x-data="{ sidebarOpen: false, showLogoutModal: false }">
    <!-- Navigation -->
    <nav class="flex shadow-lg bg-white w-full">
        <div class="container mx-auto px-6 py-3">
            <div class="flex items-center justify-between">
                <!-- Back Button -->
                <div class="flex items-center">
                    <a href="javascript:history.back()" class="flex items-center text-gray-600 hover:text-gray-900">
                        <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        <span>Back</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900">My Prescriptions</h1>
            <p class="mt-2 text-gray-600">View and manage your current prescriptions and dosage schedule.</p>
        </div>

        <!-- Prescriptions List -->
        <div class="space-y-6">
            <?php if ($prescriptions->num_rows > 0): ?>
                <?php while ($prescription = $prescriptions->fetch_assoc()): ?>
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                        <div class="p-6">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900">
                                        <?php echo htmlspecialchars($prescription['medication_name']); ?>
                                        <span class="ml-2 text-sm text-gray-500">
                                            <?php echo htmlspecialchars($prescription['strength'] . ' ' . $prescription['dosage_form']); ?>
                                        </span>
                                    </h3>
                                    <div class="mt-2 text-sm text-gray-600">
                                        <p>Prescribed by Dr. <?php echo htmlspecialchars($prescription['doctor_name']); ?></p>
                                        <p>Hospital: <?php echo htmlspecialchars($prescription['hospital_name']); ?></p>
                                        <p class="mt-2">
                                            <span class="font-medium">Dosage:</span>
                                            <?php echo htmlspecialchars($prescription['dosage_amount']); ?>
                                        </p>
                                        <p>
                                            <span class="font-medium">Frequency:</span>
                                            <?php echo htmlspecialchars($prescription['frequency']); ?>
                                        </p>
                                        <p>
                                            <span class="font-medium">Duration:</span>
                                            <?php echo htmlspecialchars($prescription['duration']); ?>
                                        </p>
                                        <p class="mt-2">
                                            <span class="font-medium">Start Date:</span>
                                            <?php echo date('M j, Y', strtotime($prescription['start_date'])); ?>
                                            <?php if ($prescription['end_date']): ?>
                                                <span class="mx-2">-</span>
                                                <span class="font-medium">End Date:</span>
                                                <?php echo date('M j, Y', strtotime($prescription['end_date'])); ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <?php if ($prescription['notes']): ?>
                                        <div class="mt-3 p-3 bg-gray-50 rounded-md">
                                            <p class="text-sm text-gray-600">
                                                <span class="font-medium">Notes:</span>
                                                <?php echo nl2br(htmlspecialchars($prescription['notes'])); ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex flex-col items-end">
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium
                                        <?php
                                        echo match ($prescription['status']) {
                                            'active' => 'bg-green-100 text-green-800',
                                            'completed' => 'bg-blue-100 text-blue-800',
                                            'cancelled' => 'bg-red-100 text-red-800',
                                            default => 'bg-gray-100 text-gray-800'
                                        };
                                        ?>">
                                        <?php echo ucfirst($prescription['status']); ?>
                                    </span>
                                    <?php if ($prescription['status'] === 'active'): ?>
                                        <div class="mt-4 text-sm text-gray-600">
                                            <p>
                                                Doses taken:
                                                <span class="font-medium text-green-600">
                                                    <?php echo $prescription['doses_taken']; ?>
                                                </span>
                                            </p>
                                            <p>
                                                Doses missed:
                                                <span class="font-medium text-red-600">
                                                    <?php echo $prescription['doses_missed']; ?>
                                                </span>
                                            </p>
                                        </div>
                                        <button onclick="window.location.href='./track_dosage.php?id=<?php echo $prescription['prescription_id']; ?>'"
                                            class="mt-4 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                            Track Dosage
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 text-center">
                    <p class="text-gray-500">You don't have any prescriptions yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>