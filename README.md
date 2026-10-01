# CineVault — eBEYONDS Web Developer Interview Evaluation

**Developed by [Bhagya Salgado](https://bhagya-salgado.vercel.app)** ·
[GitHub](https://github.com/BhagyaSalgado)

A responsive HTML5/CSS/JS site with a live TV/movie search (TVMaze API) and a
small PHP backend for the contact form, built against the brief in
*Test instructions – advance.pdf*.

## A note on the Figma design

The evaluation links to a private Figma file (`figma.com/file/6FDTiXOX7dvEmhk9dCJyym`).
Dev Mode access was later granted, and the layout, colors, type scale and
spacing below were rebuilt to match that file exactly, inspected node by
node:

- **Colors** — `#0F0F0F` (header / hero caption), `#1D1D1D` (favorites
  section), `#3C3C3C` (card body / form fields), `#CC9601` (the file's one
  accent color, used for every button), plus its exact text greys
  (`#B7B7B7`, `#878787`, `#EAEAEA`). All defined as CSS variables at the top
  of `css/styles.css`.
- **Type** — the file's scale (54.86px / 36.57px / 32px / 20.57px / 18.29px /
  16px, all at 150% line-height and ‑1.1% letter-spacing) using Oswald +
  Open Sans + Inter. The file itself uses **DIN Alternate** for headings,
  which isn't freely licensable for the web, so Oswald (a similarly
  condensed, bold grotesque) stands in for it — swap the `--font-heading`
  variable if a licensed copy of DIN Alternate is available.
- **Layout** — the header's 115px side padding and 78px height, the
  favourites section's heading+search row, the 427:606 poster cards with
  their circular rating badge and `#3C3C3C` body panel, the hairline-bordered
  contact form and its exact `242×49` gold submit button, and the footer's
  actual two-part structure (address + social row, then a copyright bar)
  all match the file's Dev Mode measurements.
- **Imagery** — the file's own raster images (stock cinema photo, movie
  poster art) aren't reproduced here: the poster art is intentionally real,
  live TVMaze artwork rather than the file's copyrighted movie posters
  (Batman, Spider-Man, etc.), and the hero banner slideshow uses real
  cinema-seat photos (`assets/img/hero-photo-1.jpg` through `-3.jpg`),
  credited in the footer ("Photos by Felix Mooneeram & Serge Kutuzov on
  Unsplash"). `scripts/gen_assets.py` can still regenerate the earlier
  procedurally-generated placeholder art (`hero-1.svg`–`hero-3.svg`,
  `fav-*.svg`, `logo.svg`) if real photos aren't available.

A small addition beyond the Figma file: a **RTL Preview** toggle in the
footer bottom bar (the file has no such control) demonstrates the
layout's RTL support without changing anything in the matched design.

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

### Sending email on Render (Resend instead of SMTP)

Render's free tier blocks all outbound traffic on the SMTP ports
(25/465/587), so Gmail SMTP — correct credentials and all — just hangs and
times out once deployed there; it isn't a config problem, the network path
itself is closed. [Resend](https://resend.com) sends mail over a plain
HTTPS API call (port 443) instead, which isn't blocked, so it's the
transport that actually works on Render's free tier.

1. Sign up at [resend.com](https://resend.com) (free tier is enough for
   this evaluation) and create an API key at
   [resend.com/api-keys](https://resend.com/api-keys).
2. Without a verified domain, Resend's sandbox sender
   (`onboarding@resend.dev`) can only deliver to the email address you
   signed up with — fine for the auto-response/admin-notification pair
   during testing. Verifying a domain (Resend walks you through adding a
   couple of DNS records) lifts that restriction if you need to send to
   arbitrary addresses.
3. In Render's dashboard, open the service → **Environment** tab and set:
   - `MAIL_TRANSPORT` = `resend`
   - `RESEND_API_KEY` = the key from step 1
   - `SMTP_FROM_EMAIL` = the sender address (`onboarding@resend.dev`
     until a domain is verified)
   - `ADMIN_EMAILS` = where the admin notification should land
     (comma-separated if more than one)
4. Save — Render redeploys automatically, and `php/contact.php` picks the
   new transport up via `php/config.php`'s environment-variable mapping,
   no code changes needed.

Locally, the same thing can be done by uncommenting the `mail_transport`
and `resend_api_key` lines in `php/config.local.php` (see
`config.local.php.example`). Gmail SMTP is left as the default because it
works out of the box for local dev; Resend is the one to switch to for the
Render deployment specifically.

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
