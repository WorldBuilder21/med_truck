<?php
session_start();
require_once '../../db/config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

// Get all doctors in this hospital
$stmt = $conn->prepare("
    SELECT u.user_id, u.first_name, u.last_name, dd.specialization
    FROM users u
    JOIN hospital_staff hs ON u.user_id = hs.user_id
    LEFT JOIN doctor_details dd ON u.user_id = dd.user_id
    WHERE hs.hospital_id = ? AND u.role = 'doctor'
    ORDER BY u.last_name, u.first_name
");
$stmt->bind_param("i", $_SESSION['hospital_id']);
$stmt->execute();
$doctors = $stmt->get_result();

// Get all patients
$stmt = $conn->prepare("
    SELECT u.user_id, u.first_name, u.last_name, u.email,
           dpa.doctor_id, dpa.assignment_id, dpa.status,
           CONCAT(d.first_name, ' ', d.last_name) as doctor_name
    FROM users u
    LEFT JOIN doctor_patient_assignments dpa ON u.user_id = dpa.patient_id AND dpa.status = 'active'
    LEFT JOIN users d ON dpa.doctor_id = d.user_id
    WHERE u.role = 'patient'
    ORDER BY u.last_name, u.first_name
");
$stmt->execute();
$patients = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Patient Assignments - MedTrack</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.1/flowbite.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/alpinejs/2.3.0/alpine.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-50" x-data="{ showAssignModal: false, selectedPatient: null }">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="javascript:history.back()" class="inline-flex items-center px-4 py-2 text-gray-700 hover:text-gray-900">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L4.414 9H17a1 1 0 110 2H4.414l5.293 5.293a1 1 0 010 1.414z" clip-rule="evenodd" />
                        </svg>
                        Back
                    </a>
                </div>
                <div class="flex items-center">
                    <span class="text-xl font-semibold text-gray-900">Patient Assignments</span>
                </div>
                <div class="w-48"></div>
            </div>
        </div>
    </nav>

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

        <!-- Patient List -->
        <div class="bg-white shadow overflow-hidden sm:rounded-lg">
            <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                        Patient Assignments
                    </h3>
                </div>
            </div>
            <div class="bg-white">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Patient Name
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Email
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Assigned Doctor
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($patient = $patients->fetch_assoc()): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-500">
                                            <?php echo htmlspecialchars($patient['email']); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($patient['doctor_name']): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                <?php echo htmlspecialchars($patient['doctor_name']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-sm text-gray-500">Not assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <?php if ($patient['doctor_id']): ?>
                                            <form action="../../actions/admin_unassign_patient.php" method="POST" class="inline">
                                                <input type="hidden" name="assignment_id" value="<?php echo $patient['assignment_id']; ?>">
                                                <button type="submit" class="text-red-600 hover:text-red-900">
                                                    Unassign
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <button @click="selectedPatient = <?php echo $patient['user_id']; ?>; showAssignModal = true"
                                                class="text-blue-600 hover:text-blue-900">
                                                Assign to Doctor
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Assign Patient Modal -->
        <div x-show="showAssignModal" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>

                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <form action="../../actions/admin_assign_patients.php" method="POST">
                        <input type="hidden" name="patient_id" x-bind:value="selectedPatient">

                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Assign Patient to Doctor</h3>

                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700">Select Doctor</label>
                                <select name="doctor_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:ring-1">
                                    <option value="">Choose a doctor...</option>
                                    <?php
                                    $doctors->data_seek(0);
                                    while ($doctor = $doctors->fetch_assoc()):
                                    ?>
                                        <option value="<?php echo $doctor['user_id']; ?>">
                                            Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
                                            <?php if ($doctor['specialization']): ?>
                                                (<?php echo htmlspecialchars($doctor['specialization']); ?>)
                                            <?php endif; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Notes (optional)</label>
                                <textarea name="notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:ring-1"></textarea>
                            </div>
                        </div>

                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                                Assign
                            </button>
                            <button type="button" @click="showAssignModal = false" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>

</html>