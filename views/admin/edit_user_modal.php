<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>

<body>
    <!-- Edit User Modal -->
    <div x-show="showEditModal"
        class="fixed inset-0 z-50 overflow-y-auto"
        x-cloak
        @show-edit-modal.window="showEditModal = true">
        <div class="flex items-center justify-center min-h-screen px-4">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75" @click="showEditModal = false"></div>

            <!-- Modal Content -->
            <div class="relative bg-white rounded-lg w-full max-w-md mx-4">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Edit User</h3>
                        <button @click="showEditModal = false" class="text-gray-400 hover:text-gray-500">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <form action="../../actions/update_user.php" method="POST" class="space-y-4">
                        <input type="hidden" id="edit_user_id" name="user_id">

                        <!-- Common Fields from users table -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Email</label>
                            <input type="email" name="email" id="edit_email" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">First Name</label>
                            <input type="text" name="first_name" id="edit_first_name" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Last Name</label>
                            <input type="text" name="last_name" id="edit_last_name" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>

                        <!-- Doctor Fields -->
                        <div id="doctor_fields" class="space-y-4 border-t pt-4 hidden">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Phone Number</label>
                                <input type="tel" name="doctor_phone" id="edit_doctor_phone"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Specialization</label>
                                <input type="text" name="specialization" id="edit_specialization"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">License Number</label>
                                <input type="text" name="license_number" id="edit_license_number"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                        </div>

                        <!-- Patient Fields -->
                        <div id="patient_fields" class="space-y-4 border-t pt-4 hidden">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Phone Number</label>
                                <input type="tel" name="patient_phone" id="edit_patient_phone"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Date of Birth</label>
                                <input type="date" name="date_of_birth" id="edit_date_of_birth"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Weight (kg)</label>
                                <input type="number" step="0.01" name="weight" id="edit_weight"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Height (cm)</label>
                                <input type="number" name="height" id="edit_height"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Blood Type</label>
                                <input type="text" name="blood_type" id="edit_blood_type" maxlength="5"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Allergies</label>
                                <textarea name="allergies" id="edit_allergies" rows="3"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                            </div>
                        </div>

                        <!-- Password Change Section -->
                        <div class="space-y-4 border-t pt-4">
                            <div class="flex items-center">
                                <input type="checkbox" id="change_password" name="change_password" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="change_password" class="ml-2 block text-sm text-gray-900">Change Password</label>
                            </div>

                            <div id="password_fields" class="space-y-4 hidden">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">New Password</label>
                                    <input type="password" name="password" id="edit_password" minlength="8"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Confirm Password</label>
                                    <input type="password" name="password_confirmation" id="edit_password_confirmation" minlength="8"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end space-x-3 pt-4">
                            <button type="button" @click="showEditModal = false"
                                class="px-4 py-2 border rounded-md text-gray-700 hover:bg-gray-50">
                                Cancel
                            </button>
                            <button type="submit"
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const changePasswordCheckbox = document.getElementById('change_password');
            const passwordFields = document.getElementById('password_fields');
            const editUserForm = document.querySelector('form');

            // Toggle password fields
            changePasswordCheckbox.addEventListener('change', function() {
                passwordFields.classList.toggle('hidden');
            });

            // Form validation
            editUserForm.addEventListener('submit', function(e) {
                if (changePasswordCheckbox.checked) {
                    const password = document.getElementById('edit_password').value;
                    const passwordConfirm = document.getElementById('edit_password_confirmation').value;

                    if (password !== passwordConfirm) {
                        e.preventDefault();
                        alert('Passwords do not match');
                        return;
                    }

                    if (password.length < 8) {
                        e.preventDefault();
                        alert('Password must be at least 8 characters long');
                        return;
                    }
                }
            });
        });

        // Function to show/hide role-specific fields
        function showRoleFields(role) {
            const doctorFields = document.getElementById('doctor_fields');
            const patientFields = document.getElementById('patient_fields');

            doctorFields.classList.add('hidden');
            patientFields.classList.add('hidden');

            if (role === 'doctor') {
                doctorFields.classList.remove('hidden');
            } else if (role === 'patient') {
                patientFields.classList.remove('hidden');
            }
        }

        // Update the editUser function to handle the form population
        window.editUser = function(userId) {
            fetch(`../../actions/get_user.php?id=${userId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const user = data.user;

                        // Populate common fields
                        document.getElementById('edit_user_id').value = user.user_id;
                        document.getElementById('edit_email').value = user.email;
                        document.getElementById('edit_first_name').value = user.first_name;
                        document.getElementById('edit_last_name').value = user.last_name;

                        // Show appropriate fields based on role
                        showRoleFields(user.role);

                        // Populate role-specific fields
                        if (user.role === 'doctor') {
                            document.getElementById('edit_doctor_phone').value = user.phone_number || '';
                            document.getElementById('edit_specialization').value = user.specialization || '';
                            document.getElementById('edit_license_number').value = user.license_number || '';
                        } else if (user.role === 'patient') {
                            document.getElementById('edit_patient_phone').value = user.phone_number || '';
                            document.getElementById('edit_date_of_birth').value = user.date_of_birth || '';
                            document.getElementById('edit_weight').value = user.weight || '';
                            document.getElementById('edit_height').value = user.height || '';
                            document.getElementById('edit_blood_type').value = user.blood_type || '';
                            document.getElementById('edit_allergies').value = user.allergies || '';
                        }

                        // Show the modal
                        window.dispatchEvent(new CustomEvent('show-edit-modal'));
                    } else {
                        alert('Error loading user data');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading user data');
                });
        };
    </script>
</body>

</html>