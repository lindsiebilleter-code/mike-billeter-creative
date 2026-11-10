# Mike Billeter Writing — website

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
Open `index.html`. Service anchors are ordinary fragments (`services.html#email-campaigns`).

## Form delivery
The contact form never fakes success. Delivery is configured in `js/config.js`:

```js
form: { endpoint: 'https://formspree.io/f/XXXX', accessKey: '', minSeconds: 3 }
```

- **Formspree**: set `endpoint` to your form URL. Subject arrives as `[Website Inquiry] from <name>`, reply-to is the sender.
- **Web3Forms**: `endpoint: 'https://api.web3forms.com/submit'`, `accessKey: '<key>'`.
- **Custom**: any URL accepting a JSON POST (`name, email, message, timeline, subject`) and returning 2xx.
- **Unconfigured** (default): the form validates, then tells the visitor nothing was sent and offers a pre-filled `mailto:` link to mike.billeter@gmail.com with the `[Website Inquiry]` subject.

States: validation (inline, per field, focus moves to the first error), pending (button disabled, "Sending…"), sent, error (message kept in the form + mailto fallback), unconfigured, too-fast. Spam protection: a hidden honeypot field and a 3-second minimum time-to-submit — no CAPTCHA.

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
