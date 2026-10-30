/**
 * Practical 5: Student Registration Form Validation & Password Strength
 * Compatible with Practical 7 (JSON/CSV File Mode) and Practical 8 (MySQL Database Mode)
 * File: js/validation.js
 */

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('register-form');
    if (!form) return;

    // Elements
    const fullNameInput = document.getElementById('fullname');
    const emailInput = document.getElementById('email');
    const phoneInput = document.getElementById('phone');
    const departmentSelect = document.getElementById('department');
    const yearSelect = document.getElementById('year');
    const passwordInput = document.getElementById('create-password');
    const confirmPasswordInput = document.getElementById('confirm-password');
    const termsCheckbox = document.getElementById('terms-agree');

    // Storage mode selector radio buttons (Minimal Corner Toggle)
    const modeRadios = document.querySelectorAll('input[name="storage_mode"]');
    const modeContainers = document.querySelectorAll('.corner-radio-pill');

    // =========================================================
    // 1. Storage Mode Switcher
    // =========================================================
    function updateStorageMode(mode) {
        modeContainers.forEach(container => {
            const radio = container.querySelector('input[type="radio"]');
            if (radio && radio.value === mode) {
                container.classList.add('active');
                radio.checked = true;
            } else {
                container.classList.remove('active');
            }
        });

        // Set form destination based on mode (leave button text clean as "Register")
        if (mode === 'db') {
            form.setAttribute('action', '../php/process_registration_db.php');
            form.setAttribute('method', 'POST');
        } else {
            form.setAttribute('action', '../php/process_registration_file.php');
            form.setAttribute('method', 'POST');
        }
    }

    modeRadios.forEach(radio => {
        radio.addEventListener('change', () => {
            updateStorageMode(radio.value);
        });
    });

    // Default to File mode for Practical 7 (or check URL parameter ?mode=db)
    const urlParams = new URLSearchParams(window.location.search);
    const initialMode = urlParams.get('mode') === 'db' ? 'db' : 'file';
    updateStorageMode(initialMode);

    // =========================================================
    // 2. Regular Expressions & Validation Rules
    // =========================================================
    const REGEX_NAME = /^[a-zA-Z\s]{3,50}$/;
    const REGEX_EMAIL = /^[a-zA-Z0-9._%+-]+@([a-zA-Z0-9.-]+\.)?[a-zA-Z]{2,}$/;
    const REGEX_PHONE = /^[1-9]\d{9}$/;
    const REGEX_PASSWORD_UPPER = /[A-Z]/;
    const REGEX_PASSWORD_LOWER = /[a-z]/;
    const REGEX_PASSWORD_NUMBER = /[0-9]/;
    const REGEX_PASSWORD_SPECIAL = /[@$!%*?&#]/;

    // Helper: set field error state
    function setError(inputElement, errorElementId, message) {
        const errorEl = document.getElementById(errorElementId);
        if (errorEl) {
            errorEl.textContent = message;
            errorEl.classList.add('visible');
        }
        if (inputElement) {
            const container = inputElement.closest('.input-with-icon') || inputElement.closest('.select-with-icon');
            if (container) {
                container.classList.add('has-error');
                container.classList.remove('is-valid');
            } else {
                inputElement.classList.add('input-invalid');
                inputElement.classList.remove('input-valid');
            }
        }
        return false;
    }

    // Helper: set field valid state
    function setSuccess(inputElement, errorElementId) {
        const errorEl = document.getElementById(errorElementId);
        if (errorEl) {
            errorEl.textContent = '';
            errorEl.classList.remove('visible');
        }
        if (inputElement) {
            const container = inputElement.closest('.input-with-icon') || inputElement.closest('.select-with-icon');
            if (container) {
                container.classList.remove('has-error');
                container.classList.add('is-valid');
            } else {
                inputElement.classList.remove('input-invalid');
                inputElement.classList.add('input-valid');
            }
        }
        return true;
    }

    // Validators
    function validateFullName() {
        const val = fullNameInput ? fullNameInput.value.trim() : '';
        if (!val) {
            return setError(fullNameInput, 'error-fullname', 'Full name is required.');
        }
        if (!REGEX_NAME.test(val)) {
            return setError(fullNameInput, 'error-fullname', 'Name must be 3-50 letters and spaces only.');
        }
        return setSuccess(fullNameInput, 'error-fullname');
    }

    function validateEmail() {
        const val = emailInput ? emailInput.value.trim() : '';
        if (!val) {
            return setError(emailInput, 'error-email', 'Academic email ID is required.');
        }
        if (!REGEX_EMAIL.test(val)) {
            return setError(emailInput, 'error-email', 'Please enter a valid email address.');
        }
        return setSuccess(emailInput, 'error-email');
    }

    function validatePhone() {
        const val = phoneInput ? phoneInput.value.trim() : '';
        if (!val) {
            return setError(phoneInput, 'error-phone', 'Mobile number is required.');
        }
        if (val.startsWith('0')) {
            return setError(phoneInput, 'error-phone', 'Mobile number must start with a digit between 1-9.');
        }
        if (!REGEX_PHONE.test(val)) {
            return setError(phoneInput, 'error-phone', 'Enter a valid 10-digit mobile number starting with 1-9.');
        }
        return setSuccess(phoneInput, 'error-phone');
    }

    function validateDepartment() {
        const val = departmentSelect ? departmentSelect.value : '';
        if (!val) {
            return setError(departmentSelect, 'error-department', 'Please select your department.');
        }
        return setSuccess(departmentSelect, 'error-department');
    }

    function validateYear() {
        const val = yearSelect ? yearSelect.value : '';
        if (!val) {
            return setError(yearSelect, 'error-year', 'Please select your academic year.');
        }
        return setSuccess(yearSelect, 'error-year');
    }

    function validateGender() {
        const selected = document.querySelector('input[name="gender"]:checked');
        const errorEl = document.getElementById('error-gender');
        if (!selected) {
            if (errorEl) {
                errorEl.textContent = 'Please specify your gender.';
                errorEl.classList.add('visible');
            }
            return false;
        }
        if (errorEl) {
            errorEl.textContent = '';
            errorEl.classList.remove('visible');
        }
        return true;
    }

    function validatePassword() {
        const val = passwordInput ? passwordInput.value : '';
        if (!val) {
            return setError(passwordInput, 'error-password', 'Password is required.');
        }
        if (val.length < 8) {
            return setError(passwordInput, 'error-password', 'Password must be at least 8 characters long.');
        }
        if (!REGEX_PASSWORD_UPPER.test(val) || !REGEX_PASSWORD_LOWER.test(val) || !REGEX_PASSWORD_NUMBER.test(val) || !REGEX_PASSWORD_SPECIAL.test(val)) {
            return setError(passwordInput, 'error-password', 'Must include uppercase, lowercase, number, and special character.');
        }
        return setSuccess(passwordInput, 'error-password');
    }

    function validateConfirmPassword() {
        const pw = passwordInput ? passwordInput.value : '';
        const cpw = confirmPasswordInput ? confirmPasswordInput.value : '';
        if (!cpw) {
            return setError(confirmPasswordInput, 'error-confirm-password', 'Please confirm your password.');
        }
        if (pw !== cpw) {
            return setError(confirmPasswordInput, 'error-confirm-password', 'Passwords do not match.');
        }
        return setSuccess(confirmPasswordInput, 'error-confirm-password');
    }

    function validateTerms() {
        const errorEl = document.getElementById('error-terms');
        if (!termsCheckbox || !termsCheckbox.checked) {
            if (errorEl) {
                errorEl.textContent = 'You must accept the terms and campus regulations to proceed.';
                errorEl.classList.add('visible');
            }
            return false;
        }
        if (errorEl) {
            errorEl.textContent = '';
            errorEl.classList.remove('visible');
        }
        return true;
    }

    // =========================================================
    // 3. Password Strength Meter & Interactive Checklist
    // =========================================================
    function updatePasswordStrength() {
        const pw = passwordInput ? passwordInput.value : '';
        const seg1 = document.getElementById('strength-seg-1');
        const seg2 = document.getElementById('strength-seg-2');
        const seg3 = document.getElementById('strength-seg-3');
        const seg4 = document.getElementById('strength-seg-4');
        const strengthLabel = document.getElementById('strength-label');

        const reqLength = document.getElementById('req-length');
        const reqUpper = document.getElementById('req-upper');
        const reqLower = document.getElementById('req-lower');
        const reqNumber = document.getElementById('req-number');
        const reqSpecial = document.getElementById('req-special');

        // Checklist checks
        const hasLength = pw.length >= 8;
        const hasUpper = REGEX_PASSWORD_UPPER.test(pw);
        const hasLower = REGEX_PASSWORD_LOWER.test(pw);
        const hasNumber = REGEX_PASSWORD_NUMBER.test(pw);
        const hasSpecial = REGEX_PASSWORD_SPECIAL.test(pw);

        const updateReq = (el, met) => {
            if (!el) return;
            const icon = el.querySelector('.material-symbols-outlined');
            if (met) {
                el.classList.add('met');
                if (icon) icon.textContent = 'check_circle';
            } else {
                el.classList.remove('met');
                if (icon) icon.textContent = 'radio_button_unchecked';
            }
        };

        updateReq(reqLength, hasLength);
        updateReq(reqUpper, hasUpper);
        updateReq(reqLower, hasLower);
        updateReq(reqNumber, hasNumber);
        updateReq(reqSpecial, hasSpecial);

        // Calculate score
        let score = 0;
        if (hasLength) score++;
        if (hasUpper && hasLower) score++;
        if (hasNumber) score++;
        if (hasSpecial) score++;

        // Reset segments
        [seg1, seg2, seg3, seg4].forEach(seg => {
            if (seg) {
                seg.className = 'strength-segment';
            }
        });

        if (!pw) {
            if (strengthLabel) {
                strengthLabel.textContent = 'None';
                strengthLabel.style.color = '';
            }
            return;
        }

        if (score <= 1) {
            if (seg1) seg1.classList.add('strength-weak');
            if (strengthLabel) {
                strengthLabel.textContent = 'Weak';
                strengthLabel.style.color = '#ef4444';
            }
        } else if (score === 2) {
            if (seg1) seg1.classList.add('strength-fair');
            if (seg2) seg2.classList.add('strength-fair');
            if (strengthLabel) {
                strengthLabel.textContent = 'Fair';
                strengthLabel.style.color = '#f59e0b';
            }
        } else if (score === 3) {
            if (seg1) seg1.classList.add('strength-good');
            if (seg2) seg2.classList.add('strength-good');
            if (seg3) seg3.classList.add('strength-good');
            if (strengthLabel) {
                strengthLabel.textContent = 'Good';
                strengthLabel.style.color = '#3b82f6';
            }
        } else {
            if (seg1) seg1.classList.add('strength-strong');
            if (seg2) seg2.classList.add('strength-strong');
            if (seg3) seg3.classList.add('strength-strong');
            if (seg4) seg4.classList.add('strength-strong');
            if (strengthLabel) {
                strengthLabel.textContent = 'Strong';
                strengthLabel.style.color = '#10b981';
            }
        }
    }

    // =========================================================
    // 4. Attach Real-Time Event Listeners
    // =========================================================
    if (fullNameInput) {
        fullNameInput.addEventListener('input', validateFullName);
        fullNameInput.addEventListener('blur', validateFullName);
    }

    if (emailInput) {
        emailInput.addEventListener('input', validateEmail);
        emailInput.addEventListener('blur', validateEmail);
    }

    if (phoneInput) {
        phoneInput.addEventListener('input', validatePhone);
        phoneInput.addEventListener('blur', validatePhone);
    }

    if (departmentSelect) {
        departmentSelect.addEventListener('change', validateDepartment);
    }

    if (yearSelect) {
        yearSelect.addEventListener('change', validateYear);
    }

    document.querySelectorAll('input[name="gender"]').forEach(radio => {
        radio.addEventListener('change', validateGender);
    });

    if (passwordInput) {
        passwordInput.addEventListener('input', () => {
            updatePasswordStrength();
            validatePassword();
            if (confirmPasswordInput && confirmPasswordInput.value) {
                validateConfirmPassword();
            }
        });
        passwordInput.addEventListener('blur', validatePassword);
    }

    if (confirmPasswordInput) {
        confirmPasswordInput.addEventListener('input', validateConfirmPassword);
        confirmPasswordInput.addEventListener('blur', validateConfirmPassword);
    }

    if (termsCheckbox) {
        termsCheckbox.addEventListener('change', validateTerms);
    }

    // =========================================================
    // 5. Form Submission Handler
    // =========================================================
    form.addEventListener('submit', (e) => {
        const isNameValid = validateFullName();
        const isEmailValid = validateEmail();
        const isPhoneValid = validatePhone();
        const isDeptValid = validateDepartment();
        const isYearValid = validateYear();
        const isGenderValid = validateGender();
        const isPwValid = validatePassword();
        const isCpwValid = validateConfirmPassword();
        const isTermsValid = validateTerms();

        const formIsValid = isNameValid && isEmailValid && isPhoneValid && isDeptValid &&
                            isYearValid && isGenderValid && isPwValid && isCpwValid &&
                            isTermsValid;

        if (!formIsValid) {
            e.preventDefault();
            const firstError = document.querySelector('.error-msg.visible');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            return false;
        }

        return true;
    });
});
