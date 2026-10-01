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
    const homeId = sections[0].id;
    let current = homeId;
    setActive(current);

    // Keep each section's latest ratio around rather than only looking at
    // whichever entries happened to be in the most recent callback batch —
    // a fast/smooth jump between sections can otherwise leave the highlight
    // stuck on a section it only passed through on the way to its target.
    const ratios = new Map(sections.map((el) => [el.id, 0]));

    function pickCurrent() {
      let best = null;
      ratios.forEach((ratio, id) => {
        if (ratio > 0 && (!best || ratio > best.ratio)) best = { id, ratio };
      });
      if (best && best.id !== current) {
        current = best.id;
        setActive(current);
      }
    }

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((e) => {
          ratios.set(e.target.id, e.isIntersecting ? e.intersectionRatio : 0);
        });
        pickCurrent();
      },
      { rootMargin: '-40% 0px -50% 0px', threshold: [0, .25, .5, .75, 1] }
    );
    sections.forEach((el) => observer.observe(el));

    // Hard guarantee: being scrolled all the way to the top always means
    // "Home" is current, regardless of anything the observer's band thinks
    // (layout shifts from async content, rounding, etc. shouldn't matter).
    window.addEventListener(
      'scroll',
      () => {
        if (window.scrollY < 2 && current !== homeId) {
          current = homeId;
          setActive(current);
        }
      },
      { passive: true }
    );
  } else if (sections.length) {
    setActive(sections[0].id);
  }
})();