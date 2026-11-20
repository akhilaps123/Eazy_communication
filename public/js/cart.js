/**
 * Shopping Cart Interactivity - Eazy Communications
 * Handles Quantity Changes, Price Updates, and Item Removal
 */

document.addEventListener('DOMContentLoaded', () => {
  // Mobile Navigation Drawer Toggle
  const mobileToggle = document.getElementById('mobileMenuToggle');
  const navDrawer = document.getElementById('mobileNavDrawer');

  if (mobileToggle && navDrawer) {
    mobileToggle.addEventListener('click', () => {
      const isExpanded = mobileToggle.getAttribute('aria-expanded') === 'true';
      mobileToggle.setAttribute('aria-expanded', !isExpanded);
      mobileToggle.classList.toggle('active');
      navDrawer.classList.toggle('open');
      document.body.style.overflow = !isExpanded ? 'hidden' : '';
    });

    // Close on navigation link click
    navDrawer.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', () => {
        mobileToggle.setAttribute('aria-expanded', 'false');
        mobileToggle.classList.remove('active');
        navDrawer.classList.remove('open');
        document.body.style.overflow = '';
      });
    });
  }

  // Live catalogue (Phase 7): items, quantities, totals and the GST number are
  // handled by the server (real forms). The demo calculations below only run
  // on the static sample cart.
  const LIVE = !!document.querySelector('[data-cart-live]');

  if (!LIVE) {
  // Cart Price & Quantity Calculation
  const UNIT_PRICE = 34.99;
  const UNIT_ORIGINAL = 40.99;

  const cartItem = document.getElementById('cartItemJapan');
  const qtyDecreaseBtn = document.getElementById('qtyDecrease');
  const qtyIncreaseBtn = document.getElementById('qtyIncrease');
  const qtyValueEl = document.getElementById('qtyValue');
  const itemTotalCurrentEl = document.getElementById('itemTotalCurrent');
  const itemTotalOriginalEl = document.getElementById('itemTotalOriginal');
  const cartSummaryAmountEl = document.getElementById('cartSummaryAmount');
  const removeBtn = document.getElementById('removeCartItem');

  const cartContentWrap = document.getElementById('cartContentWrap');
  const cartEmptyState = document.getElementById('cartEmptyState');

  let currentQty = 1;

  function updateTotals() {
    // Update all quantity numbers (desktop and mobile)
    document.querySelectorAll('.qty-num').forEach(el => {
      el.textContent = currentQty;
    });

    const totalCurrent = (UNIT_PRICE * currentQty).toFixed(2);
    const totalOriginal = (UNIT_ORIGINAL * currentQty).toFixed(2);

    // Update all current item total displays (desktop and mobile)
    document.querySelectorAll('.cart-item-total-current').forEach(el => {
      el.textContent = `$${totalCurrent}`;
    });

    if (itemTotalOriginalEl) itemTotalOriginalEl.textContent = `$${totalOriginal}`;
    if (cartSummaryAmountEl) cartSummaryAmountEl.textContent = `$${totalCurrent}`;

    // Disable and hide all decrease buttons at qty = 1 (matching Figma node 188:806)
    document.querySelectorAll('.cart-qty-control .qty-btn:first-child').forEach(btn => {
      if (currentQty <= 1) {
        btn.style.opacity = '0';
        btn.style.pointerEvents = 'none';
      } else {
        btn.style.opacity = '1';
        btn.style.pointerEvents = 'auto';
      }
    });
  }

  if (qtyDecreaseBtn) {
    qtyDecreaseBtn.addEventListener('click', (e) => {
      e.preventDefault();
      if (currentQty > 1) {
        currentQty--;
        updateTotals();
      }
    });
  }

  if (qtyIncreaseBtn) {
    qtyIncreaseBtn.addEventListener('click', (e) => {
      e.preventDefault();
      currentQty++;
      updateTotals();
    });
  }

  // Remove Item Handler
  if (removeBtn) {
    removeBtn.addEventListener('click', (e) => {
      e.preventDefault();
      if (cartItem) {
        cartItem.classList.add('removing');
        setTimeout(() => {
          if (cartContentWrap) cartContentWrap.style.display = 'none';
          if (cartEmptyState) cartEmptyState.classList.add('active');
        }, 300);
      }
    });
  }

  // Initial State Setup
  updateTotals();
  } // end static-only demo cart

  // ---------------------------------------------------------------------------
  // GST Number Input Handler
  // ---------------------------------------------------------------------------
  const btnGstPill = document.getElementById('btnGstPill');
  const gstInputBox = document.getElementById('gstInputBox');
  const gstNumberInput = document.getElementById('gstNumberInput');
  const gstApplyBtn = document.getElementById('gstApplyBtn');
  const gstCancelBtn = document.getElementById('gstCancelBtn');
  const gstAppliedCard = document.getElementById('gstAppliedCard');
  const gstAppliedNum = document.getElementById('gstAppliedNum');
  const btnGstRemove = document.getElementById('btnGstRemove');
  const gstFeedbackMsg = document.getElementById('gstFeedbackMsg');

  const STORAGE_KEY = 'eazy_gst_number';

  function showAppliedGst(gst) {
    if (gstAppliedNum) gstAppliedNum.textContent = gst;
    if (btnGstPill) btnGstPill.style.display = 'none';
    if (gstInputBox) gstInputBox.style.display = 'none';
    if (gstAppliedCard) gstAppliedCard.style.display = 'flex';
  }

  function resetGstUI() {
    if (btnGstPill) btnGstPill.style.display = 'inline-flex';
    if (gstInputBox) gstInputBox.style.display = 'none';
    if (gstAppliedCard) gstAppliedCard.style.display = 'none';
    if (gstNumberInput) gstNumberInput.value = '';
    if (gstFeedbackMsg) {
      gstFeedbackMsg.textContent = '';
      gstFeedbackMsg.className = 'gst-feedback-msg';
    }
  }

  // Restore saved GST if present (static demo only — live mode stores it on the server)
  const savedGst = LIVE ? null : sessionStorage.getItem(STORAGE_KEY);
  if (savedGst) {
    showAppliedGst(savedGst);
  }

  // Click "Enter GST Number" to open input field
  if (btnGstPill) {
    btnGstPill.addEventListener('click', (e) => {
      e.preventDefault();
      btnGstPill.style.display = 'none';
      if (gstInputBox) {
        gstInputBox.style.display = 'flex';
        if (gstNumberInput) {
          gstNumberInput.focus();
        }
      }
    });
  }

  // Cancel button hides input and restores button
  if (gstCancelBtn) {
    gstCancelBtn.addEventListener('click', (e) => {
      e.preventDefault();
      resetGstUI();
    });
  }

  // Apply GST button (live mode: the button submits the real GSTIN form)
  if (gstApplyBtn && !LIVE) {
    gstApplyBtn.addEventListener('click', (e) => {
      e.preventDefault();
      applyGst();
    });
  }

  if (gstNumberInput) {
    gstNumberInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && LIVE) {
        return;   // let the browser submit the real GSTIN form
      }
      if (e.key === 'Enter') {
        e.preventDefault();
        applyGst();
      } else if (e.key === 'Escape') {
        resetGstUI();
      }
    });

    gstNumberInput.addEventListener('input', () => {
      if (gstFeedbackMsg) {
        gstFeedbackMsg.textContent = '';
        gstFeedbackMsg.className = 'gst-feedback-msg';
      }
    });
  }

  function applyGst() {
    const val = (gstNumberInput?.value || '').trim().toUpperCase();
    if (!val) {
      if (gstFeedbackMsg) {
        gstFeedbackMsg.textContent = 'Please enter a GST number';
        gstFeedbackMsg.className = 'gst-feedback-msg error';
      }
      return;
    }

    sessionStorage.setItem(STORAGE_KEY, val);
    showAppliedGst(val);
  }

  // Remove GST button (live mode: the button submits the real removal form)
  if (btnGstRemove && !LIVE) {
    btnGstRemove.addEventListener('click', (e) => {
      e.preventDefault();
      sessionStorage.removeItem(STORAGE_KEY);
      resetGstUI();
    });
  }
});
