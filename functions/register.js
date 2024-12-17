// Global variables
let currentStep = 1;
const totalSteps = 3;

// DOM Elements
const form = document.getElementById('registrationForm');
const userTypeInputs = document.querySelectorAll('input[name="user_type"]');

// Add event listeners
document.addEventListener('DOMContentLoaded', function () {
    // Add change event listener to user type radio buttons
    userTypeInputs.forEach(input => {
        input.addEventListener('change', () => {
            showFields(input.value);
        });
    });

    // Add form submission handler
    form.addEventListener('submit', handleSubmit);
});

function showFields(userType) {
    // Hide all field sets
    document.getElementById('adminFields').classList.add('hidden');
    document.getElementById('commonFields').classList.add('hidden');
    document.getElementById('patientFields').classList.add('hidden');

    // Show relevant fields based on user type
    if (userType === 'admin') {
        document.getElementById('adminFields').classList.remove('hidden');
    } else {
        document.getElementById('commonFields').classList.remove('hidden');
        if (userType === 'patient') {
            document.getElementById('patientFields').classList.remove('hidden');
        }
    }
}

function updateStepIndicator(step) {
    // Reset all indicators to gray
    for (let i = 1; i <= totalSteps; i++) {
        const indicator = document.querySelector(`#step${i}Indicator div`);
        indicator.classList.remove('bg-blue-600', 'text-white');
        indicator.classList.add('bg-gray-200', 'text-gray-600');
    }

    // Set completed steps to blue
    for (let i = 1; i <= step; i++) {
        const indicator = document.querySelector(`#step${i}Indicator div`);
        indicator.classList.remove('bg-gray-200', 'text-gray-600');
        indicator.classList.add('bg-blue-600', 'text-white');
    }
}

function validateStep(step) {
    let isValid = true;
    let fields = [];

    switch (step) {
        case 1:
            if (!document.querySelector('input[name="user_type"]:checked')) {
                isValid = false;
                showError('Please select a user type');
            }
            break;

        case 2:
            const userType = document.querySelector('input[name="user_type"]:checked').value;

            if (userType === 'admin') {
                fields = ['hospital_name', 'admin_first_name', 'admin_last_name', 'admin_email', 'hospital_phone', 'hospital_address'];
            } else {
                fields = ['first_name', 'last_name', 'email', 'phone'];
                if (userType === 'patient') {
                    fields = fields.concat(['dob', 'weight', 'height']);
                }
            }
            break;

        case 3:
            fields = ['password', 'password_confirmation'];
            const password = document.getElementById('password').value;
            const confirmation = document.getElementById('password_confirmation').value;

            if (password !== confirmation) {
                isValid = false;
                showError('Passwords do not match');
            }
            break;
    }

    // Validate required fields
    fields.forEach(field => {
        const input = document.getElementById(field);
        if (!input.value.trim()) {
            isValid = false;
            input.classList.add('border-red-500');
            showError(`${field.replace('_', ' ')} is required`);
        } else {
            input.classList.remove('border-red-500');
        }
    });

    return isValid;
}

function showError(message) {
    const errorDiv = document.getElementById('errorMessage');
    if (!errorDiv) {
        const div = document.createElement('div');
        div.id = 'errorMessage';
        div.className = 'bg-red-50 border-l-4 border-red-400 p-4 mb-4';
        div.innerHTML = `
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-red-700">${message}</p>
                </div>
            </div>
        `;
        form.insertBefore(div, form.firstChild);
    } else {
        errorDiv.querySelector('p').textContent = message;
    }
}

function clearErrors() {
    const errorDiv = document.getElementById('errorMessage');
    if (errorDiv) {
        errorDiv.remove();
    }
    // Clear red borders
    document.querySelectorAll('input').forEach(input => {
        input.classList.remove('border-red-500');
    });
}

function nextStep(step) {
    clearErrors();

    if (validateStep(step)) {
        document.getElementById(`step${step}`).classList.add('hidden');
        document.getElementById(`step${step + 1}`).classList.remove('hidden');
        currentStep = step + 1;
        updateStepIndicator(currentStep);
    }
}

function previousStep(step) {
    clearErrors();
    document.getElementById(`step${step}`).classList.add('hidden');
    document.getElementById(`step${step - 1}`).classList.remove('hidden');
    currentStep = step - 1;
    updateStepIndicator(currentStep);
}

function handleSubmit(e) {
    e.preventDefault();
    clearErrors();

    // Only submit if we're on the final step
    if (currentStep === 3 && validateStep(currentStep)) {
        // Validate password matching one final time
        const password = document.getElementById('password').value;
        const confirmation = document.getElementById('password_confirmation').value;

        if (password !== confirmation) {
            showError('Passwords do not match');
            return;
        }

        // If all validations pass, submit the form
        form.submit();
    }
}

// Helper function to format form data for submission
function getFormData() {
    const formData = new FormData(form);
    const data = {};
    for (let [key, value] of formData.entries()) {
        data[key] = value;
    }
    return data;
}