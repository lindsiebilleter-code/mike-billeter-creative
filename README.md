# Mike Billeter Creative — website

Portfolio and lead-gen site for copywriter Mike Billeter (Sioux Falls, SD), with a hidden 1999 edition.

## Files
Static HTML — no build step, no framework. Upload the folder to any host.
- `index.html`, `services.html`, `process.html`, `about.html`, `contact.html` — the site
- `y2k/*.html` — the 1999 edition, one page per route (`noindex`)
- `css/site.css` — resets, keyframes, hover/focus states; `css/fx.css` — the CRT transition
- `js/config.js` — site URL, email, subject prefix, **form delivery settings**
- `js/contact.js` — validation, mailto builder, delivery adapter, form wiring
- `js/site.js` — modern-edition behavior (scroll motion, hero edit line, sand field, y2k trigger); `js/sand.js` — the interactive hero background; `js/y2k.js` — 1999-edition behavior; `js/fx.js` — transition + typed-word trigger
- `_dc-source/` — the original Design Component sources these pages were generated from (not deployed)
- `assets/` — headshot renditions (`mike-*.jpg`, original kept as `mike-original.jpg`), favicon, `y2k/` retro assets and scoped stylesheet
- `_ds/classical-…/` — the previously bound design system (no longer loaded by the modern edition; kept for reference)
- `PRODUCT.md`, `DESIGN.md`, `CONTENT-NOTES.md` — product context, design system notes, open content questions
- `robots.txt`, `sitemap.xml`
- `content/website-copy.txt` — plain-text extraction of the approved copy document

## Local development
Run `php -S localhost:8000 index.php` and open http://localhost:8000. Links are clean, root-relative URLs (`/services#email-campaigns`), so opening the files straight from disk no longer navigates between pages.

## Clean URLs
Pages are served without `.html`: `/services`, `/about`, `/y2k/services`. `index.php` is a small front controller: the server hands it any path that isn't a real file, and it serves the matching `.html` page, sends `/page/` and `/index` to their clean form with a 301, and returns `404.html` with a real 404 status for anything else. Old `/page.html` links still work: the canonical tag points at the clean URL and `js/fx.js` tidies the address bar. For a true 301 from `/page.html` (recommended), add this to the CloudPanel vhost, inside the `server { … }` block for port 443, above `location / {`:

```nginx
if ($request_uri ~ ^/(.*?)(?:index)?\.html(\?.*)?$) { return 301 /$1$2; }
```

## SEO
`python3 tools/seo.py` regenerates each page's `<head>` SEO block (title, description, canonical, Open Graph/Twitter, icons, one JSON-LD `@graph` with the business, Mike, breadcrumbs, the priced services catalog and the FAQ), rewrites internal links, and rebuilds `sitemap.xml`, `robots.txt` and `llms.txt`. Edit titles and descriptions in `PAGES` at the top of that script, never in the HTML. FAQ answers and page descriptions are read from the pages so the structured data can't drift from the copy. `assets/og-image.jpg` (1200×630) is the social share image. The Y2K pages are `noindex, follow`. Source and draft folders (`_dc-source`, `_ds`, `explorations`, `uploads`, `content`) and the `.md` notes are excluded from deploys.

## Form delivery
The contact forms (modern and Y2K) post to `forms/submit.php`, a small PHP backend that emails each message through **Resend** and keeps a copy. Everything is configured in the browser at **`/admin/forms/`**, not in code:

- **Sending**: Resend API key (Sending access), from name, from address (must be on a domain verified in Resend), pause switch, and a "Send a test email" button
- **Recipients**: send to, CC, BCC (comma-separated); reply goes to the visitor
- **Email**: subject template (`{name}`, `{email}`, `{timeline}`, `{date}`); optional auto-reply to the visitor
- **reCAPTCHA v3**: site key, secret key, score threshold (0.0–1.0), and what to do with low scores (file as spam, or show the visitor an error)
- **Other spam protection**: honeypot (always on), minimum seconds on page, per-visitor hourly limit, max message length, blocked words
- **Entries**: view, search, filter (inbox / spam / didn't send), delete, download as CSV; optional auto-delete after N days

First visit to `/admin/forms/` asks you to set the admin password. Do that right after deploying. To reset a lost password, delete `auth.php` from the data folder.

**Files**: `forms/_lib.php` (shared code), `forms/submit.php` (endpoint), `forms/config.php` (public settings: reCAPTCHA site key, timing), `admin/forms/index.php` (settings page). Data (settings, password hash, entries) goes in `mb-forms-data/` *next to* the web root when the host allows it, otherwise in `forms/data/` (blocked by `.htaccess`; git-ignored). Every data file is a `.php` file that exits immediately, so it can't be downloaded. Needs PHP 7.4+ with cURL (or `allow_url_fopen`).

The form never fakes success: if delivery is paused or not set up, the visitor gets the pre-filled `mailto:` link; if Resend fails, they see an error with the same link and the message is still saved under Entries → Didn't send. Opened from disk (`file://`), the form shows the error state.

## Headshot
Original: `assets/mike-original.jpg` (1734×2048). Renditions: full portrait at 600/1000/1600w (About), 3:4 top-weighted crop at 500/900w (Home), 1:1 face crop at 400/800w (Y2K page, Open Graph). All `<img>` tags carry width/height and `srcset`/`sizes`; nothing is stretched.

## The y2k easter egg
Type `y2k` (any case) anywhere except inside a text field. A rolling three-character buffer resets after 1.5s of silence, so stray earlier keystrokes don't trigger it. The switch runs a CRT-collapse transition (skipped under `prefers-reduced-motion`) and navigates to the matching page under `y2k/`. Exit with **Escape**, the "RETURN TO THE FUTURE" / "EXIT 1999" controls, or by typing `y2k` again. The clue lives in the footer colophon.

## Design workflow
The brief asked for the Impeccable workflow (github.com/pbakaus/impeccable). This environment can't install the Codex plugin, so its artifacts and passes were done by hand: `PRODUCT.md` and `DESIGN.md` are written in its format; the critique / distill / harden / adapt / audit passes are reflected in the build (fluid layout without breakpoints, focus states, reduced-motion, honest form states, no console errors). Run `/audit` and `/polish` from Impeccable against these files when the repo is in Codex.

## Deploying
Static hosting; every script is a plain `<script>` so the pages also work opened from disk.

## Fonts & libraries
Space Grotesk (400/500/700) loads from Google Fonts; GSAP 3.12 + ScrollTrigger from jsDelivr. Self-host both before launch. The hero sand field is dependency-free canvas. Motion is skipped entirely under `prefers-reduced-motion`, and every element GSAP animates is visible without JavaScript.
