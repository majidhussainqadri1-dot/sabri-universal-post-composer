# File 22 — 13 November 2026: native-reference placeholder policy bypass (D22-SRC-09)

## Proven source limitation
The previous File22 `Policy_Engine::has_reference()` and `has_successful_case_reference()` only checked `^[A-Za-z0-9][A-Za-z0-9._:-]{0,254}$`. Therefore literal placeholders such as `pending`, `unknown`, `tbd`, `none`, `false`, `0` or `1` satisfied required opaque reference presence for patient consent, successful-case evidence, rights/license provenance and verified seller references. An identifier's syntactic presence must not be mistaken for a completed native proof. This issue is separate from D22-SRC-08 (empty medical reference lists); it affects different reference-bearing fields and control decisions.

## Correction and scope
The shared `valid_opaque_reference()` preserves the previous bounded syntax and rejects a fixed list of obvious nonidentifiers; both single keys and successful-case lists must use it. Standard native `consent:patient:1`, `case:verified:1`, `rights:license:1` and `seller:verified:1` remain accepted as **syntactic identifiers only**.

This **does not** prove real consent, actual DOI, lawful reuse, seller verification or patient anonymization. Native owner must recheck identity, records, expiry, rights, approval, modification and context server-side at publish time; File22 never grants itself those permissions.

## Additional D22-SRC-10 discovered by exact-head CI
The initial exact PR SHA `5caf733807ee1f05956d5e745dd6bffab1fa3500` failed PHPUnit (one failed assertion, repeated across multiple workflows) on a valid `rights:license:1` source reference. Root cause: `contains_unsafe_link_protocol()` treated **every** colon-prefixed string in fields containing `source` as an external URL; meanwhile `has_reference()` correctly treated `source_reference` as a native opaque ID. Both constraints cannot hold simultaneously.

The correction distinguishes URI-bearing keys (`url/link/embed`) from opaque source/reference keys. It retains rejection of dangerous `javascript:/vbscript:/data:/file:/blob:/ftp:/mailto:/about:/intent:` schemes and rejects non-HTTP(S) URLs containing `://`. A source ID such as `rights:license:1` may pass the *syntactic* File22 gate, while the actual native owner must verify existence, rights and context. Regression now includes four dangerous URI cases. This is **one additional root cause**, not ten separate CI defects.

## Regression and release
`tests/NativeReferencePlaceholderTest.php` exercises patients, documented cases, marketplace and copyright; good opaque identifiers remain valid; privacy holds still apply in draft. Require exact PR-HEAD full CI, source review and real File00/21/18 adapter staging. Do not mark user consent, rights or live safety resolved from source alone.

Source baseline: `02d818a6c5406cab691cb8dbe8379bd056d389e6`.
Deployed Version: UNVERIFIED.
DB Version: UNVERIFIED.
Migration State: UNVERIFIED.
Live Verification: NOT PERFORMED.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
