<?php
require_once __DIR__ . '/../../db/config.php';

session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header("Location: ../auth/login.php");
    exit();
}

function isActive($page_name)
{
    $current_page = basename($_SERVER['PHP_SELF']);
    return $current_page === $page_name ? 'bg-blue-600 text-white' : 'hover:bg-blue-600 hover:text-white';
}

// Prepare the base query
$baseQuery = "
    SELECT 
        her.*,
        u.first_name,
        u.last_name,
        u.email,
        dd.phone_number,
        dd.specialization,
        h.name as hospital_name
    FROM hospital_enrollment_requests her
    JOIN users u ON her.doctor_id = u.user_id
    LEFT JOIN doctor_details dd ON u.user_id = dd.user_id
    JOIN hospitals h ON her.hospital_id = h.hospital_id
";

// Modify query based on admin type
if ($_SESSION['role'] === 'admin') {
    $baseQuery .= " WHERE her.hospital_id = ?";
    $stmt = $conn->prepare($baseQuery . " ORDER BY her.request_date DESC");
    $stmt->bind_param("i", $_SESSION['hospital_id']);
} else {
    $stmt = $conn->prepare($baseQuery . " ORDER BY her.request_date DESC");
}

$stmt->execute();
$requests = $stmt->get_result();

// Get statistics based on admin type
if ($_SESSION['role'] === 'admin') {
    $statsQuery = "
        SELECT status, COUNT(*) as count
        FROM hospital_enrollment_requests
        WHERE hospital_id = ?
        GROUP BY status
    ";
    $statsStmt = $conn->prepare($statsQuery);
    $statsStmt->bind_param("i", $_SESSION['hospital_id']);
} else {
    $statsQuery = "
        SELECT status, COUNT(*) as count
        FROM hospital_enrollment_requests
        GROUP BY status
    ";
    $statsStmt = $conn->prepare($statsQuery);
}

$statsStmt->execute();
$statsResult = $statsStmt->get_result();

$stats = [
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0
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
    <title>Manage Enrollment Requests - MedTrack</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.1/flowbite.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/alpinejs/2.3.0/alpine.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-50" x-data="{ showLogoutModal: false }">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
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
            <!-- Status Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="mb-4 bg-green-50 border-l-4 border-green-500 p-4">
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
                <div class="mb-4 bg-red-50 border-l-4 border-red-500 p-4">
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

            <div class="mb-8">
                <h1 class="text-2xl font-bold text-gray-900">Doctor Enrollment Requests</h1>
                <p class="mt-2 text-gray-600">
                    <?php echo $_SESSION['role'] === 'admin'
                        ? 'Review and manage enrollment requests for your hospital.'
                        : 'Overview of all hospital enrollment requests across the system.'; ?>
                </p>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900">Pending Requests</h3>
                    <p class="text-3xl font-bold text-blue-600 mt-2"><?php echo $stats['pending']; ?></p>
                </div>
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900">Approved</h3>
                    <p class="text-3xl font-bold text-green-600 mt-2"><?php echo $stats['approved']; ?></p>
                </div>
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900">Rejected</h3>
                    <p class="text-3xl font-bold text-red-600 mt-2"><?php echo $stats['rejected']; ?></p>
                </div>
            </div>

            <!-- Requests Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                    <h2 class="text-lg font-medium text-gray-900">Enrollment Requests</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Doctor</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                                <?php if ($_SESSION['role'] === 'super_admin'): ?>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hospital</th>
                                <?php endif; ?>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Request Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($request = $requests->fetch_assoc()): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?>
                                        </div>
                                        <?php if ($request['specialization']): ?>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($request['specialization']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($request['email']); ?></div>
                                        <?php if ($request['phone_number']): ?>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($request['phone_number']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($_SESSION['role'] === 'super_admin'): ?>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($request['hospital_name']); ?></div>
                                        </td>
                                    <?php endif; ?>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('M j, Y', strtotime($request['request_date'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php
                                        echo match ($request['status']) {
                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                            'approved' => 'bg-green-100 text-green-800',
                                            'rejected' => 'bg-red-100 text-red-800',
                                            default => 'bg-gray-100 text-gray-800'
                                        };
                                        ?>">
                                            <?php echo ucfirst($request['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <?php if (
                                            $request['status'] === 'pending' &&
                                            ($_SESSION['role'] === 'admin' && $request['hospital_id'] == $_SESSION['hospital_id'])
                                        ): ?>
                                            <form action="../../actions/process_enrollment.php" method="POST" class="inline-block">
                                                <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                                                <input type="hidden" name="doctor_id" value="<?php echo $request['doctor_id']; ?>">
                                                <button type="submit" name="action" value="approve"
                                                    class="text-green-600 hover:text-green-900 mr-3">
                                                    Approve
                                                </button>
                                                <button type="submit" name="action" value="reject"
                                                    class="text-red-600 hover:text-red-900">
                                                    Reject
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-gray-500">
                                                <?php echo $request['status'] === 'pending' ? 'Awaiting hospital admin' : 'Processed'; ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>


</body>

</html>