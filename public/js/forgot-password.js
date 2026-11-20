/**
 * Forgot Password Page Script - Eazy Communications
 * Handles form validation and reset link feedback.
 */

document.addEventListener('DOMContentLoaded', () => {
  const forgotForm = document.getElementById('forgotForm');
  const identityInput = document.getElementById('recoveryIdentity');
  const feedbackMsg = document.getElementById('forgotFeedback');

  if (forgotForm) {
    forgotForm.addEventListener('submit', (e) => {
      e.preventDefault();

      identityInput?.classList.remove('error');
      if (feedbackMsg) {
        feedbackMsg.className = 'form-feedback';
        feedbackMsg.textContent = '';
      }

      const val = identityInput?.value.trim();
      if (!val) {
        identityInput?.classList.add('error');
        if (feedbackMsg) {
          feedbackMsg.classList.add('error');
          feedbackMsg.textContent = 'Please enter your username or email address.';
        }
        return;
      }

      // Client check passed: send the form to Laravel, which emails the reset
      // link and replies with a message in #forgotFeedback.
      const submitBtn = document.getElementById('btnSubmitReset');
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.style.opacity = '0.7';
        submitBtn.textContent = 'Sending...';
      }

      forgotForm.submit();
    });
  }
});
