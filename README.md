# CineVault — eBEYONDS Web Developer Interview Evaluation

**Developed by [Bhagya Salgado](https://bhagya-salgado.vercel.app)** ·
[GitHub](https://github.com/BhagyaSalgado)

A responsive HTML5/CSS/JS site with a live TV/movie search (TVMaze API) and a
small PHP backend for the contact form, built against the brief in
*Test instructions – advance.pdf*.

## A note on the Figma design

The evaluation links to a private Figma file
(`figma.com/file/6FDTiXOX7dvEmhk9dCJyym`) that requires being signed in with
access granted to that specific file — it couldn't be opened from here, so
the visual design below is an original layout built from the brief's written
section list ("Header", "Main Visual", "Site Introduction", "Collect your
favorites", "Contact Us", "Footer") rather than a pixel match to the Figma
file. All artwork (logo, hero banners, poster art) is procedurally generated
SVG (see `scripts/gen_assets.py`) so there are no licensing questions. Swap
in the real design's assets/colors in `css/styles.css` (CSS variables at the
top) and `assets/img/` if you'd like a closer match.

## What's implemented

**Basic requirements**
- Semantic HTML5, responsive from mobile through desktop
- Tested in Chromium via automated Playwright checks (layout, interactions);
  uses standard CSS/JS with no vendor-specific APIs, so it should behave the
  same in current Firefox/Edge/Safari
- Contact form with client-side *and* server-side required-field validation
- Live search against the [TVMaze API](https://www.tvmaze.com/api) (no key
  needed, CORS-friendly) — chosen over TMDB since TMDB requires an API key
- Search input, "Add to grid", and "Remove from grid" for search results
- Three required static favorite items, plus dynamically added API results

**Optional requirements implemented**
- Creative use of fetched data: star rating, premiere year and genre badges
  on each added card, plus a truncated synopsis
- CSS-only scroll-reveal animations (IntersectionObserver + transitions, no
  extra library) and an auto-rotating hero slideshow
- RTL support: click "RTL Preview" in the header to flip `<html dir>` — the
  whole layout mirrors correctly because the stylesheet uses CSS logical
  properties (`margin-inline`, `inset-inline-start`, etc.) throughout
- Accessibility: skip link, visible focus states, `aria-*` wiring on the
  menu/search/form, labelled fields, `prefers-reduced-motion` support

**Backend**
- `php/contact.php` re-validates every field server-side, stores each
  submission as a record in `data/submissions.json` (file-locked, so
  concurrent submissions can't corrupt it), and sends two emails via
  [PHPMailer](https://github.com/PHPMailer/PHPMailer) over Gmail SMTP:
  - an auto-response to the visitor
  - an admin notification (currently set to a personal Gmail address for
    testing — see below; swap in the brief's real addresses before
    submitting)
- A hidden honeypot field silently drops bot submissions
- `data/.htaccess` blocks direct web access to the stored JSON/log files

### Setting up real email sending (Gmail SMTP)

Email credentials are kept out of the committed code, in a git-ignored
`php/config.local.php` file, so nothing secret ever ends up on GitHub.

1. Copy `php/config.local.php.example` to `php/config.local.php`.
2. Turn on 2-Step Verification on the Gmail account you want to send from
   (if it isn't already): https://myaccount.google.com/signinoptions/two-step-verification
3. Generate an App Password: https://myaccount.google.com/apppasswords —
   name it something like "CineVault" and copy the 16-character password
   it gives you (this is separate from your normal Google password).
4. Open `php/config.local.php` and fill in:
   - `from_email` / `smtp_username` — your Gmail address
   - `admin_emails` — where the admin notification should land (can be the
     same address)
   - `smtp_password` — the App Password from step 3
5. That's it — `php/contact.php` picks these up automatically and sends
   both emails for real over `smtp.gmail.com:587`.

If `php/config.local.php` doesn't exist yet (fresh clone, before step 1),
the form still works — it just falls back to PHP's built-in `mail()`,
which does nothing on a plain local dev server, so submissions still save
correctly but no email goes out until SMTP is configured.

PHPMailer itself is vendored directly in `php/PHPMailer/` (just the three
source files), so there's no Composer install step needed to run this.

## Running it locally

```bash
php -S localhost:8080
```

Then open `http://localhost:8080/`. The search box and grid work immediately
(TVMaze is called directly from the browser); the contact form validates
and saves to `data/submissions.json` locally, and will send real emails
once `php/config.local.php` is set up as described above.

## Deployment

Deployed via Docker on [Render](https://render.com) (free tier) — see the
`Dockerfile`. Vercel and GitHub Pages don't run PHP, so they can only host
the static front end, without a working contact form.

Steps: push `Dockerfile` + `.dockerignore` to GitHub → create a Render Web
Service from the repo → add `php/config.local.php` as a **Secret File**
(Environment tab) with your Gmail credentials → deploy.

Note: the free tier has no persistent disk, so `data/submissions.json`
resets on restart/redeploy — email sending still works fine either way.

## Project structure

```
index.html
css/styles.css
js/
  nav.js              — hamburger drawer (open/close, focus, Escape, overlay)
  hero-slider.js       — main-visual slideshow
  reveal-animations.js — scroll-in animations
  favorites.js         — TVMaze search, add/remove grid logic
  contact-form.js      — client-side validation + AJAX submit
  rtl-toggle.js         — flips <html dir> for the RTL preview
php/
  config.php              — site settings; merges in config.local.php if present
  config.local.php.example — copy to config.local.php and fill in your Gmail + App Password
  contact.php             — validates, stores, and emails a submission
  PHPMailer/src/          — vendored PHPMailer (no Composer needed)
data/
  submissions.json — stored contact submissions (starts empty)
  .htaccess        — blocks direct access to the data folder
assets/img/        — generated SVG logo, hero art, and poster placeholders
scripts/gen_assets.py — regenerates the placeholder SVG artwork
```

## QA notes

Automated checks (Playwright) covered: responsive layout at mobile/tablet/
desktop widths, the hamburger drawer open/close cycle, TVMaze search →
add-to-grid → remove-from-grid (verified against both the live API's schema
via a mocked fetch and the schema itself), contact form validation and a
successful submission round-trip through `contact.php`, and the RTL toggle.

One real bug was caught and fixed during testing: the hidden honeypot field
originally used `position: absolute; left: -9999px`, which is a common
pattern but caused a rendering glitch when combined with the RTL direction
flip in headless Chromium. It now uses the same clip-based hidden-field
technique as the rest of the form's accessibility helpers.
