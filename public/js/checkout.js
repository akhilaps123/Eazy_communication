/**
 * Checkout Page Logic - Eazy Communications
 * Handles country selection, payment method selection, promo code validation,
 * and order submission.
 */

document.addEventListener('DOMContentLoaded', () => {
  initCountrySelect();

  // Live catalogue (Phase 7): real order placement. No demo promo codes, no
  // payment selection and no simulated confirmation are used in this mode.
  if (document.querySelector('[data-checkout-live]')) {
    initLiveCheckoutSubmit();
    return;
  }

  initPaymentSelection();
  initPromoCode();
  initCheckoutSubmit();
});

/**
 * Live checkout: light client-side checks, then a normal form submission.
 * The server validates everything again and calculates all prices.
 */
function initLiveCheckoutSubmit() {
  const form = document.querySelector('[data-checkout-live]');
  const submitBtn = document.getElementById('btnCheckoutSubmit');
  const postalInput = document.getElementById('postalCodeInput');
  const countryInput = document.getElementById('countryHiddenInput');
  const countrySelect = document.getElementById('countryCustomSelect');
  let submitting = false;

  function flag(el) {
    if (!el) return;
    el.style.borderColor = '#ef4444';
    setTimeout(() => { el.style.borderColor = ''; }, 2000);
  }

  form.addEventListener('submit', (e) => {
    if (submitting) {            // block double clicks
      e.preventDefault();
      return;
    }
    if (countryInput && !countryInput.value) {
      e.preventDefault();
      flag(countrySelect);
      if (countrySelect) countrySelect.focus();
      return;
    }
    if (postalInput && !postalInput.value.trim()) {
      e.preventDefault();
      flag(postalInput.parentElement);
      postalInput.focus();
      return;
    }
    submitting = true;
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = 'Placing order…';
    }
  });
}

/**
 * Custom Country Dropdown
 */
function initCountrySelect() {
  const customSelect = document.getElementById('countryCustomSelect');
  const trigger = document.getElementById('countrySelectTrigger');
  const textElem = document.getElementById('selectedCountryText');
  const hiddenInput = document.getElementById('countryHiddenInput');
  const options = document.querySelectorAll('.checkout-select-option');

  if (!customSelect || !trigger) return;

  // Toggle dropdown on trigger click
  trigger.addEventListener('click', (e) => {
    e.stopPropagation();
    const isOpen = customSelect.classList.contains('active');
    closeAllSelects();
    if (!isOpen) {
      customSelect.classList.add('active');
      customSelect.setAttribute('aria-expanded', 'true');
    }
  });

  // Option selection
  options.forEach((opt) => {
    opt.addEventListener('click', (e) => {
      e.stopPropagation();
      const val = opt.getAttribute('data-value');
      textElem.textContent = opt.textContent.trim();   // visible name; the hidden input gets the value
      textElem.classList.add('has-value');
      if (hiddenInput) hiddenInput.value = val;

      options.forEach((o) => o.classList.remove('selected'));
      opt.classList.add('selected');

      customSelect.classList.remove('active');
      customSelect.setAttribute('aria-expanded', 'false');
    });
  });

  // Close on outside click
  document.addEventListener('click', () => {
    closeAllSelects();
  });

  // Close on Escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      closeAllSelects();
    }
  });

  function closeAllSelects() {
    if (customSelect.classList.contains('active')) {
      customSelect.classList.remove('active');
      customSelect.setAttribute('aria-expanded', 'false');
    }
  }
}

/**
 * Payment Method Selection (Radio Cards)
 */
function initPaymentSelection() {
  const paymentOptions = document.querySelectorAll('.checkout-payment-option');

  paymentOptions.forEach((option) => {
    option.addEventListener('click', () => {
      paymentOptions.forEach((opt) => {
        opt.classList.remove('active');
        opt.setAttribute('aria-checked', 'false');
      });
      option.classList.add('active');
      option.setAttribute('aria-checked', 'true');
    });

    // Keyboard support (Space / Enter to select)
    option.addEventListener('keydown', (e) => {
      if (e.key === ' ' || e.key === 'Enter') {
        e.preventDefault();
        option.click();
      }
    });
  });
}

/**
 * Promo Code Handler
 */
function initPromoCode() {
  const promoInput = document.getElementById('promoCodeInput');
  const applyBtn = document.getElementById('btnApplyPromo');
  const feedback = document.getElementById('promoFeedback');
  const discountVal = document.getElementById('summaryDiscount');
  const totalVal = document.getElementById('summaryTotal');

  if (!promoInput || !applyBtn || !feedback) return;

  const validCodes = {
    EAZY10: 0.1,
    SAVE20: 0.2,
    WELCOME: 5.0,
  };

  applyBtn.addEventListener('click', () => {
    const code = promoInput.value.trim().toUpperCase();

    if (!code) {
      feedback.textContent = 'Please enter a promo code.';
      feedback.className = 'checkout-promo-feedback error';
      return;
    }

    if (validCodes[code] !== undefined) {
      const discountRatio = validCodes[code];
      const baseSubtotal = 34.99;
      let discountAmount = 0;

      if (discountRatio < 1) {
        discountAmount = baseSubtotal * discountRatio;
      } else {
        discountAmount = discountRatio;
      }

      const finalTotal = Math.max(0, baseSubtotal - discountAmount);

      discountVal.textContent = `$${discountAmount.toFixed(2)}`;
      totalVal.textContent = `$${finalTotal.toFixed(2)}`;

      feedback.textContent = `Promo code "${code}" applied!`;
      feedback.className = 'checkout-promo-feedback success';
    } else {
      feedback.textContent = 'Invalid promo code or gift card.';
      feedback.className = 'checkout-promo-feedback error';
    }
  });

  promoInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      applyBtn.click();
    }
  });
}

/**
 * Checkout Submission
 */
function initCheckoutSubmit() {
  const submitBtn = document.getElementById('btnCheckoutSubmit');
  const postalInput = document.getElementById('postalCodeInput');
  const countryInput = document.getElementById('countryHiddenInput');

  if (!submitBtn) return;

  submitBtn.addEventListener('click', (e) => {
    e.preventDefault();

    // Basic validation
    if (postalInput && !postalInput.value.trim()) {
      postalInput.focus();
      postalInput.parentElement.style.borderColor = '#ef4444';
      setTimeout(() => {
        postalInput.parentElement.style.borderColor = '';
      }, 2000);
      return;
    }

    submitBtn.textContent = 'Processing...';
    submitBtn.disabled = true;

    setTimeout(() => {
      submitBtn.textContent = 'Order Confirmed!';
      submitBtn.style.background = '#10b981';

      setTimeout(() => {
        alert('Thank you for your order! Your eSIM activation details have been sent to johndoe1997@gmail.com.');
        // Laravel: home URL comes from <meta name="app-url"> in layouts/app.blade.php
        const appUrlMeta = document.querySelector('meta[name="app-url"]');
        window.location.href = appUrlMeta ? appUrlMeta.content : '/';
      }, 800);
    }, 1200);
  });
}
