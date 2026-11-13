# File 22 — V12.1 WP-033/WP-034: medical source-reference presence boundary (13 November 2026)

## Exact repository source
GitHub baseline main SHA: `29ecb8f93b24aa16edab042a3c7741c01789810d`. Source candidate `1.0.0-rc.3`. The exact deployed version, database version, migration state and live source parity are **unverified**.

## Source-confirmed D22-SRC-08: invalid reference arrays bypass source requirement
`Policy_Engine::has_any_references()` originally accepted **any nonempty array**, including `references:[null]`, `references:['']`, or `references:[{'unrelated':true}]`. During strict medical `validate/preview/submit/revision` phases this made `medical_references_required` disappear despite no usable citation, undermining V12.1 common evidence gate. This is a source-level validation defect; no live publication or real patient exposure is established.

## Limited correction
- Keep the reference list as optional during drafting.
- In strict medical phases, require a usable non-placeholder citation string or a native structured citation with one bounded scalar locator (`reference/citation/source_reference/url/doi/locator/source/identifier`).
- Reject placeholder values, null/false/empty/array-only rows and unrecognized structured records.
- Limit list scanning to 100 items and reference scalars to 2048 bytes; no arbitrary nested recursion and no arbitrary citation body stored/logged.
- Preserve valid existing `source:book:1` and English/Urdu/Arabic free-text native references. **Do not claim** to verify the citation's edition, page, DOI validity or academic truth: that belongs to the native owner and the broader WP-033 acceptance gate.

## Regression
`tests/MedicalReferenceEvidenceGateTest.php` checks 12 invalid citation fixtures across four strict phases, seven valid native citation formats, draft editing unaffected, and standard non-medical publications unaffected. Exact-HEAD CI, PHPCS, PHPStan, PHP lint and package must pass before source acceptance.

## Remaining major V12.1 WP-033 evidence gaps
Native owner must verify bibliographic source ID, title, author, edition, page/locator, DOI schema and link trust, with source provenance and human review where appropriate. Real staging must demonstrate the adapter schema, display of Urdu/Arabic locators, cross-owner rejection, and publication rollback. This fix alone does not fulfill WP-033.

## Truth
Repository base: `29ecb8f93b24aa16edab042a3c7741c01789810d`.
Deployed Version: unknown.
DB Version: unknown.
Migration State: unknown.
Live Verification Status: not performed.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
