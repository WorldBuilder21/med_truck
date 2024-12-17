<?php
require_once __DIR__ . '/../../db/config.php';
session_start();

// Check authentication and authorization
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['super_admin', 'admin'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Different SQL query based on role
if ($_SESSION['role'] === 'admin') {
    // For hospital admin - only show patients and doctors in their hospital
    $sql = "SELECT 
                u.user_id,
                u.email,
                u.first_name,
                u.last_name,
                u.role,
                u.created_at,
                CASE 
                    WHEN u.role = 'patient' THEN pd.phone_number
                    WHEN u.role = 'doctor' THEN dd.phone_number
                    ELSE NULL
                END as phone_number
            FROM users u
            LEFT JOIN patient_details pd ON u.user_id = pd.user_id AND u.role = 'patient'
            LEFT JOIN doctor_details dd ON u.user_id = dd.user_id AND u.role = 'doctor'
            LEFT JOIN hospital_staff hs ON u.user_id = hs.user_id
            WHERE hs.hospital_id = ? 
            AND u.role IN ('patient', 'doctor')
            ORDER BY u.created_at DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $_SESSION['hospital_id']);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // For super admin - show all users except super admin
    $sql = "SELECT 
                u.user_id,
                u.email,
                u.first_name,
                u.last_name,
                u.role,
                u.created_at,
                CASE 
                    WHEN u.role = 'patient' THEN pd.phone_number
                    WHEN u.role = 'doctor' THEN dd.phone_number
                    ELSE NULL
                END as phone_number
            FROM users u
            LEFT JOIN patient_details pd ON u.user_id = pd.user_id AND u.role = 'patient'
            LEFT JOIN doctor_details dd ON u.user_id = dd.user_id AND u.role = 'doctor'
            WHERE u.role != 'super_admin'
            ORDER BY u.created_at DESC";

    $result = $conn->query($sql);
}

$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - MedTrack</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.1/flowbite.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/alpinejs/2.3.0/alpine.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100"
    x-data="{ showModal: false, showEditModal: false, showActionModal: false, userType: 'patient', editUserId: null, activeFilter: 'all', selectedUserId: null, selectedUserName: '' }">
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

    <main class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-900">User Management</h1>
            <button @click="showModal = true" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                Add New User
            </button>
        </div>

        <!-- Filters -->
        <!-- Filters -->
        <div class="mb-6">
            <div class="flex flex-wrap gap-2">
                <button @click="activeFilter = 'all'; filterUsers('all')"
                    :class="activeFilter === 'all' ? 'bg-blue-600 text-white' : 'bg-blue-100 text-blue-600'"
                    class="px-4 py-2 rounded-lg text-sm">
                    All Users
                </button>
                <button @click="activeFilter = 'patient'; filterUsers('patient')"
                    :class="activeFilter === 'patient' ? 'bg-blue-600 text-white' : 'bg-blue-100 text-blue-600'"
                    class="px-4 py-2 rounded-lg text-sm">
                    Patients
                </button>
                <button @click="activeFilter = 'doctor'; filterUsers('doctor')"
                    :class="activeFilter === 'doctor' ? 'bg-blue-600 text-white' : 'bg-blue-100 text-blue-600'"
                    class="px-4 py-2 rounded-lg text-sm">
                    Doctors
                </button>
                <?php if ($_SESSION['role'] === 'super_admin'): ?>
                    <button @click="activeFilter = 'admin'; filterUsers('admin')"
                        :class="activeFilter === 'admin' ? 'bg-blue-600 text-white' : 'bg-blue-100 text-blue-600'"
                        class="px-4 py-2 rounded-lg text-sm">
                        Admins
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                <?php
                echo $_SESSION['success'];
                unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                <?php
                echo $_SESSION['error'];
                unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <!-- Users Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                        <th class="hidden md:table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contact</th>
                        <th class="hidden md:table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                        <th class="hidden md:table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($users as $user): ?>
                        <tr class="user-row cursor-pointer"
                            data-role="<?php echo htmlspecialchars($user['role']); ?>"
                            data-user-id="<?php echo $user['user_id']; ?>"
                            @click="if (window.innerWidth < 768) { showActionModal = true; selectedUserId = <?php echo $user['user_id']; ?>; selectedUserName = '<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>'; }">
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                </div>
                                <div class="text-sm text-gray-500">
                                    <?php echo htmlspecialchars($user['email']); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php echo match ($user['role']) {
                                        'super_admin' => 'bg-purple-100 text-purple-800',
                                        'admin' => 'bg-blue-100 text-blue-800',
                                        'doctor' => 'bg-green-100 text-green-800',
                                        'patient' => 'bg-yellow-100 text-yellow-800',
                                        default => 'bg-gray-100 text-gray-800'
                                    }; ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td class="hidden md:table-cell px-6 py-4 text-sm text-gray-500">
                                <?php echo $user['phone_number'] ? htmlspecialchars($user['phone_number']) : '-'; ?>
                            </td>
                            <td class="hidden md:table-cell px-6 py-4 text-sm text-gray-500">
                                <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                            </td>
                            <td class="hidden md:table-cell px-6 py-4 text-sm font-medium">
                                <?php if ($user['role'] !== 'super_admin'): ?>
                                    <button onclick="event.stopPropagation(); editUser(<?php echo $user['user_id']; ?>)"
                                        class="text-blue-600 hover:text-blue-900">Edit</button>
                                    <button onclick="event.stopPropagation(); confirmDelete(<?php echo $user['user_id']; ?>)"
                                        class="ml-3 text-red-600 hover:text-red-900">Delete</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Add User Modal -->
        <?php include './add_user_modal.php'; ?>

        <!-- Edit User Modal -->
        <?php include './edit_user_modal.php'; ?>

        <!-- Mobile Action Modal -->
        <div x-show="showActionModal"
            class="fixed inset-0 z-50 overflow-y-auto"
            x-cloak>
            <div class="flex items-center justify-center min-h-screen px-4">
                <!-- Backdrop -->
                <div class="fixed inset-0 transition-opacity" @click="showActionModal = false">
                    <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                </div>

                <!-- Modal Content -->
                <div class="relative bg-white rounded-lg w-full max-w-xs mx-auto">
                    <div class="p-4">
                        <div class="mb-4">
                            <h3 class="text-lg font-medium text-gray-900" x-text="selectedUserName"></h3>
                            <p class="text-sm text-gray-500">Select an action</p>
                        </div>

                        <div class="space-y-3">
                            <button @click="editUser(selectedUserId); showActionModal = false;"
                                class="w-full text-left px-4 py-2 text-sm text-blue-600 hover:bg-blue-50 rounded-lg">
                                Edit User
                            </button>
                            <button @click="if(confirm('Are you sure you want to delete this user?')) { confirmDelete(selectedUserId); } showActionModal = false;"
                                class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 rounded-lg">
                                Delete User
                            </button>
                            <button @click="showActionModal = false"
                                class="w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 rounded-lg">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        function filterUsers(role) {
            const rows = document.querySelectorAll('.user-row');
            rows.forEach(row => {
                if (role === 'all' || row.dataset.role === role) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        function editUser(userId) {
            fetch(`../../actions/get_user.php?id=${userId}&hospital_id=<?php echo $_SESSION['hospital_id']; ?>`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const user = data.user;
                        document.getElementById('edit_user_id').value = user.user_id;
                        document.getElementById('edit_first_name').value = user.first_name;
                        document.getElementById('edit_last_name').value = user.last_name;
                        document.getElementById('edit_email').value = user.email;
                        document.getElementById('edit_phone').value = user.phone_number;

                        window.dispatchEvent(new CustomEvent('show-edit-modal'));
                    } else {
                        alert('Error loading user data');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading user data');
                });
        }

        function confirmDelete(userId) {
            const formData = new FormData();
            formData.append('user_id', userId);
            formData.append('hospital_id', <?php echo $_SESSION['hospital_id']; ?>);

            fetch('../../actions/delete_user.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.querySelector(`tr[data-user-id="${userId}"]`).remove();
                    } else {
                        alert('Error deleting user: ' + data.error);
                    }
                })
                .catch(error => {
                    alert('Error deleting user');
                    console.error('Error:', error);
                });
        }

        // Add window resize handler
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 768) {
                // Close the action modal if screen becomes larger
                const actionModal = document.querySelector('[x-data]').__x.$data;
                if (actionModal && actionModal.showActionModal) {
                    actionModal.showActionModal = false;
                }
            }
        });
    </script>
</body>

</html>