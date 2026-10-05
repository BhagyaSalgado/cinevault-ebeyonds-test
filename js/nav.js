/* Burger menu: opens/closes the off-canvas drawer (all breakpoints), traps
   Escape / overlay clicks, and runs a scrollspy that underlines whichever
   inline nav link points at the section currently in view. */
(function () {
  const btn = document.getElementById('hamburgerBtn');
  const drawer = document.getElementById('drawer');
  const overlay = document.getElementById('navOverlay');
  const nav = document.getElementById('main-nav');
  if (!btn || !drawer || !overlay) return;

  function isOpen() { return drawer.classList.contains('is-open'); }

  function openMenu() {
    drawer.classList.add('is-open');
    overlay.hidden = false;
    btn.setAttribute('aria-expanded', 'true');
    btn.setAttribute('aria-label', 'Close menu');
    const firstLink = drawer.querySelector('a');
    if (firstLink) firstLink.focus();
  }

  function closeMenu({ returnFocus = true } = {}) {
    drawer.classList.remove('is-open');
    overlay.hidden = true;
    btn.setAttribute('aria-expanded', 'false');
    btn.setAttribute('aria-label', 'Open menu');
    if (returnFocus) btn.focus();
  }

  btn.addEventListener('click', () => { isOpen() ? closeMenu() : openMenu(); });
  overlay.addEventListener('click', () => closeMenu({ returnFocus: false }));
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && isOpen()) closeMenu();
  });
  drawer.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', () => closeMenu({ returnFocus: false }));
  });

  // Scrollspy: underline the inline nav link(s) whose target section is in view.
  if (!nav) return;
  const links = Array.from(nav.querySelectorAll('a[href^="#"]'));
  const sectionIds = Array.from(new Set(links.map((a) => a.getAttribute('href').slice(1)))).filter(Boolean);
  const sections = sectionIds.map((id) => document.getElementById(id)).filter(Boolean);
  if (!sections.length) return;

  function setActive(id) {
    links.forEach((a) => a.classList.toggle('is-active', a.getAttribute('href') === `#${id}`));
  }

  const homeId = sections[0].id;
  let current = homeId;
  setActive(current);
  if (!('IntersectionObserver' in window)) return;

  const ratios = new Map(sections.map((el) => [el.id, 0]));
  function pickCurrent() {
    let best = null;
    ratios.forEach((ratio, id) => { if (ratio > 0 && (!best || ratio > best.ratio)) best = { id, ratio }; });
    if (best && best.id !== current) { current = best.id; setActive(current); }
  }
  const observer = new IntersectionObserver((entries) => {
    entries.forEach((e) => ratios.set(e.target.id, e.isIntersecting ? e.intersectionRatio : 0));
    pickCurrent();
  }, { rootMargin: '-40% 0px -50% 0px', threshold: [0, .25, .5, .75, 1] });
  sections.forEach((el) => observer.observe(el));

  // Scrolled fully to the top always means "Home".
  window.addEventListener('scroll', () => {
    if (window.scrollY < 2 && current !== homeId) { current = homeId; setActive(current); }
  }, { passive: true });
})();
