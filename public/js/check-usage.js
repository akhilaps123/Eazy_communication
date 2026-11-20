/**
 * Check Usage Page Functionality - Eazy Communications
 * Figma Node: 189:1199
 */

document.addEventListener('DOMContentLoaded', () => {
  // 1. Mobile Menu Toggle
  const mobileToggle = document.getElementById('mobileMenuToggle');
  const mobileDrawer = document.getElementById('mobileNavDrawer');

  if (mobileToggle && mobileDrawer) {
    mobileToggle.addEventListener('click', () => {
      const isOpen = mobileDrawer.classList.contains('active');
      if (isOpen) {
        mobileDrawer.classList.remove('active');
        mobileToggle.classList.remove('active');
        mobileToggle.setAttribute('aria-expanded', 'false');
      } else {
        mobileDrawer.classList.add('active');
        mobileToggle.classList.add('active');
        mobileToggle.setAttribute('aria-expanded', 'true');
      }
    });

    document.addEventListener('click', (e) => {
      if (!mobileToggle.contains(e.target) && !mobileDrawer.contains(e.target)) {
        mobileDrawer.classList.remove('active');
        mobileToggle.classList.remove('active');
        mobileToggle.setAttribute('aria-expanded', 'false');
      }
    });
  }

  // 2. Check Usage Form Submission
  const form = document.getElementById('checkUsageForm');
  const orderInput = document.getElementById('orderNumberInput');
  const emailInput = document.getElementById('emailAddressInput');
  const messageEl = document.getElementById('checkFormMessage');
  const resultCard = document.getElementById('checkResultCard');

  if (form) {
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      handleCheckUsage();
    });
  }

  function handleCheckUsage() {
    const orderVal = orderInput ? orderInput.value.trim() : '';
    const emailVal = emailInput ? emailInput.value.trim() : '';

    if (!orderVal) {
      showMessage('Please enter your order number (e.g. MM-12345).', 'error');
      orderInput.focus();
      return;
    }

    if (!emailVal || !validateEmail(emailVal)) {
      showMessage('Please enter a valid email address associated with your order.', 'error');
      emailInput.focus();
      return;
    }

    // Hide previous messages
    if (messageEl) {
      messageEl.className = 'check-form-message';
      messageEl.style.display = 'none';
    }

    // Show simulated result
    if (resultCard) {
      // Update dynamic fields
      const orderSpan = document.getElementById('resOrderNumber');
      const emailSpan = document.getElementById('resEmailAddress');
      if (orderSpan) orderSpan.textContent = orderVal.toUpperCase();
      if (emailSpan) emailSpan.textContent = emailVal;

      resultCard.style.display = 'block';
      resultCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
  }

  function showMessage(text, type) {
    if (!messageEl) return;
    messageEl.textContent = text;
    messageEl.className = `check-form-message ${type}`;
    messageEl.style.display = 'block';
    if (resultCard) resultCard.style.display = 'none';
  }

  function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
  }
});
