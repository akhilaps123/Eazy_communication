/**
 * New eSIM Page Interactive Controller - Eazy Communications
 * Faithful implementation of Figma node 92:1290 with working dropdown menus
 */

function initNewEsimPage() {
  // 1. Mobile Menu Drawer Toggle
  const mobileToggle = document.getElementById('mobileMenuToggle');
  const mobileDrawer = document.getElementById('mobileNavDrawer');

  if (mobileToggle && mobileDrawer) {
    mobileToggle.addEventListener('click', () => {
      const isExpanded = mobileToggle.getAttribute('aria-expanded') === 'true';
      mobileToggle.setAttribute('aria-expanded', !isExpanded);
      mobileDrawer.classList.toggle('open');
    });

    document.addEventListener('click', (e) => {
      if (!mobileToggle.contains(e.target) && !mobileDrawer.contains(e.target)) {
        mobileToggle.setAttribute('aria-expanded', 'false');
        mobileDrawer.classList.remove('open');
      }
    });
  }

  // 2. Dropdown Menus Management (Sort and Filter Pills)
  const dropdownContainers = document.querySelectorAll('.filter-dropdown');
  const offerCards = document.querySelectorAll('.esim-catalog-grid .offer-card');
  const plansCounter = document.querySelector('.plans-counter');
  const initialCounterText = plansCounter ? plansCounter.textContent : '';

  function closeAllDropdowns() {
    dropdownContainers.forEach((dd) => {
      dd.classList.remove('open');
      const btn = dd.querySelector('button');
      if (btn) btn.setAttribute('aria-expanded', 'false');
    });
  }

  dropdownContainers.forEach((container) => {
    const triggerBtn = container.querySelector('button');
    const menu = container.querySelector('.filter-dropdown-menu');
    const filterType = container.getAttribute('data-filter-type');

    if (!triggerBtn || !menu) return;

    // Toggle dropdown on button click
    triggerBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      const isOpen = container.classList.contains('open');
      closeAllDropdowns();

      if (!isOpen) {
        container.classList.add('open');
        triggerBtn.setAttribute('aria-expanded', 'true');
      }
    });

    // Handle Option Selection inside Menu
    const items = menu.querySelectorAll('.dropdown-item');
    items.forEach((item) => {
      item.addEventListener('click', (e) => {
        e.stopPropagation();

        // Update active class inside this menu
        items.forEach((it) => {
          it.classList.remove('active');
          it.setAttribute('aria-selected', 'false');
        });
        item.classList.add('active');
        item.setAttribute('aria-selected', 'true');

        const selectedValue = item.getAttribute('data-value');
        const selectedText = item.textContent.replace('✓', '').trim();

        // Update button text and active state
        if (filterType === 'sort') {
          const sortText = triggerBtn.querySelector('.sort-btn-text');
          if (sortText) sortText.textContent = selectedText.toUpperCase();
        } else {
          const pillLabel = triggerBtn.querySelector('.pill-label');
          if (selectedValue === 'all') {
            triggerBtn.classList.remove('active');
            const defaultLabels = {
              destination: 'POPULAR DESTINATION',
              coverage: 'COVERAGE TYPE',
              data: 'DATA',
              validity: 'VALIDITY',
              price: 'PRICE',
              speed: 'NETWORK SPEED',
              features: 'FEATURES'
            };
            if (pillLabel && defaultLabels[filterType]) {
              pillLabel.textContent = defaultLabels[filterType];
            }
          } else {
            triggerBtn.classList.add('active');
            if (pillLabel) {
              pillLabel.textContent = selectedText.toUpperCase();
            }
          }

          // Filter cards if destination is selected
          if (filterType === 'destination') {
            filterCardsByDestination(selectedValue);
          }
        }

        // Close dropdown
        container.classList.remove('open');
        triggerBtn.setAttribute('aria-expanded', 'false');
      });
    });
  });

  // Close dropdowns on click outside or Escape key
  document.addEventListener('click', (e) => {
    if (!e.target.closest('.filter-dropdown')) {
      closeAllDropdowns();
    }
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      closeAllDropdowns();
    }
  });

  // Filter Cards Helper Function
  function filterCardsByDestination(destination) {
    let visibleCount = 0;

    offerCards.forEach((card) => {
      const country = card.getAttribute('data-country');
      if (destination === 'all' || country === destination) {
        card.style.display = 'flex';
        // Live catalogue cards carry their plan count; static cards count as one.
        visibleCount += Number(card.getAttribute('data-plans')) || 1;
      } else {
        card.style.display = 'none';
      }
    });

    if (plansCounter) {
      if (destination === 'all') {
        plansCounter.textContent = initialCounterText;
      } else {
        plansCounter.textContent = `Showing ${visibleCount} plan${visibleCount !== 1 ? 's' : ''} for ${destination}`;
      }
    }
  }

  // 3. Smooth Scrolling for Anchor Links (using Lenis when available)
  document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
    anchor.addEventListener('click', function (e) {
      const targetId = this.getAttribute('href');
      if (targetId && targetId !== '#') {
        const targetElement = document.querySelector(targetId);
        if (targetElement) {
          e.preventDefault();
          if (window.lenis) {
            window.lenis.scrollTo(targetElement, { offset: -10, duration: 1.2 });
          } else {
            targetElement.scrollIntoView({ behavior: 'smooth' });
          }
        }
      }
    });
  });

  // 4. Best Sellers Manual Mouse Pointer Scroll Controller (Silky Smooth Momentum)
  const bestsellersSlider = document.getElementById('bestsellersSlider');

  if (bestsellersSlider) {
    let isDown = false;
    let startX = 0;
    let scrollStart = 0;
    let hasDragged = false;
    let velocity = 0;
    let lastX = 0;
    let lastTime = 0;
    let momentumRafId = null;
    let targetWheelScroll = bestsellersSlider.scrollLeft;
    let wheelRafId = null;

    function stopMomentum() {
      if (momentumRafId) {
        cancelAnimationFrame(momentumRafId);
        momentumRafId = null;
      }
    }

    function stopWheel() {
      if (wheelRafId) {
        cancelAnimationFrame(wheelRafId);
        wheelRafId = null;
      }
    }

    // Prevent native browser image ghost-dragging
    bestsellersSlider.addEventListener('dragstart', (e) => {
      e.preventDefault();
    });

    // Mouse Drag to Scroll (Pointer Drag with Momentum Inertia)
    bestsellersSlider.addEventListener('mousedown', (e) => {
      if (e.button !== 0) return; // Only left mouse button
      stopMomentum();
      stopWheel();
      isDown = true;
      hasDragged = false;
      bestsellersSlider.classList.add('grabbing');
      bestsellersSlider.style.scrollBehavior = 'auto';

      startX = e.pageX;
      scrollStart = bestsellersSlider.scrollLeft;
      lastX = e.pageX;
      lastTime = performance.now();
      velocity = 0;
    });

    window.addEventListener('mouseup', () => {
      if (!isDown) return;
      isDown = false;
      bestsellersSlider.classList.remove('grabbing');

      // Start momentum inertia glide if dragged with speed
      if (hasDragged && Math.abs(velocity) > 0.05) {
        let speed = velocity * 16;
        speed = Math.max(-32, Math.min(32, speed)); // Cap maximum flick speed

        function glide() {
          if (Math.abs(speed) > 0.3) {
            bestsellersSlider.scrollLeft += speed;
            speed *= 0.93; // Silky friction decay
            const maxScroll = bestsellersSlider.scrollWidth - bestsellersSlider.clientWidth;
            if (bestsellersSlider.scrollLeft <= 0 || bestsellersSlider.scrollLeft >= maxScroll) {
              speed = 0;
            }
            momentumRafId = requestAnimationFrame(glide);
          } else {
            bestsellersSlider.style.scrollBehavior = 'smooth';
            momentumRafId = null;
          }
        }
        glide();
      } else {
        bestsellersSlider.style.scrollBehavior = 'smooth';
      }

      // Reset hasDragged on next microtask so current click event can be captured and blocked
      setTimeout(() => {
        hasDragged = false;
      }, 50);
    });

    window.addEventListener('mousemove', (e) => {
      if (!isDown) return;
      e.preventDefault();
      const dx = e.pageX - startX;
      if (Math.abs(dx) > 4) {
        hasDragged = true;
      }
      bestsellersSlider.scrollLeft = scrollStart - dx;

      // Track instantaneous velocity with smoothed low-pass filter
      const now = performance.now();
      const dt = now - lastTime;
      if (dt > 8) {
        const vx = (lastX - e.pageX) / dt;
        velocity = velocity * 0.35 + vx * 0.65;
        lastX = e.pageX;
        lastTime = now;
      }
    });

    // Capture-phase click interceptor to stop any accidental click or navigation after dragging
    bestsellersSlider.addEventListener(
      'click',
      (e) => {
        if (hasDragged) {
          e.preventDefault();
          e.stopPropagation();
          e.stopImmediatePropagation();
          return false;
        }
      },
      true // Capture phase to run before any other listener
    );

    // Smooth horizontal mouse wheel scroll with interpolation
    bestsellersSlider.addEventListener(
      'wheel',
      (e) => {
        // If native horizontal scrolling (trackpad or tilt-wheel)
        if (Math.abs(e.deltaX) > Math.abs(e.deltaY)) {
          return; // Handled natively with overscroll-behavior-x: contain
        }

        const maxScroll = bestsellersSlider.scrollWidth - bestsellersSlider.clientWidth;
        if (maxScroll <= 0) return;

        const isAtLeft = bestsellersSlider.scrollLeft <= 0;
        const isAtRight = bestsellersSlider.scrollLeft >= maxScroll - 1;

        if ((e.deltaY < 0 && !isAtLeft) || (e.deltaY > 0 && !isAtRight)) {
          e.preventDefault();
          stopMomentum();

          if (!wheelRafId) {
            targetWheelScroll = bestsellersSlider.scrollLeft;
          }
          targetWheelScroll = Math.max(0, Math.min(maxScroll, targetWheelScroll + e.deltaY * 0.9));

          bestsellersSlider.style.scrollBehavior = 'auto';

          function smoothWheel() {
            const diff = targetWheelScroll - bestsellersSlider.scrollLeft;
            if (Math.abs(diff) > 0.5) {
              bestsellersSlider.scrollLeft += diff * 0.14; // smooth fluid easing
              wheelRafId = requestAnimationFrame(smoothWheel);
            } else {
              bestsellersSlider.scrollLeft = targetWheelScroll;
              bestsellersSlider.style.scrollBehavior = 'smooth';
              wheelRafId = null;
            }
          }

          if (!wheelRafId) {
            wheelRafId = requestAnimationFrame(smoothWheel);
          }
        }
      },
      { passive: false }
    );
  }
}

// Run immediately if ready or on DOMContentLoaded
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initNewEsimPage);
} else {
  initNewEsimPage();
}
