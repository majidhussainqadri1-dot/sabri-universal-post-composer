# File 22 V12.1 — WP-019/WP-021: native textarea semantics and mixed-direction authoring (13 Nov 2026)

## Source evidence and root cause D22-SRC-14
At GitHub HEAD `952b5c99002651ed8fec9e7ca41156dea6ab73f1`, `Workflow_Surface::field()` rendered a schema field of type `textarea` as a one-line HTML `<input type="text">` unless its key was in the special rich-text allowlist. The HTML did not explicitly provide direction selection for title, rich content, nonrich multiline text, or preview body, despite V12.1 Urdu/Arabic/English authoring and RTL/LTR requirements.

This is a confirmed HTML rendering defect in source code, not a claim of tested browser appearance or a verified user report.

## Focused correction
- Render ordinary native `textarea` fields with a real multiline `<textarea>` including required, accessibility and privacy attributes.
- Set `dir="auto"` for ordinary title/text fields and both the visible rich-text editor and its native source textarea.
- Independently set `dir="auto"` for preview title/body (not the containing English draft/status line). Retain actual `type="url"`, `email`, numeric and date inputs unchanged.
- Preserve responsive logical CSS, editor sanitation, native content schema, accessibility labels, required fields and server ownership.

## Regression and remaining browser gates
New `tests/MultilingualTextareaPresentationTest.php` calls the actual private `Workflow_Surface` rendering methods and checks multiline textarea semantics, auto bidi on plain/rich/preview, and unchanged URL semantics.

Real staging is still required for right-to-left cursor selection, Urdu/Arabic mixed punctuation, pasted URLs, screen reader, spellcheck, keyboard traps, 200%-400% zoom, mobile device keyboards, Safari/Firefox/Chromium and WCAG compliance. No screenshot/browser acceptance is claimed.

Source baseline: `952b5c99002651ed8fec9e7ca41156dea6ab73f1`.
Deployed Version: UNVERIFIED.
DB Version: UNVERIFIED.
Migration State: UNVERIFIED.
Live Verification Status: NOT PERFORMED.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
