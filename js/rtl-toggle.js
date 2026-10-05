/* Optional requirement: RTL support.
   The stylesheet uses CSS logical properties (margin-inline, padding-inline,
   inset-inline-*, text-align: start/end) throughout, so setting `dir="rtl"`
   on <html> mirrors the whole layout without a separate RTL stylesheet.

   There is deliberately no visible toggle on the page (the Figma design has
   none). To preview RTL, open the site with ?dir=rtl — e.g. index.html?dir=rtl
   — or run  document.documentElement.dir = 'rtl'  in the console. */
(function () {
  const params = new URLSearchParams(window.location.search);
  const dir = params.get('dir');
  if (dir === 'rtl' || dir === 'ltr') document.documentElement.dir = dir;
})();
