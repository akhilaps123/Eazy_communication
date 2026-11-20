/**
 * Japan eSIM Product Detail Page Script
 * Handles plan configurator, specs tabs, FAQ accordion, and interactive states.
 */

document.addEventListener('DOMContentLoaded', () => {
  // 1. Data Type Toggle & Plan Cards Data
  // Live catalogue: the page provides window.EAZY_PLANS (see pages/japan-esim.blade.php);
  // otherwise the original static sample data below is used.
  const assetBase = window.EAZY_ASSET_BASE || '';
  const planData = window.EAZY_PLANS || {
    unlimited: [
      { id: 'u7', title: 'Unlimited - 7 Days', price: '$34.99', crossed: '$40.99', selected: true },
      { id: 'u10', title: 'Unlimited - 10 Days', price: '$44.99', crossed: '$50.99', selected: false },
      { id: 'u14', title: 'Unlimited - 14 Days', price: '$654.99', crossed: '$60.99', selected: false },
      { id: 'u28', title: 'Unlimited - 28 Days', price: '$64.99', crossed: '$70.99', selected: false },
    ],
    fixed: [
      { id: 'f7', title: '5 GB - 7 Days', price: '$12.99', crossed: '$18.99', selected: true },
      { id: 'f10', title: '12 GB - 10 Days', price: '$19.99', crossed: '$27.99', selected: false },
      { id: 'f14', title: '20 GB - 14 Days', price: '$29.99', crossed: '$39.99', selected: false },
      { id: 'f28', title: '40 GB - 28 Days', price: '$49.99', crossed: '$65.99', selected: false },
    ]
  };

  let currentType = window.EAZY_INITIAL_TYPE || 'unlimited';
  const toggleBtns = document.querySelectorAll('.toggle-tab-btn');
  const planCardsGrid = document.getElementById('planCardsGrid');

  function renderPlanCards(type) {
    if (!planCardsGrid) return;
    const items = planData[type] || [];
    planCardsGrid.innerHTML = '';

    items.forEach((item, index) => {
      const card = document.createElement('div');
      card.className = `plan-card ${item.selected ? 'selected' : ''}`;
      card.setAttribute('data-id', item.id);
      card.setAttribute('role', 'radio');
      card.setAttribute('aria-checked', item.selected ? 'true' : 'false');
      card.tabIndex = 0;

      card.innerHTML = `
        <div class="plan-radio-wrap" aria-hidden="true">
          <img src="${assetBase}${item.selected ? 'assets/icons/radio-active.svg' : 'assets/icons/radio-inactive.svg'}" alt="" class="plan-radio-icon">
        </div>
        <div class="plan-card-body">
          <h4 class="plan-card-title">${item.title}</h4>
          <div class="plan-card-prices">
            <span class="plan-price-active">${item.price}</span>
            <span class="plan-price-crossed">${item.crossed}</span>
          </div>
        </div>
      `;

      card.addEventListener('click', () => selectPlanCard(item.id));
      card.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          selectPlanCard(item.id);
        }
      });

      planCardsGrid.appendChild(card);
    });
  }

  function selectPlanCard(id) {
    planData[currentType].forEach(item => {
      item.selected = item.id === id;
    });
    const cards = planCardsGrid.querySelectorAll('.plan-card');
    cards.forEach(c => {
      const isSelected = c.getAttribute('data-id') === id;
      c.classList.toggle('selected', isSelected);
      c.setAttribute('aria-checked', isSelected ? 'true' : 'false');
      const radioImg = c.querySelector('.plan-radio-icon');
      if (radioImg) {
        radioImg.src = assetBase + (isSelected ? 'assets/icons/radio-active.svg' : 'assets/icons/radio-inactive.svg');
      }
    });
  }

  toggleBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      const targetType = btn.getAttribute('data-type');
      if (targetType === currentType) return;
      currentType = targetType;

      toggleBtns.forEach(b => b.classList.toggle('active', b === btn));
      renderPlanCards(currentType);
    });
  });

  // Initial render of cards
  renderPlanCards(currentType);

  // 2. "What Does Japan eSIM Include?" Tabs & Specs Table
  const specData = Object.assign({
    overview: [
      { label: 'Data Allowance', value: 'High-speed 4G LTE & 5G Unlimited' },
      { label: 'Validity Period', value: 'Flexible 7, 10, 14, 28 Days options' },
      { label: 'Calling / SMS', value: 'Data only (VoIP apps like WhatsApp & FaceTime supported)' },
      { label: 'Activation Type', value: 'Instant QR code delivery via email' }
    ],
    network: [
      { label: 'Data Allowance', value: '5GB, 12GB, 20 GB, 40GB to Unlimited Data' },
      { label: 'Validity Period', value: '7 days, 10 days, 14 days to 28 days' },
      { label: 'Countries Covered', value: 'Japan' },
      { label: 'Cellular Network', value: 'KDDI' }
    ],
    activation: [
      { label: 'Delivery Method', value: 'Instant email with setup QR code' },
      { label: 'Activation Steps', value: 'Scan QR code in phone cellular settings' },
      { label: 'Timing', value: 'Validity starts upon connection to local network in Japan' },
      { label: 'Expiration', value: 'Must be activated within 90 days of purchase' }
    ],
    compatibility: [
      { label: 'Device Support', value: 'Unlocked iPhone XS/XR or newer, Pixel 4+, Galaxy S20+' },
      { label: 'Lock Status', value: 'Device must be carrier-unlocked' },
      { label: 'Dual SIM Mode', value: 'Supports Dual SIM Dual Standby (DSDS)' },
      { label: 'Hotspot / Tethering', value: 'Supported at high speed without extra fee' }
    ]
  }, window.EAZY_SPECS || {});

  const specTabBtns = document.querySelectorAll('.spec-tab-btn');
  const specsTableCard = document.getElementById('specsTableCard');

  function renderSpecs(tabKey) {
    if (!specsTableCard || !specData[tabKey]) return;
    const rows = specData[tabKey];
    specsTableCard.innerHTML = rows.map(r => `
      <div class="specs-table-row">
        <span class="spec-label">${r.label}</span>
        <span class="spec-value">${r.value}</span>
      </div>
    `).join('');
  }

  specTabBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      const tabKey = btn.getAttribute('data-tab');
      specTabBtns.forEach(b => b.classList.toggle('active', b === btn));
      renderSpecs(tabKey);
    });
  });

  // Initial render: Network tab active (matching Figma node 159:74)
  renderSpecs('network');

  // 4. "Add to Cart" Button Toast Notice
  const addCartBtn = document.getElementById('addCartBtn');
  if (addCartBtn) {
    addCartBtn.addEventListener('click', () => {
      const selectedPlan = planData[currentType].find(p => p.selected) || planData[currentType][0];
      const tripDate = document.getElementById('tripStartDate')?.value || 'Not set';

      // Live catalogue (Phase 7): save to the real cart; success is shown only
      // after the server confirms. Static sample page: original demo message.
      if (window.EAZY_CART && selectedPlan && selectedPlan.slug) {
        addToRealCart(selectedPlan.slug, document.getElementById('tripStartDate')?.value || '');
        return;
      }

      showToast(`Added ${selectedPlan.title} (${selectedPlan.price}) to cart!`);
    });
  }

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = String(text);
    return div.innerHTML;
  }

  // Normal form POST: for guests Laravel shows the login page and returns here afterwards.
  function submitCartForm(slug, tripDate) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = window.EAZY_CART.url;
    const fields = {
      _token: document.querySelector('meta[name="csrf-token"]')?.content || '',
      plan: slug, quantity: '1', trip_start_date: tripDate,
    };
    Object.keys(fields).forEach((name) => {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = name;
      input.value = fields[name];
      form.appendChild(input);
    });
    document.body.appendChild(form);
    form.submit();
  }

  function addToRealCart(slug, tripDate) {
    if (!window.EAZY_CART.signedIn) {
      submitCartForm(slug, tripDate);
      return;
    }
    addCartBtn.disabled = true;
    fetch(window.EAZY_CART.url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
      },
      body: JSON.stringify({ plan: slug, quantity: 1, trip_start_date: tripDate }),
    })
      .then(async (response) => {
        const data = await response.json().catch(() => ({}));
        if (response.status === 401) {
          submitCartForm(slug, tripDate);
          return;
        }
        if (response.ok && data.success) {
          showToast(escapeHtml(data.message));
          window.location.href = data.cart_url || window.EAZY_CART.cartUrl;
          return;
        }
        showToast(escapeHtml(data.message || 'Sorry, this plan could not be added to your cart.'));
        addCartBtn.disabled = false;
      })
      .catch(() => {
        showToast('Network error — please try again.');
        addCartBtn.disabled = false;
      });
  }

  function showToast(msg) {
    let toast = document.querySelector('.toast-notice');
    if (!toast) {
      toast = document.createElement('div');
      toast.className = 'toast-notice';
      toast.setAttribute('role', 'alert');
      document.body.appendChild(toast);
    }
    toast.innerHTML = `
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#00AEEF" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <path d="M20 6L9 17l-5-5"/>
      </svg>
      <span>${msg}</span>
    `;
    toast.classList.add('show');
    clearTimeout(toast.timeoutId);
    toast.timeoutId = setTimeout(() => {
      toast.classList.remove('show');
    }, 3200);
  }

  // ---------------------------------------------------------------------------
  // 5. Trip Start Date Interactive Calendar Popover
  // ---------------------------------------------------------------------------
  const tripDateWrap = document.getElementById('tripDateWrap');
  const tripDateInput = document.getElementById('tripStartDate');
  const tripCalendarIcon = document.getElementById('tripCalendarIcon');

  if (tripDateWrap && tripDateInput) {
    function parseDate(str) {
      if (!str) return new Date();
      const parts = str.split('-');
      if (parts.length === 3) {
        const d = parseInt(parts[0], 10);
        const m = parseInt(parts[1], 10) - 1;
        const y = parseInt(parts[2], 10);
        if (!isNaN(d) && !isNaN(m) && !isNaN(y)) {
          return new Date(y, m, d);
        }
      }
      return new Date();
    }

    let selectedDate = parseDate(tripDateInput.value);
    let viewMonth = selectedDate.getMonth();
    let viewYear = selectedDate.getFullYear();

    const popover = document.createElement('div');
    popover.className = 'calendar-popover';
    popover.setAttribute('role', 'dialog');
    popover.setAttribute('aria-label', 'Calendar Date Picker');
    tripDateWrap.appendChild(popover);

    const monthNames = [
      'January', 'February', 'March', 'April', 'May', 'June',
      'July', 'August', 'September', 'October', 'November', 'December'
    ];
    const weekdays = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];

    function renderCalendar() {
      popover.innerHTML = '';

      // Header: Month Year + Prev/Next
      const header = document.createElement('div');
      header.className = 'cal-header';

      const title = document.createElement('div');
      title.className = 'cal-month-year';
      title.textContent = `${monthNames[viewMonth]} ${viewYear}`;

      const navWrap = document.createElement('div');
      navWrap.style.display = 'flex';
      navWrap.style.gap = '4px';

      const prevBtn = document.createElement('button');
      prevBtn.type = 'button';
      prevBtn.className = 'cal-nav-btn';
      prevBtn.setAttribute('aria-label', 'Previous month');
      prevBtn.innerHTML = '&#9664;';
      prevBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        viewMonth--;
        if (viewMonth < 0) {
          viewMonth = 11;
          viewYear--;
        }
        renderCalendar();
      });

      const nextBtn = document.createElement('button');
      nextBtn.type = 'button';
      nextBtn.className = 'cal-nav-btn';
      nextBtn.setAttribute('aria-label', 'Next month');
      nextBtn.innerHTML = '&#9654;';
      nextBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        viewMonth++;
        if (viewMonth > 11) {
          viewMonth = 0;
          viewYear++;
        }
        renderCalendar();
      });

      navWrap.appendChild(prevBtn);
      navWrap.appendChild(nextBtn);
      header.appendChild(title);
      header.appendChild(navWrap);
      popover.appendChild(header);

      // Weekdays
      const weekdaysRow = document.createElement('div');
      weekdaysRow.className = 'cal-weekdays';
      weekdays.forEach(wd => {
        const cell = document.createElement('div');
        cell.textContent = wd;
        weekdaysRow.appendChild(cell);
      });
      popover.appendChild(weekdaysRow);

      // Days Grid
      const daysGrid = document.createElement('div');
      daysGrid.className = 'cal-days-grid';

      const firstDayIndex = new Date(viewYear, viewMonth, 1).getDay();
      const daysInMonth = new Date(viewYear, viewMonth + 1, 0).getDate();
      const daysInPrevMonth = new Date(viewYear, viewMonth, 0).getDate();

      // Days from previous month
      for (let i = firstDayIndex - 1; i >= 0; i--) {
        const dayBtn = document.createElement('button');
        dayBtn.type = 'button';
        dayBtn.className = 'cal-day other-month';
        dayBtn.textContent = daysInPrevMonth - i;
        const targetD = daysInPrevMonth - i;
        dayBtn.addEventListener('click', (e) => {
          e.stopPropagation();
          let m = viewMonth - 1;
          let y = viewYear;
          if (m < 0) { m = 11; y--; }
          selectDate(new Date(y, m, targetD));
        });
        daysGrid.appendChild(dayBtn);
      }

      // Days in current month
      const today = new Date();
      for (let day = 1; day <= daysInMonth; day++) {
        const dayBtn = document.createElement('button');
        dayBtn.type = 'button';
        dayBtn.className = 'cal-day';
        dayBtn.textContent = day;

        const isSelected = selectedDate &&
          selectedDate.getDate() === day &&
          selectedDate.getMonth() === viewMonth &&
          selectedDate.getFullYear() === viewYear;

        const isToday = today.getDate() === day &&
          today.getMonth() === viewMonth &&
          today.getFullYear() === viewYear;

        if (isSelected) dayBtn.classList.add('selected');
        if (isToday) dayBtn.classList.add('today');

        dayBtn.addEventListener('click', (e) => {
          e.stopPropagation();
          selectDate(new Date(viewYear, viewMonth, day));
        });

        daysGrid.appendChild(dayBtn);
      }

      // Days in next month to fill grid
      const totalCells = daysGrid.children.length;
      const targetRows = totalCells > 35 ? 42 : 35;
      const remainingCells = targetRows - totalCells;
      for (let nextDay = 1; nextDay <= remainingCells; nextDay++) {
        const dayBtn = document.createElement('button');
        dayBtn.type = 'button';
        dayBtn.className = 'cal-day other-month';
        dayBtn.textContent = nextDay;
        const targetD = nextDay;
        dayBtn.addEventListener('click', (e) => {
          e.stopPropagation();
          let m = viewMonth + 1;
          let y = viewYear;
          if (m > 11) { m = 0; y++; }
          selectDate(new Date(y, m, targetD));
        });
        daysGrid.appendChild(dayBtn);
      }

      popover.appendChild(daysGrid);
    }

    function selectDate(date) {
      selectedDate = date;
      const dd = String(date.getDate()).padStart(2, '0');
      const mm = String(date.getMonth() + 1).padStart(2, '0');
      const yyyy = date.getFullYear();
      tripDateInput.value = `${dd}-${mm}-${yyyy}`;
      tripDateInput.dispatchEvent(new Event('change', { bubbles: true }));
      closeCalendar();
    }

    function openCalendar() {
      selectedDate = parseDate(tripDateInput.value);
      viewMonth = selectedDate.getMonth();
      viewYear = selectedDate.getFullYear();
      renderCalendar();
      popover.classList.add('active');
    }

    function closeCalendar() {
      popover.classList.remove('active');
    }

    function toggleCalendar(e) {
      if (e) e.stopPropagation();
      if (popover.classList.contains('active')) {
        closeCalendar();
      } else {
        openCalendar();
      }
    }

    if (tripCalendarIcon) {
      tripCalendarIcon.addEventListener('click', toggleCalendar);
      tripCalendarIcon.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          toggleCalendar(e);
        }
      });
    }

    tripDateInput.addEventListener('click', toggleCalendar);

    document.addEventListener('click', (e) => {
      if (!tripDateWrap.contains(e.target)) {
        closeCalendar();
      }
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        closeCalendar();
      }
    });
  }
});
