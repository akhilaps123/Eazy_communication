/**
 * My Account page interactions
 * (moved unchanged from an inline <script> in the original my-account.html)
 */
document.addEventListener('DOMContentLoaded', () => {
  const dropdowns = document.querySelectorAll('.account-dropdown-wrapper');

  dropdowns.forEach(wrapper => {
    const btn = wrapper.querySelector('.account-pill-btn');
    const label = wrapper.querySelector('.account-pill-label');
    const items = wrapper.querySelectorAll('.account-dropdown-item');

    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const isOpen = wrapper.classList.contains('open');
      // Close any other open dropdown
      dropdowns.forEach(w => {
        if (w !== wrapper) {
          w.classList.remove('open');
          w.querySelector('.account-pill-btn')?.setAttribute('aria-expanded', 'false');
        }
      });
      wrapper.classList.toggle('open', !isOpen);
      btn.setAttribute('aria-expanded', String(!isOpen));
    });

    items.forEach(item => {
      item.addEventListener('click', (e) => {
        e.stopPropagation();
        const val = item.getAttribute('data-value');
        if (label && val) {
          label.textContent = val;
        }
        items.forEach(i => {
          i.classList.remove('active');
          i.setAttribute('aria-selected', 'false');
        });
        item.classList.add('active');
        item.setAttribute('aria-selected', 'true');
        wrapper.classList.remove('open');
        btn.setAttribute('aria-expanded', 'false');
      });
    });
  });

  // Close dropdown on outside click
  document.addEventListener('click', () => {
    dropdowns.forEach(w => {
      w.classList.remove('open');
      w.querySelector('.account-pill-btn')?.setAttribute('aria-expanded', 'false');
    });
  });
});

/**
 * Logout: the "Logout" card submits the hidden CSRF-protected POST form
 * (#logoutForm) instead of following a link.
 */
document.addEventListener('DOMContentLoaded', () => {
  const logoutForm = document.getElementById('logoutForm');
  if (!logoutForm) return;

  document.querySelectorAll('[data-logout]').forEach(trigger => {
    trigger.addEventListener('click', (e) => {
      e.preventDefault();
      logoutForm.submit();
    });
  });
});
