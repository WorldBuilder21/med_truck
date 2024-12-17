document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('resetForm');

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        // Get form values
        const email = document.getElementById('email').value;
        const password = document.getElementById('password').value;
        const passwordConfirmation = document.getElementById('password_confirmation').value;

        // Validate email
        if (!isValidEmail(email)) {
            showError('Please enter a valid email address');
            return;
        }

        // Validate password length
        if (password.length < 8) {
            showError('Password must be at least 8 characters long');
            return;
        }

        // Validate password match
        if (password !== passwordConfirmation) {
            showError('Passwords do not match');
            return;
        }

        // If all validations pass, submit the form
        this.submit();
    });
});

function isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function showError(message) {
    let errorDiv = document.querySelector('.bg-red-50');
    if (!errorDiv) {
        errorDiv = document.createElement('div');
        errorDiv.className = 'bg-red-50 border-l-4 border-red-400 p-4 mb-4';
        errorDiv.innerHTML = `
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
        const form = document.getElementById('resetForm');
        form.insertBefore(errorDiv, form.firstChild);
    } else {
        errorDiv.querySelector('p').textContent = message;
    }
}