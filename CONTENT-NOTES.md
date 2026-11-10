# Content notes — things that need Mike's call

1. **Email Campaigns price.** Homepage summary says "Starts at $450"; the Services section says "Up to 3 emails — $650". Per the brief, $650 is used everywhere.
2. **Retainer summary.** Homepage summary says "$150/hour, three-hour monthly minimum"; the Services section prices 3 hours at $450 (= $150/hr). Both are shown as written; they agree mathematically.
3. **"→ See samples and past projects"** (homepage). No samples, case studies or work files were supplied, so this link is omitted rather than pointed at an empty page. Add a Work route when real material exists.
4. **Testimonials.** The About-page testimonials block is built but disabled (`showTestimonials` prop, `testimonials` array). The two bracketed placeholders are not published. The "my mom" quote sits in that same block in the source doc — it's ready to enable with the real quotes if Mike wants it kept.
5. **Business name.** The copy doc's footer says "Mike Billeter Writing"; the 1999 build says "Mike Billeter Creative". The modern site uses *Writing* throughout; the Y2K edition keeps *Creative* in its wordmark (it reads as the old site) but its footer says *Writing*.
6. **Response-time promises.** "I'll get back within a day or two" and "I usually respond within a day or two" are Mike's own lines and are kept verbatim; nothing stronger was added.
7. **Address.** The Project Agreement lists 1505 W. Wicklow Ln. It is not published on the site (structured data carries city/state only). Confirm whether a street address should appear.
8. **Domain.** `site.config.js` assumes `https://www.mikebilleterwriting.com`. Update when the real domain (and the possible @mikebilleter.com address Scott asked about) is decided.
9. **Em dashes.** The doc mixes spaced and unspaced dashes; the modern site normalizes to spaced " — ". The Y2K edition keeps the unspaced dashes from the 1999 build.
10. **Legal Docs / Project Agreement.** Not part of the public site; retained in `content/website-copy.txt` for reference.
