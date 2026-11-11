# DESIGN.md — Mike Billeter Creative

Two complete visual identities joined by one hidden interaction.

## 1. Modern edition — after grafik.co.nz
Stark, typographic, black on white. Reference: grafik.co.nz (international-typographic-style restraint, giant statement headlines, long-form entries stacked vertically, imagery full-width, almost no chrome). The Classical design system was set aside for this edition at the client's request.

**Ground & ink.** #ffffff and #0a0a0a. No grays except a single #6b6b6b footnote and #bdbdbd for the 3D word cloud.

**Accent.** One: forest green #0b7a3e (5.2:1 on white). Used for the second hero line, prices, strike-throughs and the insertion underline, focus rings, the process progress line and numerals, hover color.

**Type.** Space Grotesk only. 400 for body (18px/1.5), 500 for everything else. Statement headlines at clamp(38px, 8.4vw, 136px), line-height 0.94, letter-spacing -0.035em, with deliberate line breaks. Section headings clamp(28px, 3.6vw, 52px). Prices set tabular. No eyebrows, kickers, folios or marginal notes.

**Layout.** Full-width, padding clamp(16px, 3vw, 40px). Sections alternate between a single 40rem column and a heading-left / text-right split (heading 18–26rem, text 30–44rem) that wraps to one column via flex-wrap — no media queries. Lists and tables use 1px #0a0a0a rules only.

**Imagery.** Headshot full-width in a cropped frame, grayscale at rest, color on hover, with a slow scroll parallax.

**Signature interaction.** The hero background is a three.js ring of ~50 buzzwords (the "bucket" Mike's copy describes) rotating slowly, drifting with the pointer, tightening and fading as the visitor scrolls. Below the fold, the manuscript line: a generic sentence is struck out phrase by phrase, "Says nothing. Cut." appears, and Mike's own line is inserted with a caret.

**Motion (GSAP + ScrollTrigger).** Hero lines rise from a mask on load. `data-reveal` elements fade/rise once at 88% viewport; `data-reveal-group` children stagger. `data-parallax` images scrub ±7%. "That's what I do." scales from 0.86 on scrub. Process: a green progress line draws down the rail, numerals slide in on scrub. Everything is skipped under `prefers-reduced-motion` (content is visible without JS — GSAP only sets initial states when it runs).

**States.** Hover: green. Focus: 2px green ring, 3px offset. Submit button: black outline pill, inverts to black on hover, green on press.

## 2. Y2K edition — 1999, faithfully
Scoped stylesheet `assets/y2k/retro.css` (the supplied 1999 build, prefixed under `.y2k-root`). Arial/Arial Black/Courier New/Georgia; navy #000066, cyan, yellow, lime, purple, silver; beveled `outset`/`inset` controls; marquee; visitor counter; sparkle and bouncing-mail GIFs; pixel-art hero. Accessibility fixes: visible yellow focus rings, blink limited to six cycles, marquee stops under reduced-motion, utility bar (with the exit control) stays visible on phones, aria-current on nav, honest form states. The joke never blocks contact: form, email link and FAQs are all present.

## Shared behaviors
- Hash routes: `#/`, `#/services`, `#/process`, `#/about`, `#/contact`; in-page anchors as `#/services/email-campaigns`.
- Mode persists across routes and, for the current tab only, across reloads (sessionStorage). A new tab always opens modern.
- `theme-color`, title, description and canonical update per route and mode.
