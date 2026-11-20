/**
 * Phase 9E — customer eSIM page: copy buttons for the manual installation details.
 * Values are only read from the page; nothing is sent anywhere.
 */
document.addEventListener('DOMContentLoaded', () => {
  const status = document.querySelector('[data-copy-status]');

  document.querySelectorAll('button[data-copy]').forEach((button) => {
    const original = button.textContent;

    button.addEventListener('click', () => {
      const input = document.getElementById(button.getAttribute('data-copy'));
      if (!input) return;

      let finished = false;
      const done = (ok) => {
        if (finished) return;
        finished = true;
        if (!ok) { input.focus(); input.select(); }
        button.textContent = ok ? 'Copied' : 'Press Ctrl+C';
        if (status) status.textContent = ok ? 'Copied to the clipboard.' : 'Selected — press Ctrl+C (or ⌘+C) to copy.';
        setTimeout(() => { button.textContent = original; }, 2000);
      };
      const legacy = () => {
        input.select();
        let ok = false;
        try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
        done(ok);
      };

      if (navigator.clipboard && window.isSecureContext) {
        // Some browsers never settle without clipboard permission: fall back after 1.5 s.
        setTimeout(() => done(false), 1500);
        navigator.clipboard.writeText(input.value).then(() => done(true), legacy);
      } else {
        legacy();
      }
    });
  });
});
