/**
 * eSIM Support & Troubleshooting page interactions
 * (moved unchanged from an inline <script> in the original esim-support.html)
 */
document.addEventListener('DOMContentLoaded', () => {
  // 1. Interactive Dropdowns for Troubleshooting Flow
  function setupDropdown(boxId, triggerId, dropdownId, textId) {
    const box = document.getElementById(boxId);
    const trigger = document.getElementById(triggerId);
    const dropdown = document.getElementById(dropdownId);
    const text = document.getElementById(textId);
    if (!trigger || !dropdown) return;

    trigger.addEventListener('click', (e) => {
      e.stopPropagation();
      const isOpen = box.classList.contains('active');
      document.querySelectorAll('.support-select-box').forEach(b => b.classList.remove('active'));
      if (!isOpen) {
        box.classList.add('active');
        trigger.setAttribute('aria-expanded', 'true');
      } else {
        trigger.setAttribute('aria-expanded', 'false');
      }
    });

    dropdown.querySelectorAll('.support-select-option').forEach(opt => {
      opt.addEventListener('click', (e) => {
        e.stopPropagation();
        dropdown.querySelectorAll('.support-select-option').forEach(o => o.classList.remove('active'));
        opt.classList.add('active');
        text.textContent = opt.textContent;
        box.classList.remove('active');
        trigger.setAttribute('aria-expanded', 'false');
      });
    });
  }

  setupDropdown('issueSelectBox', 'issueSelectTrigger', 'issueDropdown', 'selectedIssueText');
  setupDropdown('deviceSelectBox', 'deviceSelectTrigger', 'deviceDropdown', 'selectedDeviceText');

  document.addEventListener('click', () => {
    document.querySelectorAll('.support-select-box').forEach(b => {
      b.classList.remove('active');
      const t = b.querySelector('.support-select-trigger');
      if (t) t.setAttribute('aria-expanded', 'false');
    });
  });

  // Quick help card click syncs with issue dropdown
  document.querySelectorAll('.support-card').forEach(card => {
    card.addEventListener('click', (e) => {
      const issueVal = card.getAttribute('data-issue');
      if (issueVal) {
        const opt = document.querySelector(`.support-select-option[data-value="${issueVal}"]`);
        if (opt) {
          document.querySelectorAll('#issueDropdown .support-select-option').forEach(o => o.classList.remove('active'));
          opt.classList.add('active');
          const text = document.getElementById('selectedIssueText');
          if (text) text.textContent = opt.textContent;
        }
      }
    });
  });
});
