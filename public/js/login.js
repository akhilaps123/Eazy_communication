/**
 * Login Page Script - Eazy Communications
 * Handles form validation, password visibility toggle, and interactive states.
 */

document.addEventListener('DOMContentLoaded', () => {
  const loginForm = document.getElementById('loginForm');
  const passwordInput = document.getElementById('loginPassword');
  const togglePasswordBtn = document.getElementById('toggleLoginPassword');
  const feedbackMsg = document.getElementById('loginFeedback');

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
  if (loginForm) {
    loginForm.addEventListener('submit', (e) => {
      e.preventDefault();

      const email = document.getElementById('loginEmail');

      // Clear previous error state
      [email, passwordInput].forEach(input => input?.classList.remove('error'));
      if (feedbackMsg) {
        feedbackMsg.className = 'form-feedback';
        feedbackMsg.textContent = '';
      }

      // Basic validation
      let isValid = true;
      let errorText = '';

      if (!email.value.trim() || !validateEmail(email.value.trim())) {
        email.classList.add('error');
        isValid = false;
        errorText = 'Please enter a valid email address.';
      } else if (!passwordInput.value) {
        passwordInput.classList.add('error');
        isValid = false;
        errorText = 'Please enter your password.';
      }

      if (!isValid) {
        if (feedbackMsg) {
          feedbackMsg.classList.add('error');
          feedbackMsg.textContent = errorText;
        }
        return;
      }

      // Client checks passed: show the loading state and send the form to Laravel,
      // which verifies the credentials (errors come back in #loginFeedback).
      const submitBtn = document.getElementById('btnSubmitSignIn');
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.style.opacity = '0.7';
        submitBtn.textContent = 'Signing In...';
      }

      loginForm.submit();
    });
  }

  function validateEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  }
});
