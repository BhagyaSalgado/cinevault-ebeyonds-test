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

  // Scrollspy: underline whichever nav link(s) point at the section currently
  // in view, so "selected" always reflects where the visitor actually is.
  const links = Array.from(nav.querySelectorAll('a[href^="#"]'));
  const sectionIds = Array.from(new Set(links.map((a) => a.getAttribute('href'))))
    .map((href) => href.slice(1))
    .filter(Boolean);
  const sections = sectionIds
    .map((id) => document.getElementById(id))
    .filter(Boolean);

  function setActive(id) {
    links.forEach((a) => {
      a.classList.toggle('is-active', a.getAttribute('href') === `#${id}`);
    });
  }

  if (sections.length && 'IntersectionObserver' in window) {
    let current = sections[0].id;
    setActive(current);

    const observer = new IntersectionObserver(
      (entries) => {
        // Pick the most visible intersecting section rather than just the
        // first one, so fast scrolling past a short section doesn't stick.
        const visible = entries
          .filter((e) => e.isIntersecting)
          .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];
        if (visible) {
          current = visible.target.id;
          setActive(current);
        }
      },
      { rootMargin: '-40% 0px -50% 0px', threshold: [0, .25, .5, .75, 1] }
    );
    sections.forEach((el) => observer.observe(el));
  } else if (sections.length) {
    setActive(sections[0].id);
  }
})();