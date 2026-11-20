/**
 * Registration Page Script - Eazy Communications
 * Handles form validation, password visibility toggle, and interactive states.
 */

document.addEventListener('DOMContentLoaded', () => {
  const regForm = document.getElementById('registrationForm');
  const passwordInput = document.getElementById('regPassword');
  const togglePasswordBtn = document.getElementById('togglePassword');
  const feedbackMsg = document.getElementById('formFeedback');

  // Password Visibility Toggle
  if (togglePasswordBtn && passwordInput) {
    togglePasswordBtn.addEventListener('click', () => {
      const isPassword = passwordInput.type === 'password';
      passwordInput.type = isPassword ? 'text' : 'password';
      togglePasswordBtn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
      togglePasswordBtn.style.opacity = isPassword ? '1' : '0.6';
    });
  }

  // Form Submission & Validation
  if (regForm) {
    regForm.addEventListener('submit', (e) => {
      e.preventDefault();

      const fullName = document.getElementById('regFullName');
      const email = document.getElementById('regEmail');
      const terms = document.getElementById('regTerms');
      const passwordConfirm = document.getElementById('regPasswordConfirm');

      // Clear previous error state
      [fullName, email, passwordInput, passwordConfirm].forEach(input => input?.classList.remove('error'));
      if (feedbackMsg) {
        feedbackMsg.className = 'form-feedback';
        feedbackMsg.textContent = '';
      }

      // Basic validation
      let isValid = true;
      let errorText = '';

      if (!fullName.value.trim()) {
        fullName.classList.add('error');
        isValid = false;
        errorText = 'Please enter your full name.';
      } else if (!email.value.trim() || !validateEmail(email.value.trim())) {
        email.classList.add('error');
        isValid = false;
        errorText = 'Please enter a valid email address.';
      } else if (!passwordInput.value || passwordInput.value.length < 8) {
        passwordInput.classList.add('error');
        isValid = false;
        errorText = 'Password must be at least 8 characters.';
      } else if (passwordConfirm && passwordConfirm.value !== passwordInput.value) {
        passwordConfirm.classList.add('error');
        isValid = false;
        errorText = 'The two passwords do not match.';
      } else if (!terms.checked) {
        isValid = false;
        errorText = 'You must agree to the Terms of Condition and Privacy Policy.';
      }

      if (!isValid) {
        if (feedbackMsg) {
          feedbackMsg.classList.add('error');
          feedbackMsg.textContent = errorText;
        }
        return;
      }

      // Client checks passed: show the loading state and send the form to Laravel,
      // which validates again and creates the account (errors come back in #formFeedback).
      const submitBtn = document.getElementById('btnSubmitSignUp');
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.style.opacity = '0.7';
        submitBtn.textContent = 'Signing Up...';
      }

      regForm.submit();
    });
  }

  function validateEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  }
});
