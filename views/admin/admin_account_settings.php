<?php
require_once __DIR__ . '/../../db/config.php';
session_start();

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Fetch user details based on role
$userData = null;
switch ($_SESSION['role']) {
    case 'doctor':
        $stmt = $conn->prepare("
            SELECT u.*, dd.phone_number, dd.specialization, dd.license_number
            FROM users u
            LEFT JOIN doctor_details dd ON u.user_id = dd.user_id
            WHERE u.user_id = ?
        ");
        break;
    case 'admin':
        $stmt = $conn->prepare("
            SELECT u.*, h.*, hs.position
            FROM users u
            JOIN hospital_staff hs ON u.user_id = hs.user_id
            JOIN hospitals h ON hs.hospital_id = h.hospital_id
            WHERE u.user_id = ?
        ");
        break;
    case 'patient':
        $stmt = $conn->prepare("
            SELECT u.*, pd.phone_number, pd.date_of_birth, pd.weight, pd.height, pd.blood_type, pd.allergies
            FROM users u
            LEFT JOIN patient_details pd ON u.user_id = pd.user_id
            WHERE u.user_id = ?
        ");
        break;
}

$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$userData = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings - MedTrack</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.1/flowbite.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/alpinejs/2.3.0/alpine.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100" x-data="{ 
    showDeleteModal: false,
    showSuccessMessage: <?php echo isset($_SESSION['success']) ? 'true' : 'false'; ?> 
}">
    <!-- Success Message -->
    <div x-show="showSuccessMessage"
        x-init="setTimeout(() => showSuccessMessage = false, 3000)" `
        class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
        <?php
        if (isset($_SESSION['success'])) {
            echo $_SESSION['success'];
            unset($_SESSION['success']);
        }
        ?>
    </div>

    <!-- Error Message -->
    <?php if (isset($_SESSION['error'])): ?>
        <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
            <?php
            echo $_SESSION['error'];
            unset($_SESSION['error']);
            ?>
        </div>
    <?php endif; ?>

    <div class="max-w-4xl mx-auto space-y-6 p-6">
        <!-- Personal Information -->
        <div class="bg-white shadow rounded-lg p-6">
            <h2 class="text-xl font-semibold mb-4">Personal Information</h2>
            <form action="../../actions/update_admin_profile.php" method="POST" class="space-y-4">
                <input type="hidden" name="role" value="<?php echo $_SESSION['role']; ?>">

                <!-- Common Fields -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">First Name</label>
                        <input type="text" name="first_name" value="<?php echo htmlspecialchars($userData['first_name']); ?>"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Last Name</label>
                        <input type="text" name="last_name" value="<?php echo htmlspecialchars($userData['last_name']); ?>"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($userData['email']); ?>"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Phone Number</label>
                        <input type="tel" name="phone_number" value="<?php echo htmlspecialchars($userData['phone_number'] ?? ''); ?>"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                </div>

                <!-- Role-specific Fields -->
                <?php if ($_SESSION['role'] === 'doctor'): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Specialization</label>
                            <input type="text" name="specialization" value="<?php echo htmlspecialchars($userData['specialization'] ?? ''); ?>"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">License Number</label>
                            <input type="text" name="license_number" value="<?php echo htmlspecialchars($userData['license_number'] ?? ''); ?>"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                    </div>
                <?php elseif ($_SESSION['role'] === 'patient'): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Date of Birth</label>
                            <input type="date" name="date_of_birth" value="<?php echo htmlspecialchars($userData['date_of_birth'] ?? ''); ?>"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Blood Type</label>
                            <input type="text" name="blood_type" value="<?php echo htmlspecialchars($userData['blood_type'] ?? ''); ?>"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Weight (kg)</label>
                            <input type="number" step="0.1" name="weight" value="<?php echo htmlspecialchars($userData['weight'] ?? ''); ?>"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Height (cm)</label>
                            <input type="number" name="height" value="<?php echo htmlspecialchars($userData['height'] ?? ''); ?>"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">Allergies</label>
                            <textarea name="allergies" rows="3"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"><?php echo htmlspecialchars($userData['allergies'] ?? ''); ?></textarea>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="flex justify-end">
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                        Update Profile
                    </button>
                </div>
            </form>
        </div>

        <!-- Change Password Section -->
        <div class="bg-white shadow rounded-lg p-6">
            <h2 class="text-xl font-semibold mb-4">Change Password</h2>
            <form action="../../actions/update_admin_password.php" method="POST" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Current Password</label>
                        <input type="password" name="current_password" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">New Password</label>
                        <input type="password" name="new_password" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Confirm New Password</label>
                        <input type="password" name="confirm_password" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                        Change Password
                    </button>
                </div>
            </form>
        </div>

        <!-- Delete Account Section -->
        <div class="bg-white shadow rounded-lg p-6">
            <h2 class="text-xl font-semibold mb-4">Delete Account</h2>
            <p class="text-gray-600 mb-4">
                Warning: This action cannot be undone. This will permanently delete your account and all associated data.
            </p>
            <button @click="showDeleteModal = true"
                class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">
                Delete Account
            </button>
        </div>
    </div>

    <!-- Delete Account Modal -->
    <div x-show="showDeleteModal" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75" @click="showDeleteModal = false"></div>

            <div class="relative bg-white rounded-lg w-full max-w-md mx-4">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Confirm Account Deletion</h3>
                    <p class="text-sm text-gray-500 mb-4">
                        Are you sure you want to delete your account? This action cannot be undone.
                    </p>
                    <form action="../../actions/delete_admin_account.php" method="POST">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700">Enter your password to confirm</label>
                            <input type="password" name="password" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div class="flex justify-end space-x-3">
                            <button type="button" @click="showDeleteModal = false"
                                class="px-4 py-2 border rounded-md text-gray-700 hover:bg-gray-50">
                                Cancel
                            </button>
                            <button type="submit"
                                class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                                Delete Account
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>

</html>