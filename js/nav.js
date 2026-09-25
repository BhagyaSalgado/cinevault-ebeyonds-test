/* Hamburger menu: toggle open/close, trap focus, close on Escape or overlay click. */
(function () {
  const btn = document.getElementById('hamburgerBtn');
  const nav = document.getElementById('main-nav');
  const overlay = document.getElementById('navOverlay');
  if (!btn || !nav || !overlay) return;

  function isOpen() {
    return nav.classList.contains('is-open');
  }

  function openMenu() {
    nav.classList.add('is-open');
    overlay.hidden = false;
    btn.setAttribute('aria-expanded', 'true');
    btn.setAttribute('aria-label', 'Close menu');
    const firstLink = nav.querySelector('a');
    if (firstLink) firstLink.focus();
  }

  function closeMenu({ returnFocus = true } = {}) {
    nav.classList.remove('is-open');
    overlay.hidden = true;
    btn.setAttribute('aria-expanded', 'false');
    btn.setAttribute('aria-label', 'Open menu');
    if (returnFocus) btn.focus();
  }

  btn.addEventListener('click', () => {
    isOpen() ? closeMenu() : openMenu();
  });

  overlay.addEventListener('click', () => closeMenu({ returnFocus: false }));

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && isOpen()) closeMenu();
  });

  // Close the drawer whenever a nav link is activated (mobile/tablet UX).
  nav.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', () => {
      if (window.matchMedia('(max-width: 899px)').matches) {
        closeMenu({ returnFocus: false });
      }
    });
  });

  // If the viewport grows past the mobile breakpoint while open, reset state.
  window.matchMedia('(min-width: 900px)').addEventListener('change', (e) => {
    if (e.matches) closeMenu({ returnFocus: false });
  });
})();
