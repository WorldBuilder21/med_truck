<?php
session_start();
require_once '../../db/config.php';

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

// Get current hospital enrollment if any
$currentHospital = null;
$stmt = $conn->prepare("
    SELECT h.*, 
           CONCAT(u.first_name, ' ', u.last_name) as doctor_name
    FROM hospitals h
    JOIN doctor_patient_assignments dpa ON h.hospital_id = dpa.hospital_id
    JOIN users u ON dpa.doctor_id = u.user_id
    WHERE dpa.patient_id = ? AND dpa.status = 'active'
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $currentHospital = $result->fetch_assoc();
}

// Get any pending request
$pendingRequest = null;
$stmt = $conn->prepare("
    SELECT h.*, her.request_date, her.status
    FROM hospital_enrollment_requests her
    JOIN hospitals h ON her.hospital_id = h.hospital_id
    WHERE her.doctor_id = ? AND her.status = 'pending'
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $pendingRequest = $result->fetch_assoc();
}

// Get hospitals based on search
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
$stmt = $conn->prepare("
    SELECT * FROM hospitals 
    WHERE (name LIKE ? OR address LIKE ?) 
    AND hospital_id NOT IN (
        SELECT hospital_id 
        FROM doctor_patient_assignments 
        WHERE patient_id = ? AND status = 'active'
        UNION
        SELECT hospital_id 
        FROM hospital_enrollment_requests 
        WHERE doctor_id = ? AND status = 'pending'
    )
    ORDER BY name ASC
");
$searchPattern = "%{$searchTerm}%";
$stmt->bind_param("ssii", $searchPattern, $searchPattern, $_SESSION['user_id'], $_SESSION['user_id']);
$stmt->execute();
$hospitals = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospitals - MedTrack</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.1/flowbite.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/alpinejs/2.3.0/alpine.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>
</head>

<body class="bg-gray-50" x-data="{ sidebarOpen: false, showLogoutModal: false }">
    <!-- Desktop Navigation -->
    <nav class="hidden lg:flex shadow-lg bg-white w-full">
        <div class="container mx-auto px-6 py-3">
            <div class="flex items-center justify-between">
                <!-- Logo -->
                <div class="flex items-center">
                    <span class="text-2xl font-bold text-blue-600">MedTrack</span>
                </div>

                <!-- Navigation Links -->
                <div class="flex items-center space-x-4">
                    <a href="./prescriptions.php" class="px-3 py-2 rounded-lg <?php echo isActive('prescriptions.php'); ?>">
                        My Prescriptions
                    </a>
                    <a href="./dosage_history.php" class="px-3 py-2 rounded-lg <?php echo isActive('dosage_history.php'); ?>">
                        Dosage History
                    </a>
                </div>

                <!-- User Menu -->
                <div class="flex items-center space-x-4">
                    <a href="./account_settings.php" class="px-3 py-2 rounded-lg <?php echo isActive('account_settings.php'); ?>">
                        Account Settings
                    </a>
                    <button @click="showLogoutModal = true" class="px-4 py-2 text-white bg-red-600 hover:bg-red-700 rounded-lg">
                        Logout
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Mobile Navigation -->
    <div class="lg:hidden">
        <!-- Mobile Header -->
        <div class="fixed top-0 left-0 right-0 z-30 bg-white shadow-lg px-4 py-3 flex items-center justify-between">
            <span class="text-xl font-bold text-blue-600">MedTrack</span>
            <button @click="sidebarOpen = !sidebarOpen" class="focus:outline-none">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path x-show="!sidebarOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    <path x-show="sidebarOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Mobile Sidebar -->
        <div x-show="sidebarOpen" class="fixed inset-0 z-20 bg-black bg-opacity-50" @click="sidebarOpen = false"></div>

        <div x-show="sidebarOpen" class="fixed inset-y-0 left-0 z-30 w-64 bg-white shadow-lg transform">
            <div class="p-4">
                <span class="text-xl font-bold text-blue-600">MedTrack</span>
                <nav class="mt-8 space-y-2">
                    <a href="./prescriptions.php" class="block px-4 py-2 rounded-lg <?php echo isActive('prescriptions.php'); ?>">
                        My Prescriptions
                    </a>
                    <a href="./dosage_history.php" class="block px-4 py-2 rounded-lg <?php echo isActive('dosage_history.php'); ?>">
                        Dosage History
                    </a>
                    <a href="../admin/admin_account_settings.php" class="block px-4 py-2 rounded-lg <?php echo isActive('account_settings.php'); ?>">
                        Account Settings
                    </a>
                    <button @click="showLogoutModal = true; sidebarOpen = false" class="w-full mt-4 px-4 py-2 text-white bg-red-600 hover:bg-red-700 rounded-lg">
                        Logout
                    </button>
                </nav>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto mt-8 px-4 sm:px-6 lg:px-8 py-8">
        <!-- Status Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="mb-4 rounded-md bg-green-50 p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-green-800"><?php echo $_SESSION['success']; ?></p>
                    </div>
                </div>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="mb-4 rounded-md bg-red-50 p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-red-800"><?php echo $_SESSION['error']; ?></p>
                    </div>
                </div>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Current Hospital Section -->
        <?php if ($currentHospital): ?>
            <div class="mb-8">
                <h2 class="text-xl font-bold text-gray-900 mb-4">My Current Hospital</h2>
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="p-6">
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="text-lg font-medium text-gray-900"><?php echo htmlspecialchars($currentHospital['name']); ?></h3>
                                <div class="mt-2 text-sm text-gray-600 space-y-1">
                                    <p><?php echo htmlspecialchars($currentHospital['address']); ?></p>
                                    <p>Phone: <?php echo htmlspecialchars($currentHospital['contact_number']); ?></p>
                                    <p>Email: <?php echo htmlspecialchars($currentHospital['email']); ?></p>
                                    <p class="mt-2 font-medium">Assigned Doctor: <?php echo htmlspecialchars($currentHospital['doctor_name']); ?></p>
                                </div>
                            </div>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                Active Patient
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Pending Request Section -->
        <?php if ($pendingRequest): ?>
            <div class="mb-8">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Pending Request</h2>
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="p-6">
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="text-lg font-medium text-gray-900"><?php echo htmlspecialchars($pendingRequest['name']); ?></h3>
                                <div class="mt-2 text-sm text-gray-600 space-y-1">
                                    <p><?php echo htmlspecialchars($pendingRequest['address']); ?></p>
                                    <p>Phone: <?php echo htmlspecialchars($pendingRequest['contact_number']); ?></p>
                                    <p>Email: <?php echo htmlspecialchars($pendingRequest['email']); ?></p>
                                    <p class="text-sm text-gray-500 mt-2">Requested on: <?php echo date('M j, Y', strtotime($pendingRequest['request_date'])); ?></p>
                                </div>
                            </div>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                Pending Approval
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Search Section -->
        <div>
            <h2 class="text-xl font-bold text-gray-900 mb-4">Find Hospitals</h2>
            <form class="mb-6">
                <div class="relative">
                    <input
                        type="text"
                        name="search"
                        placeholder="Search hospitals by name or location..."
                        value="<?php echo htmlspecialchars($searchTerm); ?>"
                        class="w-full pl-4 pr-10 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <button type="submit" class="absolute right-3 top-1/2 transform -translate-y-1/2">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </button>
                </div>
            </form>

            <!-- Hospital List -->
            <div class="space-y-4">
                <?php if ($hospitals->num_rows > 0): ?>
                    <?php while ($hospital = $hospitals->fetch_assoc()): ?>
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
                            <div class="p-6">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h3 class="text-lg font-medium text-gray-900"><?php echo htmlspecialchars($hospital['name']); ?></h3>
                                        <div class="mt-2 text-sm text-gray-600 space-y-1">
                                            <p><?php echo htmlspecialchars($hospital['address']); ?></p>
                                            <p>Phone: <?php echo htmlspecialchars($hospital['contact_number']); ?></p>
                                            <p>Email: <?php echo htmlspecialchars($hospital['email']); ?></p>
                                        </div>
                                    </div>
                                    <form action="../../actions/patient_request_enrollment.php" method="POST">
                                        <input type="hidden" name="hospital_id" value="<?php echo $hospital['hospital_id']; ?>">
                                        <button type="submit"
                                            class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            Request Enrollment
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 text-center">
                        <p class="text-gray-500">No hospitals found matching your search criteria.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../../widgets/logout_modal.php'; ?>
</body>

</html>