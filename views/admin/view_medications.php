<?php
require_once __DIR__ . '/../../db/config.php';
session_start();

// Check authentication and authorization
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['super_admin', 'admin'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Fetch all medications with their details
// Different SQL query based on role
if ($_SESSION['role'] === 'admin') {
    // For hospital admin - only show medications in their hospital inventory
    $sql = "SELECT DISTINCT m.*, i.quantity, i.expiry_date 
            FROM medications m 
            INNER JOIN inventory i ON m.medication_id = i.medication_id 
            WHERE i.hospital_id = ? 
            ORDER BY m.name ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $_SESSION['hospital_id']);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // For super admin - show all medications
    $sql = "SELECT m.* 
            FROM medications m 
            ORDER BY m.name ASC";

    $result = $conn->query($sql);
}

$medications = [];
while ($row = $result->fetch_assoc()) {
    $medications[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medications Management - MedTrack</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.1/flowbite.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/alpinejs/2.3.0/alpine.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100" x-data="{ showAddModal: false, showEditModal: false, showActionModal: false, selectedMedId: null, selectedMedName: '' }">
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
            <h1 class="text-2xl font-bold text-gray-900">Medications Management</h1>
            <button @click="showAddModal = true" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                Add New Medication
            </button>
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

        <!-- Medications Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                        <th class="hidden md:table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Form</th>
                        <th class="hidden md:table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Strength</th>
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                            <th class="hidden md:table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quantity</th>
                            <th class="hidden md:table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Expiry Date</th>
                        <?php else: ?>
                            <th class="hidden md:table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Manufacturer</th>
                        <?php endif; ?>
                        <th class="hidden md:table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($medications as $med): ?>
                        <tr class="cursor-pointer md:cursor-default"
                            data-medication-id="<?php echo $med['medication_id']; ?>"
                            @click="if (window.innerWidth < 768) { 
                showActionModal = true; 
                selectedMedId = <?php echo $med['medication_id']; ?>; 
                selectedMedName = '<?php echo htmlspecialchars($med['name']); ?>';
            }">
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($med['name']); ?>
                                </div>
                                <div class="md:hidden text-sm text-gray-500">
                                    <?php echo htmlspecialchars($med['strength']); ?> -
                                    <?php echo htmlspecialchars($med['dosage_form']); ?>
                                    <?php if ($_SESSION['role'] === 'admin'): ?>
                                        <br>Stock: <?php echo htmlspecialchars($med['quantity']); ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="hidden md:table-cell px-6 py-4 text-sm text-gray-500">
                                <?php echo htmlspecialchars($med['dosage_form']); ?>
                            </td>
                            <td class="hidden md:table-cell px-6 py-4 text-sm text-gray-500">
                                <?php echo htmlspecialchars($med['strength']); ?>
                            </td>
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                <td class="hidden md:table-cell px-6 py-4 text-sm text-gray-500">
                                    <?php echo htmlspecialchars($med['quantity']); ?>
                                </td>
                                <td class="hidden md:table-cell px-6 py-4 text-sm text-gray-500">
                                    <?php
                                    $expiry = new DateTime($med['expiry_date']);
                                    $now = new DateTime();
                                    $interval = $now->diff($expiry);
                                    $expiryClass = $interval->days <= 30 ? 'text-red-600' : 'text-gray-500';
                                    echo "<span class='$expiryClass'>" . $expiry->format('Y-m-d') . "</span>";
                                    ?>
                                </td>
                            <?php else: ?>
                                <td class="hidden md:table-cell px-6 py-4 text-sm text-gray-500">
                                    <?php echo htmlspecialchars($med['manufacturer']); ?>
                                </td>
                            <?php endif; ?>
                            <td class="hidden md:table-cell px-6 py-4 text-sm font-medium">
                                <button onclick="event.stopPropagation(); editMedication(<?php echo $med['medication_id']; ?>)"
                                    class="text-blue-600 hover:text-blue-900">Edit</button>
                                <button onclick="event.stopPropagation(); confirmDelete(<?php echo $med['medication_id']; ?>)"
                                    class="ml-3 text-red-600 hover:text-red-900">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Add Medication Modal -->
        <div x-show="showAddModal" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75" @click="showAddModal = false"></div>

                <div class="relative bg-white rounded-lg w-full max-w-md mx-4">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-medium text-gray-900">Add New Medication</h3>
                            <button @click="showAddModal = false" class="text-gray-400 hover:text-gray-500">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <form action="../../actions/create_medication.php" method="POST" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Name</label>
                                <input type="text" name="name" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Description</label>
                                <textarea name="description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Dosage Form</label>
                                <input type="text" name="dosage_form" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Strength</label>
                                <input type="text" name="strength" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Manufacturer</label>
                                <input type="text" name="manufacturer" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <div class="flex justify-end space-x-3 pt-4">
                                <button type="button" @click="showAddModal = false"
                                    class="px-4 py-2 border rounded-md text-gray-700 hover:bg-gray-50">
                                    Cancel
                                </button>
                                <button type="submit"
                                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                    Add Medication
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Medication Modal -->
        <div x-show="showEditModal" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75" @click="showEditModal = false"></div>

                <div class="relative bg-white rounded-lg w-full max-w-md mx-4">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-medium text-gray-900">Edit Medication</h3>
                            <button @click="showEditModal = false" class="text-gray-400 hover:text-gray-500">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <form action="../../actions/update_medication.php" method="POST" class="space-y-4">
                            <input type="hidden" id="edit_medication_id" name="medication_id">

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Name</label>
                                <input type="text" id="edit_name" name="name" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Description</label>
                                <textarea id="edit_description" name="description" rows="3"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Dosage Form</label>
                                <input type="text" id="edit_dosage_form" name="dosage_form" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Strength</label>
                                <input type="text" id="edit_strength" name="strength" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Manufacturer</label>
                                <input type="text" id="edit_manufacturer" name="manufacturer"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
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

        <!-- Mobile Action Modal -->
        <div x-show="showActionModal" class="fixed inset-0 z-50 overflow-y-auto md:hidden" x-cloak>
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75" @click="showActionModal = false"></div>

                <div class="relative bg-white rounded-lg w-full max-w-xs mx-4">
                    <div class="p-4">
                        <div class="mb-4">
                            <h3 class="text-lg font-medium text-gray-900" x-text="selectedMedName"></h3>
                            <p class="text-sm text-gray-500">Select an action</p>
                        </div>

                        <div class="space-y-3">
                            <button @click="editMedication(selectedMedId); showActionModal = false;"
                                class="w-full text-left px-4 py-2 text-sm text-blue-600 hover:bg-blue-50 rounded-lg">
                                Edit Medication
                            </button>
                            <button @click="if(confirm('Are you sure you want to delete this medication?')) { confirmDelete(selectedMedId); } showActionModal = false;"
                                class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 rounded-lg">
                                Delete Medication
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
        function editMedication(medicationId) {
            // Fetch medication details
            fetch(`../../actions/get_medication.php?id=${medicationId}&hospital_id=<?php echo $_SESSION['hospital_id']; ?>`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const medication = data.medication;

                        // Populate the edit form
                        document.getElementById('edit_medication_id').value = medication.medication_id;
                        document.getElementById('edit_name').value = medication.name;
                        document.getElementById('edit_description').value = medication.description;
                        document.getElementById('edit_dosage_form').value = medication.dosage_form;
                        document.getElementById('edit_strength').value = medication.strength;
                        document.getElementById('edit_manufacturer').value = medication.manufacturer;

                        // Show the edit modal
                        const editModal = document.querySelector('[x-data]').__x.$data;
                        editModal.showEditModal = true;
                    } else {
                        alert('Error loading medication data');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading medication data');
                });
        }

        function confirmDelete(medicationId) {
            const formData = new FormData();
            formData.append('medication_id', medicationId);
            formData.append('hospital_id', <?php echo $_SESSION['hospital_id']; ?>);

            fetch('../../actions/delete_medication.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error deleting medication: ' + data.error);
                    }
                });
        }

        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 768) {
                // Close the action modal if screen becomes larger
                const actionModal = document.querySelector('[x-data]').__x.$data;
                if (actionModal && actionModal.showActionModal) {
                    actionModal.showActionModal = false;
                }
            }
        });

        // Form validation
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const nameInput = this.querySelector('input[name="name"]');
                const strengthInput = this.querySelector('input[name="strength"]');
                const dosageFormInput = this.querySelector('input[name="dosage_form"]');

                if (!nameInput.value.trim()) {
                    e.preventDefault();
                    alert('Medication name is required');
                    return;
                }

                if (!strengthInput.value.trim()) {
                    e.preventDefault();
                    alert('Strength is required');
                    return;
                }

                if (!dosageFormInput.value.trim()) {
                    e.preventDefault();
                    alert('Dosage form is required');
                    return;
                }
            });
        });
    </script>
</body>

</html>