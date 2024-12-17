<?php
require_once __DIR__ . '/../../db/config.php';

session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['super_admin', 'admin'])) {
    header("Location: ../auth/login.php");
    exit();
}

function isActive($page_name)
{
    $current_page = basename($_SERVER['PHP_SELF']);
    return $current_page === $page_name ? 'bg-blue-600' : 'hover:bg-blue-600 hover:text-white';
}

// Load appropriate stats based on role
if ($_SESSION['role'] === 'admin') {
    require_once __DIR__ . '../../../actions/get_hospital_stats.php';
    $stats = getHospitalStats($conn, $_SESSION['hospital_id']);

    $totalPatients = $stats['totalPatients'];
    $activeDoctors = $stats['activeDoctors'];
    $totalMedications = $stats['totalMedications'];
    $recentActivities = $stats['recentActivities'];

    // Get total number of activities for pagination (hospital-specific)
    $sql = "SELECT COUNT(*) as total FROM (
        SELECT user_id FROM login_logs 
        WHERE user_id IN (
            SELECT user_id FROM hospital_staff WHERE hospital_id = ?
        )
        UNION ALL 
        SELECT performed_by FROM inventory_logs il
        JOIN inventory i ON il.inventory_id = i.inventory_id
        WHERE i.hospital_id = ?
    ) as combined_logs";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $_SESSION['hospital_id'], $_SESSION['hospital_id']);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    require_once __DIR__ . '/../../actions/dashboard_stats.php';

    // Fetch statistics for super admin
    $totalPatients = getTotalPatients($conn);
    $activeDoctors = getActiveDoctors($conn);
    $totalHospitals = getTotalHospitals($conn);
    $totalMedications = getTotalMedications($conn);

    // Fetch recent activity
    $recentActivities = getRecentActivity($conn, 10);

    // Get total number of activities for pagination (system-wide)
    $sql = "SELECT COUNT(*) as total FROM (
        SELECT user_id FROM login_logs 
        UNION ALL 
        SELECT performed_by FROM inventory_logs
    ) as combined_logs";

    $result = $conn->query($sql);
}

// Common pagination setup
$totalActivities = $result->fetch_assoc()['total'];
$activitiesPerPage = 10;
$totalPages = ceil($totalActivities / $activitiesPerPage);

// Current page
$page = isset($_GET['page']) ? max(1, min($totalPages, intval($_GET['page']))) : 1;

// Calculate pagination offset
$offset = ($page - 1) * $activitiesPerPage;

// Update recent activities based on pagination if needed
if ($_SESSION['role'] === 'admin') {
    $stats = getHospitalStats($conn, $_SESSION['hospital_id']);
    $recentActivities = $stats['recentActivities'];
} else {
    $recentActivities = getRecentActivity($conn, $activitiesPerPage, $offset);
}

// Store view data in array to make it easier to use in the view
$viewData = [
    'totalPatients' => $totalPatients,
    'activeDoctors' => $activeDoctors,
    'totalMedications' => $totalMedications,
    'recentActivities' => $recentActivities,
    'pagination' => [
        'currentPage' => $page,
        'totalPages' => $totalPages,
        'totalActivities' => $totalActivities,
        'activitiesPerPage' => $activitiesPerPage
    ]
];

// For super admin only
if ($_SESSION['role'] === 'super_admin') {
    $viewData['totalHospitals'] = $totalHospitals;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard - MedTrack</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.1/flowbite.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/alpinejs/2.3.0/alpine.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        [x-cloak] {
            display: none !important;
        }

        @media (min-width: 1024px) {
            main {
                padding-top: 1rem;
            }
        }

        @media (max-width: 1023px) {
            main {
                padding-top: 4rem;
            }

            .mobile-nav {
                height: calc(100vh - 4rem);
                top: 4rem;
            }
        }

        .overflow-y-auto {
            -webkit-overflow-scrolling: touch;
        }
    </style>
</head>

<body class="bg-gray-100" x-data="{ sidebarOpen: false, showLogoutModal: false }">

    <nav class="hidden lg:flex shadow-lg bg-white text-black w-full">
        <div class="container mx-auto px-6 py-3">
            <div class="flex items-center justify-between">
                <!-- Logo -->
                <div class="flex items-center">
                    <span class="text-2xl font-bold">MedTrack</span>
                </div>

                <!-- Navigation Links -->
                <div class="flex items-center space-x-4">
                    <a href="../admin/view_users.php"
                        class="px-3 py-2 rounded-lg <?php echo isActive('view_users.php'); ?>">
                        Users
                    </a>
                    <a href="../admin/manage_assignment.php"
                        class="px-3 py-2 rounded-lg <?php echo isActive('manage_assignment.php'); ?>">
                        Manage prescriptions
                    </a>

                    <a href="../admin/view_medications.php"
                        class="px-3 py-2 rounded-lg <?php echo isActive('view_medications.php'); ?>">
                        Medications
                    </a>

                    <a href="../admin/manage_request.php"
                        class="px-3 py-2 rounded-lg <?php echo isActive('manage_request.php'); ?>">
                        Manage requests
                    </a>

                    <a href="../admin/view_logs.php"
                        class="px-3 py-2 rounded-lg <?php echo isActive('view_logs.php'); ?>">

                        <?php echo $_SESSION['role'] === 'admin' ? 'Hospital Activity Logs' : 'System Logs'; ?>

                    </a>

                </div>

                <!-- User Menu -->
                <div class="flex items-center space-x-4">
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <a href="../admin/admin_account_settings.php"
                            class="px-3 py-2 rounded-lg <?php echo isActive('admin_account_settings.php'); ?>">
                            Account Settings
                        </a>
                    <?php endif; ?>
                    <button @click="showLogoutModal = true"
                        class="px-4 py-2 text-white bg-red-600 hover:bg-red-700 rounded-lg">
                        Logout
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Mobile Header & Sidebar -->
    <div class="lg:hidden mb-8">
        <!-- Fixed Mobile Header -->
        <div class="fixed top-0 left-0 right-0 z-30 bg-white shadow-lg text-black px-4 py-3 flex items-center justify-between">
            <span class="text-xl font-bold">MedTrack</span>
            <button @click="sidebarOpen = !sidebarOpen" class="focus:outline-none">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path x-show="!sidebarOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 6h16M4 12h16M4 18h16" />
                    <path x-show="sidebarOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Sidebar Backdrop -->
        <div x-show="sidebarOpen"
            x-transition:enter="transition-opacity ease-linear duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity ease-linear duration-300"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-40 bg-black bg-opacity-50"
            @click="sidebarOpen = false">
        </div>

        <!-- Mobile Sidebar -->
        <div x-show="sidebarOpen"
            x-transition:enter="transition ease-in-out duration-300 transform"
            x-transition:enter-start="-translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in-out duration-300 transform"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="-translate-x-full"
            class="fixed inset-y-0 left-0 z-50 w-full max-w-xs bg-white overflow-y-auto">

            <!-- Logo for mobile -->
            <div class="flex items-center justify-start px-4 py-5">
                <span class="text-xl font-bold text-black">MedTrack</span>
            </div>

            <!-- Navigation links -->
            <nav class="px-4 space-y-4">
                <a href="../admin/view_users.php"
                    @click="sidebarOpen = false"
                    class="block text-black px-4 py-2 rounded-lg <?php echo isActive('view_users.php'); ?>">
                    Users
                </a>
                <a href="../admin/view_medications.php"
                    @click="sidebarOpen = false"
                    class="block text-black px-4 py-2 rounded-lg <?php echo isActive('view_medications.php'); ?>">
                    Medications
                </a>

                <a href="../admin/manage_assignment.php"
                    @click="sidebarOpen = false"
                    class="block text-black px-4 py-2 rounded-lg <?php echo isActive('manage_assignment.php'); ?>">
                    Manage prescriptions
                </a>

                <a href="../admin/manage_request.php"
                    @click="sidebarOpen = false"
                    class="block text-black px-4 py-2 rounded-lg <?php echo isActive('manage_request.php'); ?>">
                    Manage requests
                </a>

                <a href="../admin/view_logs.php"
                    @click="sidebarOpen = false"
                    class="block text-black px-4 py-2 rounded-lg <?php echo isActive('view_logs.php'); ?>">

                    <?php echo $_SESSION['role'] === 'admin' ? 'Hospital Activity Logs' : 'System Logs'; ?>

                </a>

                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <a href="../admin/account_settings.php"
                        @click="sidebarOpen = false"
                        class="block text-black px-4 py-2 rounded-lg <?php echo isActive('account_settings.php'); ?>">
                        Account Settings
                    </a>
                <?php endif; ?>

                <!-- Logout button -->
                <div class="pt-8">
                    <button @click="showLogoutModal = true; sidebarOpen = false"
                        class="w-full text-white px-4 py-2 bg-red-600 hover:bg-red-700 rounded-lg">
                        Logout
                    </button>
                </div>
            </nav>
        </div>
    </div>


    <!-- Main Content -->
    <main class="container mx-auto px-6 py-8">
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Dashboard Overview</h1>
                <p class="text-gray-600">Welcome back, <?php echo htmlspecialchars($_SESSION['name']); ?></p>
            </div>
            <div class="flex items-center space-x-4">
                <span class="text-gray-600"><?php echo date('F j, Y'); ?></span>
            </div>
        </div>
        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8 <?php echo $_SESSION['role'] === 'super_admin' ? 'lg:grid-cols-4' : 'lg:max-w-5xl lg:mx-auto'; ?>">
            <!-- Total Patients -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h2 class="text-gray-600">Total Patients</h2>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($totalPatients); ?></p>
                    </div>
                </div>
            </div>

            <!-- Active Doctors -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h2 class="text-gray-600">Active Doctors</h2>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($activeDoctors); ?></p>
                    </div>
                </div>
            </div>

            <?php if ($_SESSION['role'] === 'super_admin'): ?>
                <!-- Hospitals -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h2 class="text-gray-600">Hospitals</h2>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($totalHospitals); ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Medications -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h2 class="text-gray-600">Medications</h2>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($totalMedications); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <!-- Recent Activity -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Recent Activity</h2>
            </div>
            <div class="p-6">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                                <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($recentActivities as $activity): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php
                                                                                                                    echo match ($activity['type']) {
                                                                                                                        'Login' => $activity['status'] === 'Success' ? 'bg-blue-100 text-blue-800' : 'bg-red-100 text-red-800',
                                                                                                                        'Inventory' => 'bg-yellow-100 text-yellow-800',
                                                                                                                        default => 'bg-gray-100 text-gray-800'
                                                                                                                    };
                                                                                                                    ?>">
                                            <?php echo htmlspecialchars($activity['type']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($activity['description']); ?></div>
                                        <?php if ($activity['ip_address']): ?>
                                            <div class="text-sm text-gray-500">IP: <?php echo htmlspecialchars($activity['ip_address']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo $activity['first_name'] ? htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']) : 'Unknown'; ?>
                                        </div>
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars(ucfirst($activity['role'] ?? '-')); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('Y-m-d h:i A', strtotime($activity['timestamp'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php
                                                                                                                    echo $activity['status'] === 'Success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                                                                                                                    ?>">
                                            <?php echo htmlspecialchars($activity['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-gray-700">
                                Showing
                                <span class="font-medium"><?php echo ($page - 1) * $activitiesPerPage + 1; ?></span>
                                to
                                <span class="font-medium"><?php echo min($page * $activitiesPerPage, $totalActivities); ?></span>
                                of
                                <span class="font-medium"><?php echo $totalActivities; ?></span>
                                results
                            </p>
                        </div>
                        <div>
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo $page - 1; ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                        <span class="sr-only">Previous</span>
                                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                        </svg>
                                    </a>
                                <?php endif; ?>

                                <?php
                                // Calculate range of page numbers to show
                                $start = max(1, min($page - 2, $totalPages - 4));
                                $end = min($start + 4, $totalPages);

                                for ($i = $start; $i <= $end; $i++):
                                ?>
                                    <a href="?page=<?php echo $i; ?>"
                                        class="relative inline-flex items-center px-4 py-2 border border-gray-300 <?php echo $i === $page ? 'bg-blue-50 text-blue-600' : 'bg-white text-gray-700'; ?> hover:bg-gray-50 text-sm font-medium">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <a href="?page=<?php echo $page + 1; ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                        <span class="sr-only">Next</span>
                                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                        </svg>
                                    </a>
                                <?php endif; ?>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>



    <?php
    // Close the database connection
    $conn->close();

    include __DIR__ . '../../../widgets/logout_modal.php';
    ?>
</body>

</html>