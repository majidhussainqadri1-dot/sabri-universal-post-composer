# File 22 — V12.1 CV-053 structured citation/DOI safety audit (13 November 2026)

## D22-SRC-11 — source-proven nested citation URL trust gap
At GitHub main `b760fc8fcca1706df5c9513a605b491464a4bff6`, `Policy_Engine::contains_unsafe_link_protocol()` inspected only top-level scalar payload fields. A nested native `references: [ {url: 'javascript:alert(1)'} ]` was not inspected. If another list item was a usable reference, `has_any_references()` could pass the medical reference gate. The structured citation handler also treated arbitrary nonempty `doi` values as usable source identifiers, despite V12.1 CV-053 explicitly requiring source validation.

This is a **static-code input-trust weakness**, not verified live XSS, SSRF, a real medical publication or actual cross-user impact.

## Narrow correction
- Examine at most 100 native citation rows under known evidence-list keys, including `url/source_url/link_url/href`, source IDs and DOI identifier strings. Reject a nested URI unless it uses HTTP(S), has a parseable host, has no user/password and fits the 2048-byte boundary; block unsafe schemes even inside opaque fields. No URL is fetched by File 22.
- For structured `{url: ...}`, count citation presence only when its URL is safe. For `{doi: ...}`, recognize bounded canonical syntax beginning `10.` followed by 4–9 digits, a slash and a non-whitespace suffix. **The DOI is not verified against a scholarly registry**; the native owner remains responsible for edition/page/real existence, context and rights.
- Preserve opaque IDs such as `source:book:1`; preserve URL/content policy for nonmedical materials as well.

## Exact-head regression
`tests/StructuredCitationSafetyTest.php` covers nested JavaScript/data/FTP/mailto/protocol-relative/userinfo/oversized URLs even alongside valid citations, unsafe opaque embedded source references, valid HTTPS/DOI/native IDs, malformed DOI-only citations and nonmedical shared security holds. Source-only PHPUnit, PHPCS, PHPStan and packaging are mandatory; actual WordPress/Browser staging remains separate.

## Remaining acceptance
- Real File 21 + File 22 payload/schema fields and source owner validation; File 06/12 native citation integration.
- Browser rendering sanitized, no open redirect, no URL fetch/SSRF, actual DOI registration and trusted provenance, documents in Urdu/Arabic, human/editorial review.
- Complete staging T-01…T-50, backup/rollback and deployed ZIP/DB parity; explicit production release approval.

Repository baseline: `b760fc8fcca1706df5c9513a605b491464a4bff6`.
Deployed Version: UNVERIFIED.
DB Version: UNVERIFIED.
Migration State: UNVERIFIED.
Live Verification Status: NOT PERFORMED.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**

## D22-SRC-15 — citation-list overflow without tail verification (13 November 2026)
A follow-up source audit identified that the V12.1 structured-reference link scanner examined only the first 100 list members while accepting a 101+ item list. A trailing malicious URL could remain unchecked when an earlier citation fulfilled the medical reference-presence gate. This is one independent cardinality-boundary defect, **not evidence of a live exploit**.

The corrected File22 common policy refuses any >100 entry reference list with a distinct `citation_reference_limit_exceeded` reason and `security_hold`; the URI scanner itself still processes a bounded leading 100 entries. The error reason is explicitly whitelisted in `Contract_Boundary::safe_rest_error_details()`, while raw references never leave internal error data.

Existing `tests/StructuredCitationSafetyTest.php` now adds exact 100 accepted, 101 benign rejected, and 101st malicious URL denied. `RestErrorDetailsBoundaryTest.php` verifies controlled reason exposure without raw source data. These are PHP-source regressions, not full staging/browser acceptance. Origin citation verification and native owner controls remain required.
