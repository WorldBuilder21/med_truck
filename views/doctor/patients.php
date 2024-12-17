<?php
session_start();
require_once '../../db/config.php';

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: ../auth/login.php");
    exit();
}

// Get doctor's current hospital
$stmt = $conn->prepare("
    SELECT h.hospital_id, h.name as hospital_name 
    FROM hospitals h
    JOIN hospital_staff hs ON h.hospital_id = hs.hospital_id
    WHERE hs.user_id = ?
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$hospitalResult = $stmt->get_result();
$hospital = $hospitalResult->fetch_assoc();

if (!$hospital) {
    $_SESSION['error'] = "Please join a hospital first to manage patients.";
    header("Location: hospitals.php");
    exit();
}

// Get patients assigned to this doctor
$stmt = $conn->prepare("
    SELECT DISTINCT 
        u.user_id,
        u.first_name,
        u.last_name,
        pd.date_of_birth,
        pd.weight,
        pd.height,
        pd.blood_type,
        pd.allergies,
        (
            SELECT COUNT(*) 
            FROM prescriptions p 
            WHERE p.patient_id = u.user_id 
            AND p.doctor_id = ? 
            AND p.status = 'active'
        ) as active_prescriptions
    FROM users u
    JOIN prescriptions p ON u.user_id = p.patient_id
    LEFT JOIN patient_details pd ON u.user_id = pd.user_id
    WHERE p.doctor_id = ?
    GROUP BY u.user_id
");
$stmt->bind_param("ii", $_SESSION['user_id'], $_SESSION['user_id']);
$stmt->execute();
$patients = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Patients - MedTrack</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.1/flowbite.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/alpinejs/2.3.0/alpine.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <!-- Back Button -->
                    <a href="javascript:history.back()" class="inline-flex items-center px-4 py-2 text-gray-700 hover:text-gray-900">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L4.414 9H17a1 1 0 110 2H4.414l5.293 5.293a1 1 0 010 1.414z" clip-rule="evenodd" />
                        </svg>
                        Back
                    </a>
                </div>


                <div class="flex items-center">
                    <span class="text-xl font-semibold text-gray-900">Patients</span>
                </div>

                <div class="flex items-center w-[100px]"></div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-900">My Patients</h1>
            <div>
                <span class="text-gray-600">Hospital:</span>
                <span class="font-medium"><?php echo htmlspecialchars($hospital['hospital_name']); ?></span>
            </div>
        </div>

        <?php if ($patients->num_rows > 0): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php while ($patient = $patients->fetch_assoc()): ?>
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                        <div class="p-6">
                            <div class="flex justify-between items-start mb-4">
                                <h3 class="text-lg font-medium text-gray-900">
                                    <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                                </h3>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $patient['active_prescriptions'] > 0 ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                    <?php echo $patient['active_prescriptions']; ?> Active Prescriptions
                                </span>
                            </div>

                            <dl class="grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-2">
                                <div class="sm:col-span-1">
                                    <dt class="text-sm font-medium text-gray-500">Age</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        <?php
                                        if ($patient['date_of_birth']) {
                                            $birthDate = new DateTime($patient['date_of_birth']);
                                            $today = new DateTime();
                                            echo $birthDate->diff($today)->y . ' years';
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </dd>
                                </div>

                                <div class="sm:col-span-1">
                                    <dt class="text-sm font-medium text-gray-500">Blood Type</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        <?php echo $patient['blood_type'] ? htmlspecialchars($patient['blood_type']) : 'N/A'; ?>
                                    </dd>
                                </div>

                                <div class="sm:col-span-1">
                                    <dt class="text-sm font-medium text-gray-500">Weight</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        <?php echo $patient['weight'] ? htmlspecialchars($patient['weight']) . ' kg' : 'N/A'; ?>
                                    </dd>
                                </div>

                                <div class="sm:col-span-1">
                                    <dt class="text-sm font-medium text-gray-500">Height</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        <?php echo $patient['height'] ? htmlspecialchars($patient['height']) . ' cm' : 'N/A'; ?>
                                    </dd>
                                </div>

                                <?php if ($patient['allergies']): ?>
                                    <div class="sm:col-span-2">
                                        <dt class="text-sm font-medium text-gray-500">Allergies</dt>
                                        <dd class="mt-1 text-sm text-gray-900">
                                            <?php echo htmlspecialchars($patient['allergies']); ?>
                                        </dd>
                                    </div>
                                <?php endif; ?>
                            </dl>

                            <div class="mt-6 flex space-x-3">
                                <a href="prescriptions.php?patient_id=<?php echo $patient['user_id']; ?>"
                                    class="flex-1 text-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                                    View Prescriptions
                                </a>
                                <a href="new_prescription.php?patient_id=<?php echo $patient['user_id']; ?>"
                                    class="flex-1 text-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700">
                                    Add Prescription
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-12 bg-white rounded-lg shadow-sm border border-gray-200">
                <p class="text-gray-500">No patients assigned yet.</p>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>