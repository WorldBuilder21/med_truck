<?php
session_start();
require_once '../../db/config.php';

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: ../auth/login.php");
    exit();
}

function isActive($page_name)
{
    $current_page = basename($_SERVER['PHP_SELF']);
    return $current_page === $page_name ? 'bg-blue-600 text-white' : 'hover:bg-blue-600 hover:text-white';
}

// Get patient details if patient_id is provided
$patientId = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : null;
$patient = null;

if ($patientId) {
    $stmt = $conn->prepare("
        SELECT u.*, pd.*, 
            (SELECT COUNT(*) FROM prescriptions 
             WHERE patient_id = u.user_id AND status = 'active') as active_prescriptions
        FROM users u
        LEFT JOIN patient_details pd ON u.user_id = pd.user_id
        WHERE u.user_id = ? AND u.role = 'patient'
    ");
    $stmt->bind_param("i", $patientId);
    $stmt->execute();
    $patient = $stmt->get_result()->fetch_assoc();
}

// Get available medications for the dropdown
$medicationsQuery = "SELECT * FROM medications ORDER BY name ASC";
$medications = $conn->query($medicationsQuery);

// Get prescriptions
$prescriptionQuery = "
    SELECT 
        p.*,
        m.name as medication_name,
        m.dosage_form,
        m.strength,
        u.first_name,
        u.last_name,
        (DATEDIFF(p.end_date, CURRENT_DATE)) as days_remaining,
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
    JOIN users u ON p.patient_id = u.user_id
    WHERE p.doctor_id = ? " . ($patientId ? "AND p.patient_id = ? " : "") . "
    ORDER BY p.created_at DESC";

if ($patientId) {
    $stmt = $conn->prepare($prescriptionQuery);
    $stmt->bind_param("ii", $_SESSION['user_id'], $patientId);
} else {
    $stmt = $conn->prepare($prescriptionQuery);
    $stmt->bind_param("i", $_SESSION['user_id']);
}

$stmt->execute();
$prescriptions = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescriptions - MedTrack</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.1/flowbite.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/alpinejs/2.3.0/alpine.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-50" x-data="{ showNewPrescriptionModal: false }">
    <!-- Navigation -->

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

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!-- Header Section -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">
                    <?php if ($patient): ?>
                        Prescriptions for <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                    <?php else: ?>
                        All Prescriptions
                    <?php endif; ?>
                </h1>
                <?php if ($patient): ?>
                    <p class="mt-1 text-sm text-gray-500">
                        <?php echo $patient['active_prescriptions']; ?> active prescriptions
                    </p>
                <?php endif; ?>
            </div>

            <?php if ($patient): ?>
                <button @click="showNewPrescriptionModal = true"
                    class="px-4 py-2 text-white bg-blue-600 hover:bg-blue-700 rounded-lg">
                    Add New Prescription
                </button>
            <?php endif; ?>
        </div>

        <!-- Prescriptions List -->
        <div class="bg-white shadow overflow-hidden sm:rounded-lg">
            <?php if ($prescriptions->num_rows > 0): ?>
                <div class="divide-y divide-gray-200">
                    <?php while ($prescription = $prescriptions->fetch_assoc()): ?>
                        <div class="p-6">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center justify-between">
                                        <h3 class="text-lg font-medium text-gray-900">
                                            <?php echo htmlspecialchars($prescription['medication_name']); ?>
                                            <?php echo htmlspecialchars($prescription['strength']); ?>
                                        </h3>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                            <?php echo $prescription['days_remaining'] > 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                            <?php
                                            if ($prescription['days_remaining'] > 0) {
                                                echo $prescription['days_remaining'] . ' days remaining';
                                            } else {
                                                echo 'Completed';
                                            }
                                            ?>
                                        </span>
                                    </div>

                                    <?php if (!$patient): ?>
                                        <p class="mt-1 text-sm text-gray-500">
                                            Patient: <?php echo htmlspecialchars($prescription['first_name'] . ' ' . $prescription['last_name']); ?>
                                        </p>
                                    <?php endif; ?>

                                    <dl class="mt-3 grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-3">
                                        <div>
                                            <dt class="text-sm font-medium text-gray-500">Dosage</dt>
                                            <dd class="mt-1 text-sm text-gray-900">
                                                <?php echo htmlspecialchars($prescription['dosage_amount']); ?>
                                                <?php echo htmlspecialchars($prescription['frequency']); ?>
                                            </dd>
                                        </div>

                                        <div>
                                            <dt class="text-sm font-medium text-gray-500">Duration</dt>
                                            <dd class="mt-1 text-sm text-gray-900">
                                                <?php echo htmlspecialchars($prescription['duration']); ?>
                                                (<?php echo date('M j, Y', strtotime($prescription['start_date'])); ?> -
                                                <?php echo date('M j, Y', strtotime($prescription['end_date'])); ?>)
                                            </dd>
                                        </div>

                                        <div>
                                            <dt class="text-sm font-medium text-gray-500">Adherence</dt>
                                            <dd class="mt-1 text-sm text-gray-900">
                                                <?php
                                                $totalDoses = $prescription['doses_taken'] + $prescription['doses_missed'];
                                                if ($totalDoses > 0) {
                                                    $adherenceRate = ($prescription['doses_taken'] / $totalDoses) * 100;
                                                    echo round($adherenceRate, 1) . '% ';
                                                    echo "({$prescription['doses_taken']}/{$totalDoses} doses)";
                                                } else {
                                                    echo "No doses recorded";
                                                }
                                                ?>
                                            </dd>
                                        </div>

                                        <?php if ($prescription['notes']): ?>
                                            <div class="sm:col-span-3">
                                                <dt class="text-sm font-medium text-gray-500">Notes</dt>
                                                <dd class="mt-1 text-sm text-gray-900">
                                                    <?php echo htmlspecialchars($prescription['notes']); ?>
                                                </dd>
                                            </div>
                                        <?php endif; ?>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="p-6 text-center text-gray-500">
                    No prescriptions found.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- New Prescription Modal -->
    <div x-show="showNewPrescriptionModal"
        class="fixed inset-0 z-50 overflow-y-auto"
        x-cloak>
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>

            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form action="../../actions/create_prescription.php" method="POST">
                    <input type="hidden" name="patient_id" value="<?php echo $patientId; ?>">

                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">New Prescription</h3>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Medication</label>
                                <select name="medication_id" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">Select a medication</option>
                                    <?php while ($medication = $medications->fetch_assoc()): ?>
                                        <option value="<?php echo $medication['medication_id']; ?>">
                                            <?php echo htmlspecialchars($medication['name'] . ' ' . $medication['strength'] . ' ' . $medication['dosage_form']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Dosage Amount</label>
                                <input type="text" name="dosage_amount" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    placeholder="e.g., 1 tablet">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Frequency</label>
                                <input type="text" name="frequency" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    placeholder="e.g., Twice daily">
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Start Date</label>
                                    <input type="date" name="start_date" required
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">End Date</label>
                                    <input type="date" name="end_date" required
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Notes</label>
                                <textarea name="notes" rows="3"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    placeholder="Additional instructions or notes"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Create Prescription
                        </button>
                        <button type="button" @click="showNewPrescriptionModal = false"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Adherence Modal -->
    <div x-show="showAdherenceModal"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>

            <div class="relative bg-white rounded-lg max-w-lg w-full">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Medication Adherence Details</h3>

                    <!-- Adherence Statistics -->
                    <div class="space-y-4">
                        <!-- Overall Adherence -->
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <div class="text-sm font-medium text-gray-500">Overall Adherence Rate</div>
                            <div class="mt-1 text-2xl font-semibold text-blue-600">
                                <!-- Will be populated by JavaScript -->
                                <span x-text="adherenceData.overallRate"></span>%
                            </div>
                        </div>

                        <!-- Dosage Tracking -->
                        <div class="space-y-2">
                            <div class="flex justify-between text-sm">
                                <span>Doses Taken</span>
                                <span x-text="adherenceData.dosesTaken"></span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span>Doses Missed</span>
                                <span x-text="adherenceData.dosesMissed"></span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span>Doses Remaining</span>
                                <span x-text="adherenceData.dosesRemaining"></span>
                            </div>
                        </div>

                        <!-- Last 7 Days Timeline -->
                        <div>
                            <h4 class="text-sm font-medium text-gray-700 mb-2">Last 7 Days</h4>
                            <div class="space-y-2">
                                <template x-for="day in adherenceData.lastSevenDays">
                                    <div class="flex items-center">
                                        <span class="w-24 text-sm text-gray-500" x-text="day.date"></span>
                                        <span class="ml-2">
                                            <template x-if="day.status === 'taken'">
                                                <svg class="h-5 w-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                                </svg>
                                            </template>
                                            <template x-if="day.status === 'missed'">
                                                <svg class="h-5 w-5 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                                </svg>
                                            </template>
                                        </span>
                                        <span class="ml-2 text-sm text-gray-600" x-text="day.time"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- Close Button -->
                    <div class="mt-6 flex justify-end">
                        <button @click="showAdherenceModal = false"
                            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('adherenceData', () => ({
                showAdherenceModal: false,
                prescription: null,
                overallRate: 0,
                dosesTaken: 0,
                dosesMissed: 0,
                dosesRemaining: 0,
                lastSevenDays: [],

                openAdherenceModal(prescriptionId) {
                    // Fetch adherence data from server using prescriptionId
                    fetch(`../../actions/get_adherence_data.php?prescription_id=${prescriptionId}`)
                        .then(response => response.json())
                        .then(data => {
                            this.prescription = data;
                            this.overallRate = data.adherenceRate;
                            this.dosesTaken = data.dosesTaken;
                            this.dosesMissed = data.dosesMissed;
                            this.dosesRemaining = data.dosesRemaining;
                            this.lastSevenDays = data.lastSevenDays;
                            this.showAdherenceModal = true;
                        });
                }
            }));
        });
    </script>

</body>

</html>