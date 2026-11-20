/**
 * Eazy Communications - Main JavaScript
 */

function initMain() {
  // ---------------------------------------------------------------------------
  // 1. Initialize Lenis (Fluid, Watery Smooth Momentum Scrolling)
  // ---------------------------------------------------------------------------
  let lenis = null;
  if (typeof Lenis !== 'undefined') {
    lenis = new Lenis({
      lerp: 0.08, // Silky watery lag/damping
      wheelMultiplier: 0.95, // Natural glide
      touchMultiplier: 1.5,
      smoothWheel: true,
      autoRaf: true,
      anchors: true,
    });
    window.lenis = lenis;

    // Smooth anchor navigation with custom offset and fluid duration
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
      anchor.addEventListener('click', (e) => {
        const href = anchor.getAttribute('href');
        if (href && href !== '#' && href.length > 1) {
          try {
            const target = document.querySelector(href);
            if (target) {
              e.preventDefault();
              lenis.scrollTo(target, {
                offset: -10,
                duration: 1.2,
              });
            }
          } catch (_) {
            // If selector is not a valid CSS query, allow default behavior
          }
        }
      });
    });

    // Smooth scroll to initial hash target if present in URL
    if (window.location.hash && window.location.hash.length > 1) {
      setTimeout(() => {
        try {
          const hashTarget = document.querySelector(window.location.hash);
          if (hashTarget) {
            lenis.scrollTo(hashTarget, {
              offset: -10,
              duration: 1.2,
            });
          }
        } catch (_) {}
      }, 150);
    }
  }

  // ---------------------------------------------------------------------------
  // 2. Mobile Navigation Menu Toggle
  // ---------------------------------------------------------------------------
  const mobileToggle = document.getElementById('mobileMenuToggle');
  const mobileDrawer = document.getElementById('mobileNavDrawer');

  if (mobileToggle && mobileDrawer) {
    mobileToggle.addEventListener('click', () => {
      const isOpen = mobileDrawer.classList.toggle('open');
      mobileToggle.classList.toggle('active');
      mobileToggle.setAttribute('aria-expanded', isOpen);

      // Pause or resume smooth scroll when drawer is opened/closed
      if (window.lenis) {
        if (isOpen) window.lenis.stop();
        else window.lenis.start();
      }
    });

    // Close on navigation link click
    mobileDrawer.querySelectorAll('.header-nav-link').forEach(link => {
      link.addEventListener('click', () => {
        mobileDrawer.classList.remove('open');
        mobileToggle.classList.remove('active');
        mobileToggle.setAttribute('aria-expanded', false);
        if (window.lenis) window.lenis.start();
      });
    });
  }

  // ---------------------------------------------------------------------------
  // 3. eSIM Offers Tab Switching
  // ---------------------------------------------------------------------------
  const offerTabs = document.querySelectorAll('.pill-tab');
  const offerCards = document.querySelectorAll('.offer-card');

  offerTabs.forEach(tab => {
    tab.addEventListener('click', () => {
      offerTabs.forEach(t => t.classList.remove('active'));
      tab.classList.add('active');

      const filter = tab.dataset.filter;
      offerCards.forEach(card => {
        if (!filter || filter === 'all') {
          card.style.display = 'flex';
        } else {
          const category = card.dataset.category;
          if (category === filter || (filter === 'popular' && (category === 'popular' || category === 'best-seller'))) {
            card.style.display = 'flex';
          } else {
            card.style.display = 'none';
          }
        }
      });
    });
  });

  // ---------------------------------------------------------------------------
  // 4. Hero Search Destination Interaction
  // ---------------------------------------------------------------------------
  const searchInput = document.getElementById('heroSearchInput');
  const searchIcon = document.getElementById('heroSearchIcon');

  function handleSearch() {
    if (!searchInput) return;
    const query = searchInput.value.trim().toLowerCase();
    if (!query) {
      offerCards.forEach(card => card.style.display = 'flex');
      return;
    }
    
    // Scroll smoothly to offers section with watery momentum
    const offersSection = document.getElementById('offers');
    if (offersSection) {
      if (window.lenis) {
        window.lenis.scrollTo(offersSection, { offset: -20, duration: 1.2 });
      } else {
        offersSection.scrollIntoView({ behavior: 'smooth' });
      }
    }

    offerCards.forEach(card => {
      const country = card.dataset.country ? card.dataset.country.toLowerCase() : '';
      if (country.includes(query)) {
        card.style.display = 'flex';
      } else {
        card.style.display = 'none';
      }
    });
  }

  if (searchInput) {
    searchInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') handleSearch();
    });
  }
  if (searchIcon) {
    searchIcon.addEventListener('click', handleSearch);
  }



  // ---------------------------------------------------------------------------
  // 6. Global FAQ Accordion Handler ("Got Questions? We’ve Got Answers")
  // ---------------------------------------------------------------------------
  const faqItems = document.querySelectorAll('.faq-item');
  faqItems.forEach(item => {
    const trigger = item.querySelector('.faq-trigger');
    if (!trigger) return;

    trigger.addEventListener('click', () => {
      const isActive = item.classList.contains('active');
      faqItems.forEach(other => {
        if (other !== item) {
          other.classList.remove('active');
          const otherTrigger = other.querySelector('.faq-trigger');
          if (otherTrigger) otherTrigger.setAttribute('aria-expanded', 'false');
        }
      });

      if (isActive) {
        item.classList.remove('active');
        trigger.setAttribute('aria-expanded', 'false');
      } else {
        item.classList.add('active');
        trigger.setAttribute('aria-expanded', 'true');
      }
    });
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initMain);
} else {
  initMain();
}
