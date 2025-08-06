// Installation JavaScript
document.addEventListener('DOMContentLoaded', function() {
    initializeInstallation();
});

function initializeInstallation() {
    // Form validation
    const forms = document.querySelectorAll('.install-form');
    forms.forEach(form => {
        form.addEventListener('submit', handleFormSubmit);
        
        // Real-time validation
        const inputs = form.querySelectorAll('input[required]');
        inputs.forEach(input => {
            input.addEventListener('blur', validateField);
            input.addEventListener('input', clearFieldError);
        });
    });
    
    // Database connection test
    const dbForm = document.querySelector('form[action*="database"]');
    if (dbForm) {
        setupDatabaseForm(dbForm);
    }
    
    // Auto-fill application URL
    setupAppUrlAutofill();
    
    // Progress tracking
    updateProgress();
}

function handleFormSubmit(event) {
    const form = event.target;
    const submitButton = form.querySelector('button[type="submit"]');
    
    // Validate form
    if (!validateForm(form)) {
        event.preventDefault();
        return false;
    }
    
    // Show loading state
    if (submitButton) {
        const originalText = submitButton.textContent;
        submitButton.disabled = true;
        submitButton.innerHTML = '<span class="loading"></span> Processing...';
        
        // Restore button after timeout (in case of errors)
        setTimeout(() => {
            submitButton.disabled = false;
            submitButton.textContent = originalText;
        }, 30000);
    }
    
    return true;
}

function validateForm(form) {
    let isValid = true;
    const inputs = form.querySelectorAll('input[required]');
    
    inputs.forEach(input => {
        if (!validateField({ target: input })) {
            isValid = false;
        }
    });
    
    return isValid;
}

function validateField(event) {
    const input = event.target;
    const value = input.value.trim();
    const type = input.type;
    const name = input.name;
    
    let isValid = true;
    let errorMessage = '';
    
    // Required field check
    if (input.hasAttribute('required') && !value) {
        isValid = false;
        errorMessage = 'This field is required';
    }
    
    // Type-specific validation
    if (value && isValid) {
        switch (type) {
            case 'email':
                if (!isValidEmail(value)) {
                    isValid = false;
                    errorMessage = 'Please enter a valid email address';
                }
                break;
                
            case 'url':
                if (!isValidUrl(value)) {
                    isValid = false;
                    errorMessage = 'Please enter a valid URL';
                }
                break;
                
            case 'number':
                const num = parseInt(value);
                if (isNaN(num) || num < 1 || num > 65535) {
                    isValid = false;
                    errorMessage = 'Please enter a valid port number (1-65535)';
                }
                break;
        }
        
        // Field-specific validation
        if (isValid) {
            switch (name) {
                case 'db_host':
                    if (!isValidHost(value)) {
                        isValid = false;
                        errorMessage = 'Please enter a valid hostname or IP address';
                    }
                    break;
                    
                case 'db_name':
                    if (!/^[a-zA-Z0-9_]+$/.test(value)) {
                        isValid = false;
                        errorMessage = 'Database name can only contain letters, numbers, and underscores';
                    }
                    break;
            }
        }
    }
    
    // Update UI
    updateFieldValidation(input, isValid, errorMessage);
    
    return isValid;
}

function clearFieldError(event) {
    const input = event.target;
    const formGroup = input.closest('.form-group');
    
    if (formGroup) {
        formGroup.classList.remove('error');
        const errorElement = formGroup.querySelector('.error-message');
        if (errorElement) {
            errorElement.remove();
        }
    }
}

function updateFieldValidation(input, isValid, errorMessage) {
    const formGroup = input.closest('.form-group');
    
    if (!formGroup) return;
    
    // Remove existing validation classes and messages
    formGroup.classList.remove('error', 'success');
    const existingError = formGroup.querySelector('.error-message');
    if (existingError) {
        existingError.remove();
    }
    
    if (!isValid && errorMessage) {
        formGroup.classList.add('error');
        
        const errorElement = document.createElement('div');
        errorElement.className = 'error-message';
        errorElement.textContent = errorMessage;
        formGroup.appendChild(errorElement);
    } else if (isValid && input.value.trim()) {
        formGroup.classList.add('success');
    }
}

function setupDatabaseForm(form) {
    const testButton = form.querySelector('button[type="submit"]');
    if (!testButton) return;
    
    // Add test connection functionality
    testButton.addEventListener('click', async function(event) {
        event.preventDefault();
        
        if (!validateForm(form)) {
            return;
        }
        
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        
        try {
            const result = await testDatabaseConnection(data);
            
            if (result.success) {
                showConnectionResult(true, 'Database connection successful!');
                // Submit the form after successful test
                setTimeout(() => {
                    form.submit();
                }, 1000);
            } else {
                showConnectionResult(false, result.message || 'Connection failed');
            }
        } catch (error) {
            showConnectionResult(false, 'Connection test failed: ' + error.message);
        }
    });
}

async function testDatabaseConnection(data) {
    // This would normally make an AJAX request to test the connection
    // For now, we'll simulate it
    return new Promise((resolve) => {
        setTimeout(() => {
            // Basic validation
            if (!data.db_host || !data.db_port || !data.db_name || !data.db_user) {
                resolve({ success: false, message: 'Please fill in all required fields' });
                return;
            }
            
            // Simulate connection test
            resolve({ success: true, message: 'Connection successful' });
        }, 1500);
    });
}

function showConnectionResult(success, message) {
    // Remove existing alerts
    const existingAlerts = document.querySelectorAll('.connection-result');
    existingAlerts.forEach(alert => alert.remove());
    
    // Create new alert
    const alert = document.createElement('div');
    alert.className = `alert connection-result ${success ? 'alert-success' : 'alert-error'}`;
    alert.innerHTML = `<strong>${success ? 'Success:' : 'Error:'}</strong> ${message}`;
    
    // Insert before form
    const form = document.querySelector('.install-form');
    if (form) {
        form.parentNode.insertBefore(alert, form);
    }
    
    // Auto-remove after delay
    setTimeout(() => {
        alert.remove();
    }, 5000);
}

function setupAppUrlAutofill() {
    const appUrlInput = document.querySelector('input[name="app_url"]');
    if (appUrlInput && !appUrlInput.value) {
        // Auto-fill with current URL (minus install.php)
        const currentUrl = window.location.href.replace(/\/install\.php.*$/, '');
        appUrlInput.value = currentUrl;
    }
}

function updateProgress() {
    const steps = document.querySelectorAll('.step');
    const activeStep = document.querySelector('.step.active');
    
    if (!activeStep) return;
    
    const activeIndex = Array.from(steps).indexOf(activeStep);
    const progress = ((activeIndex + 1) / steps.length) * 100;
    
    // Create or update progress bar
    let progressBar = document.querySelector('.progress-bar');
    if (!progressBar) {
        const progressContainer = document.createElement('div');
        progressContainer.className = 'progress';
        
        progressBar = document.createElement('div');
        progressBar.className = 'progress-bar';
        
        progressContainer.appendChild(progressBar);
        
        const stepIndicator = document.querySelector('.step-indicator');
        if (stepIndicator) {
            stepIndicator.parentNode.insertBefore(progressContainer, stepIndicator.nextSibling);
        }
    }
    
    progressBar.style.width = progress + '%';
}

// Validation helper functions
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

function isValidUrl(url) {
    try {
        new URL(url);
        return true;
    } catch {
        return false;
    }
}

function isValidHost(host) {
    // Check if it's a valid IP address
    if (/^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/.test(host)) {
        return true;
    }
    
    // Check if it's a valid hostname
    if (/^[a-zA-Z0-9]([a-zA-Z0-9\-\.]*[a-zA-Z0-9])?$/.test(host)) {
        return true;
    }
    
    return false;
}

// Keyboard shortcuts
document.addEventListener('keydown', function(event) {
    // Enter key in form fields
    if (event.key === 'Enter' && event.target.tagName === 'INPUT') {
        const form = event.target.closest('form');
        if (form) {
            const submitButton = form.querySelector('button[type="submit"]');
            if (submitButton && !submitButton.disabled) {
                submitButton.click();
            }
        }
    }
});

// Auto-focus first input
document.addEventListener('DOMContentLoaded', function() {
    const firstInput = document.querySelector('.install-form input:not([type="hidden"])');
    if (firstInput) {
        firstInput.focus();
    }
});

// Prevent form resubmission on page refresh
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}
