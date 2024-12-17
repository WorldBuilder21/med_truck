<!--
BRAIN STORMING:

- This is a dosage tracker, to keep track of the dosage of medication you take. ( keep track of the dosages taken over time )
- doctors can add medications and patients can add their medications.
- doctors can check the dosage of a patient's medication.
- Add drugs into the database and keep track of the items in stock.

// Project structure breakdown
GOAL OF THE PROJECT:
- To create a system that will help hospitals and doctors keep track of the dosages of their patients, To prevent overdose and underdose.

USER TYPES:
- Super Admin
- Admin
- Doctor
- Patient

USER FUNCTIONALITIES:
- SUPER ADMIN: will be able to view all the doctors, patients and hospital admins in the system.
- ADMIN: will be able to add drugs into the database and keep track of the items in stock. ( Admin in this will be the hospitals admin )
- DOCTOR: will be able to add patients to the system and keep track of their dosages.
- PATIENT: will be able to view their dosages and add new dosages.

VIEWS NEEDED:
- Super Admin:
    - NB: THE DEFAULT PASSWORD FOR THE SUPER ADMIN IS "admin123" & THE DEFAULT EMAIL IS "admin@admin.com"
    - View all doctors, patients and hospital admins in the system.
    - View all drugs in the system.
    - View all dosages in the system.
    - View all hospital admins in the system.
    - View all logs in the system.

- Admin (Hospital Admin):
    - Add drugs into the database and keep track of the items in stock.

- Doctor:
    - view all patients in with the designated hospital.
    - Add patients to the system and keep track of their dosages.
    - Add new dosages to for the patients.
    - View all completed dosages for a patient.
    - View all current dosages for a patient.

- Patient:
    - View their dosages and the dosages history.


-->


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MedTrack - Dosage Tracking System</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.1/flowbite.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/alpinejs/2.3.0/alpine.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg fixed w-full z-50">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0 flex items-center">
                        <span class="text-2xl font-bold text-blue-600">MedTrack</span>
                    </div>
                    <div class="hidden md:ml-6 md:flex md:space-x-8">
                        <a href="#home" class="text-gray-900 inline-flex items-center px-1 pt-1 text-sm font-medium">Home</a>
                        <a href="#features" class="text-gray-500 hover:text-gray-900 inline-flex items-center px-1 pt-1 text-sm font-medium">Features</a>
                        <a href="#about" class="text-gray-500 hover:text-gray-900 inline-flex items-center px-1 pt-1 text-sm font-medium">About</a>
                        <a href="#contact" class="text-gray-500 hover:text-gray-900 inline-flex items-center px-1 pt-1 text-sm font-medium">Contact</a>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <button onclick="window.location.href='./views/auth/login.php'" class="bg-blue-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-blue-700">Login</button>
                    <button onclick="window.location.href='./views/auth/register.php'" class="bg-gray-200 text-gray-900 px-4 py-2 rounded-md text-sm font-medium hover:bg-gray-300">Register</button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="pt-24 bg-gradient-to-b from-blue-50 to-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
            <div class="lg:grid lg:grid-cols-12 lg:gap-8">
                <div class="sm:text-center md:max-w-2xl md:mx-auto lg:col-span-6 lg:text-left">
                    <h1 class="text-4xl tracking-tight font-extrabold text-gray-900 sm:text-5xl md:text-6xl">
                        <span class="block">Smart Medication</span>
                        <span class="block text-blue-600">Tracking System</span>
                    </h1>
                    <p class="mt-3 text-base text-gray-500 sm:mt-5 sm:text-xl lg:text-lg xl:text-xl">
                        Revolutionizing medication management for healthcare providers and patients. Track dosages, monitor inventory, and ensure patient safety with our comprehensive platform.
                    </p>
                    <div class="mt-8">
                        <button onclick="window.location.href='./views/auth/register.php'" class="inline-flex px-5 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                            Get Started
                        </button>
                    </div>
                </div>
                <div class="mt-12 relative sm:max-w-lg sm:mx-auto lg:mt-0 lg:max-w-none lg:mx-0 lg:col-span-6 lg:flex lg:items-center">
                    <img src="./assets/images/landing_page_bg.jpg" alt="black doctor in a white background space" class="rounded-lg shadow-xl">
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="lg:text-center">
                <h2 class="text-3xl font-extrabold text-gray-900 sm:text-4xl">
                    Comprehensive Medication Management
                </h2>
                <p class="mt-4 max-w-2xl text-xl text-gray-500 lg:mx-auto">
                    Everything you need to manage medications and dosages effectively.
                </p>
            </div>

            <div class="mt-20">
                <div class="grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-3">
                    <!-- Feature 1 -->
                    <div class="pt-6">
                        <div class="flow-root bg-gray-50 rounded-lg px-6 pb-8">
                            <div class="-mt-6">
                                <div class="inline-flex items-center justify-center p-3 bg-blue-600 rounded-md shadow-lg">
                                    <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <h3 class="mt-8 text-lg font-medium text-gray-900 tracking-tight">Dosage Tracking</h3>
                                <p class="mt-5 text-base text-gray-500">
                                    Real-time monitoring of medication dosages with automated alerts and notifications.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Feature 2 -->
                    <div class="pt-6">
                        <div class="flow-root bg-gray-50 rounded-lg px-6 pb-8">
                            <div class="-mt-6">
                                <div class="inline-flex items-center justify-center p-3 bg-blue-600 rounded-md shadow-lg">
                                    <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                    </svg>
                                </div>
                                <h3 class="mt-8 text-lg font-medium text-gray-900 tracking-tight">Inventory Management</h3>
                                <p class="mt-5 text-base text-gray-500">
                                    Efficiently manage medication inventory with automated stock alerts and tracking.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Feature 3 -->
                    <div class="pt-6">
                        <div class="flow-root bg-gray-50 rounded-lg px-6 pb-8">
                            <div class="-mt-6">
                                <div class="inline-flex items-center justify-center p-3 bg-blue-600 rounded-md shadow-lg">
                                    <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                    </svg>
                                </div>
                                <h3 class="mt-8 text-lg font-medium text-gray-900 tracking-tight">Multi-User Access</h3>
                                <p class="mt-5 text-base text-gray-500">
                                    Secure access for doctors, patients, and administrators with role-based permissions.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="lg:text-center">
                <h2 class="text-3xl font-extrabold text-gray-900 sm:text-4xl">About MedTrack</h2>
                <p class="mt-4 max-w-2xl text-xl text-gray-500 lg:mx-auto">
                    Dedicated to improving medication management and patient safety through innovative technology.
                </p>
            </div>
            <div class="mt-10">
                <p class="text-gray-600 text-lg leading-relaxed max-w-3xl mx-auto text-center">
                    MedTrack was developed with a mission to prevent medication errors and improve patient outcomes. Our system helps healthcare providers maintain accurate medication records, track dosages, and ensure patient safety through real-time monitoring and alerts.
                </p>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <h2 class="text-3xl font-extrabold text-gray-900">Contact Us</h2>
                <p class="mt-4 text-lg text-gray-500">Have questions? We're here to help.</p>
            </div>
            <div class="mt-12 max-w-lg mx-auto">
                <form class="grid grid-cols-1 gap-y-6">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                        <input type="text" name="name" id="name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" name="email" id="email" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label for="message" class="block text-sm font-medium text-gray-700">Message</label>
                        <textarea name="message" id="message" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                    </div>
                    <div>
                        <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Send Message
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <!-- Simplified Footer -->
    <footer class="bg-gray-800">
        <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="text-gray-400 text-sm">
                    © 2024 MedTrack. All rights reserved.
                </div>
                <div class="flex space-x-6 mt-4 md:mt-0">
                    <a href="#" class="text-gray-400 hover:text-white">Privacy Policy</a>
                    <a href="#" class="text-gray-400 hover:text-white">Terms of Service</a>
                    <a href="#contact" class="text-gray-400 hover:text-white">Contact</a>
                </div>
            </div>
        </div>
    </footer>
</body>

</html>