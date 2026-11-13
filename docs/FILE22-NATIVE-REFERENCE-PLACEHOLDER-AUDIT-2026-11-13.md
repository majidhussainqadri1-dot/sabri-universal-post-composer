# File 22 — 13 November 2026: native-reference placeholder policy bypass (D22-SRC-09)

## Proven source limitation
The previous File22 `Policy_Engine::has_reference()` and `has_successful_case_reference()` only checked `^[A-Za-z0-9][A-Za-z0-9._:-]{0,254}$`. Therefore literal placeholders such as `pending`, `unknown`, `tbd`, `none`, `false`, `0` or `1` satisfied required opaque reference presence for patient consent, successful-case evidence, rights/license provenance and verified seller references. An identifier's syntactic presence must not be mistaken for a completed native proof. This issue is separate from D22-SRC-08 (empty medical reference lists); it affects different reference-bearing fields and control decisions.

## Correction and scope
The shared `valid_opaque_reference()` preserves the previous bounded syntax and rejects a fixed list of obvious nonidentifiers; both single keys and successful-case lists must use it. Standard native `consent:patient:1`, `case:verified:1`, `rights:license:1` and `seller:verified:1` remain accepted as **syntactic identifiers only**.

This **does not** prove real consent, actual DOI, lawful reuse, seller verification or patient anonymization. Native owner must recheck identity, records, expiry, rights, approval, modification and context server-side at publish time; File22 never grants itself those permissions.

## Regression and release
`tests/NativeReferencePlaceholderTest.php` exercises patients, documented cases, marketplace and copyright; good opaque identifiers remain valid; privacy holds still apply in draft. Require exact PR-HEAD full CI, source review and real File00/21/18 adapter staging. Do not mark user consent, rights or live safety resolved from source alone.

Source baseline: `02d818a6c5406cab691cb8dbe8379bd056d389e6`.
Deployed Version: UNVERIFIED.
DB Version: UNVERIFIED.
Migration State: UNVERIFIED.
Live Verification: NOT PERFORMED.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
