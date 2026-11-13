# File 22 V12.1 — Scalar-citation unsafe-scheme source defect (13 November 2026)

## Scope and governing requirement
File22 master-plan WP-033, WP-034, WP-051, CV-053; strict medical evidence and shared protocol safety. Exact source baseline (GitHub only): `00b8626f8a81bbbcad29a7a77da18f83cbf3e123`.

## Confirmed source root cause: D22-SRC-12
`Policy_Engine::contains_unsafe_structured_reference_url()` scanned list-valued citation records only if each record was an array. Plain string references in `references`, `citations`, `sources` and `evidence_references` arrays were skipped. Meanwhile `has_any_references()` could count such non-placeholder strings as evidence. A `['source:book:1','javascript:alert(1)']` list therefore passed the common unsafe-link hold. No actual rendering or live exploit is established from this source inspection.

## Limited fix
Check each scalar citation-list entry against the same prohibited URI-scheme set already used for structured native identifiers. Reject `javascript:`, `vbscript:`, `data:`, `file:`, `blob:`, `ftp:`, `mailto:`, `about:`, and `intent:` even if a second citation is valid. Keep benign native IDs (`source:book:1`), bibliographic text, and proper structured HTTPS/DOI entries. No native backend, schema, payload logging or role changes.

## Regression and acceptance
The new `tests/ScalarCitationSchemeTest.php` invokes the actual common policy under all four strict phases for eight forbidden schemes, plus valid-source continuity and nonmedical shared protection. Exact-head PHP lint, PHPUnit, static analysis, security standards and packaging must pass; PR SHA and main SHA must be verified separately.

This only validates the common source gate. V12.1 still needs native bibliographic provenance, rights review, source edition/page validation, actual browser rendering, File 21 and File 06/12 integration, all 50 staging tests, backup/rollback and live deployed/source parity.

Repository baseline: `00b8626f8a81bbbcad29a7a77da18f83cbf3e123`.
Deployed Version: UNVERIFIED.
DB Version: UNVERIFIED.
Migration State: UNVERIFIED.
Live Verification Status: NOT PERFORMED.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
