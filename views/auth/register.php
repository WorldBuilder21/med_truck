<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - MedTrack</title>
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
                    <a href="../../index.php" class="flex-shrink-0 flex items-center">
                        <span class="text-2xl font-bold text-blue-600">MedTrack</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Registration Section -->
    <div class="min-h-screen flex flex-col justify-center py-12 sm:px-6 lg:px-8">
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                Create your account
            </h2>
            <p class="mt-2 text-center text-sm text-gray-600">
                Already have an account?
                <a href="login.php" class="font-medium text-blue-600 hover:text-blue-500">
                    Sign in
                </a>
            </p>
        </div>

        <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
            <div class="bg-white py-8 px-4 md:shadow sm:rounded-lg sm:px-10">
                <form id="registrationForm" action="../../actions/register_user.php" method="POST" class="space-y-6">
                    <!-- Step Progress Indicator -->
                    <div class="mb-8">
                        <div class="flex justify-between">
                            <div id="step1Indicator" class="flex-1 text-center">
                                <div class="w-8 h-8 mx-auto bg-blue-600 text-white rounded-full flex items-center justify-center">1</div>
                                <span class="text-sm mt-1">User Type</span>
                            </div>
                            <div id="step2Indicator" class="flex-1 text-center">
                                <div class="w-8 h-8 mx-auto bg-gray-200 text-gray-600 rounded-full flex items-center justify-center">2</div>
                                <span class="text-sm mt-1">Details</span>
                            </div>
                            <div id="step3Indicator" class="flex-1 text-center">
                                <div class="w-8 h-8 mx-auto bg-gray-200 text-gray-600 rounded-full flex items-center justify-center">3</div>
                                <span class="text-sm mt-1">Password</span>
                            </div>
                        </div>
                    </div>

                    <!-- Step 1: User Type Selection -->
                    <div id="step1" class="space-y-6">
                        <div>
                            <label class="text-sm font-medium text-gray-700">Select User Type</label>
                            <div class="mt-2 space-y-4">
                                <div class="relative flex items-center p-4 border rounded-lg cursor-pointer hover:border-blue-500">
                                    <input type="radio" name="user_type" value="admin" class="h-4 w-4 text-blue-600" required>
                                    <label class="ml-3">
                                        <span class="block text-sm font-medium text-gray-900">Hospital Administrator</span>
                                        <span class="block text-sm text-gray-500">Manage hospital records and inventory</span>
                                    </label>
                                </div>
                                <div class="relative flex items-center p-4 border rounded-lg cursor-pointer hover:border-blue-500">
                                    <input type="radio" name="user_type" value="doctor" class="h-4 w-4 text-blue-600">
                                    <label class="ml-3">
                                        <span class="block text-sm font-medium text-gray-900">Doctor</span>
                                        <span class="block text-sm text-gray-500">Manage patient prescriptions and treatments</span>
                                    </label>
                                </div>
                                <div class="relative flex items-center p-4 border rounded-lg cursor-pointer hover:border-blue-500">
                                    <input type="radio" name="user_type" value="patient" class="h-4 w-4 text-blue-600">
                                    <label class="ml-3">
                                        <span class="block text-sm font-medium text-gray-900">Patient</span>
                                        <span class="block text-sm text-gray-500">Track medications and appointments</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <button type="button" onclick="nextStep(1)" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Continue
                        </button>
                    </div>

                    <!-- Step 2: User Details (Dynamic based on user type) -->
                    <div id="step2" class="hidden space-y-6">
                        <!-- Admin Fields -->
                        <div id="adminFields" class="hidden space-y-6">
                            <div>
                                <label for="hospital_name" class="block text-sm font-medium text-gray-700">Hospital Name</label>
                                <input type="text" name="hospital_name" id="hospital_name" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="admin_first_name" class="block text-sm font-medium text-gray-700">First Name</label>
                                <input type="text" name="admin_first_name" id="admin_first_name" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="admin_last_name" class="block text-sm font-medium text-gray-700">Last Name</label>
                                <input type="text" name="admin_last_name" id="admin_last_name" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="admin_email" class="block text-sm font-medium text-gray-700">Hospital Email</label>
                                <input type="email" name="admin_email" id="admin_email" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="hospital_phone" class="block text-sm font-medium text-gray-700">Hospital Phone Number</label>
                                <input type="tel" name="hospital_phone" id="hospital_phone" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="hospital_address" class="block text-sm font-medium text-gray-700">Hospital Address</label>
                                <textarea name="hospital_address" id="hospital_address" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></textarea>
                            </div>
                        </div>

                        <!-- Doctor/Patient Common Fields -->
                        <div id="commonFields" class="hidden space-y-6">
                            <div>
                                <label for="first_name" class="block text-sm font-medium text-gray-700">First Name</label>
                                <input type="text" name="first_name" id="first_name" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="last_name" class="block text-sm font-medium text-gray-700">Last Name</label>
                                <input type="text" name="last_name" id="last_name" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                                <input type="email" name="email" id="email" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="phone" class="block text-sm font-medium text-gray-700">Phone Number</label>
                                <input type="tel" name="phone" id="phone" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            </div>
                        </div>

                        <!-- Patient-specific Fields -->
                        <div id="patientFields" class="hidden space-y-6">
                            <div>
                                <label for="dob" class="block text-sm font-medium text-gray-700">Date of Birth</label>
                                <input type="date" name="dob" id="dob" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="weight" class="block text-sm font-medium text-gray-700">Weight (kg)</label>
                                <input type="number" name="weight" id="weight" step="0.1" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="height" class="block text-sm font-medium text-gray-700">Height (cm)</label>
                                <input type="number" name="height" id="height" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            </div>
                        </div>

                        <div class="flex space-x-4">
                            <button type="button" onclick="previousStep(2)" class="flex-1 py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Back
                            </button>
                            <button type="button" onclick="nextStep(2)" class="flex-1 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Continue
                            </button>
                        </div>
                    </div>

                    <!-- Step 3: Password -->
                    <div id="step3" class="hidden space-y-6">
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                            <input type="password" name="password" id="password" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>
                        <div>
                            <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirm Password</label>
                            <input type="password" name="password_confirmation" id="password_confirmation" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>
                        <div class="flex space-x-4">
                            <button type="button" onclick="previousStep(3)" class="flex-1 py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Back
                            </button>
                            <button type="submit" class="flex-1 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Register
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="../../functions/register.js"></script>
</body>

</html>