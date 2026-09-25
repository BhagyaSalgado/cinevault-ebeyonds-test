/* Optional requirement: demonstrate the layout mirrors correctly in RTL.
   The stylesheet uses CSS logical properties (margin-inline, inset-inline-*,
   text-align: start, etc.) throughout, so flipping `dir` on <html> is enough
   to re-flow header, drawer nav, cards and footer without a separate
   RTL stylesheet. */
(function () {
  const toggle = document.getElementById('rtlToggle');
  if (!toggle) return;

  toggle.addEventListener('click', () => {
    const isRtl = document.documentElement.dir === 'rtl';
    document.documentElement.dir = isRtl ? 'ltr' : 'rtl';
    toggle.setAttribute('aria-pressed', String(!isRtl));
  });
})();
