/* Optional requirement: simple auto-rotating slideshow for the Main Visual. */
(function () {
  const slidesWrap = document.getElementById('heroSlides');
  const dotsWrap = document.getElementById('heroDots');
  if (!slidesWrap || !dotsWrap) return;

  const slides = Array.from(slidesWrap.querySelectorAll('.hero-slide'));
  if (slides.length <= 1) return;

  let current = 0;
  let timer = null;
  const INTERVAL = 5500;

  slides.forEach((_, i) => {
    const dot = document.createElement('button');
    dot.type = 'button';
    dot.role = 'tab';
    dot.setAttribute('aria-label', `Show slide ${i + 1}`);
    dot.setAttribute('aria-selected', i === 0 ? 'true' : 'false');
    dot.addEventListener('click', () => goTo(i, true));
    dotsWrap.appendChild(dot);
  });
  const dots = Array.from(dotsWrap.children);

  function goTo(index, userInitiated) {
    slides[current].classList.remove('is-active');
    dots[current].setAttribute('aria-selected', 'false');
    current = (index + slides.length) % slides.length;
    slides[current].classList.add('is-active');
    dots[current].setAttribute('aria-selected', 'true');
    if (userInitiated) restart();
  }

  function tick() { goTo(current + 1, false); }

  function start() {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    timer = window.setInterval(tick, INTERVAL);
  }
  function stop() { if (timer) window.clearInterval(timer); }
  function restart() { stop(); start(); }

  start();

  const heroSection = document.getElementById('home');
  heroSection.addEventListener('mouseenter', stop);
  heroSection.addEventListener('mouseleave', start);
})();
