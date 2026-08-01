# File 22 Eighth Complete Repository Review and Correction

**Date:** 31 July 2026  
**Scope baseline:** `3d054b0998e3558f647be3a1665f67ce41823ff7`  
**Correction branch:** `fix/file22-eighth-complete-review-2026-07-31`

## Review boundary

This independent cycle re-audited the complete cumulative File 22 source after the seventh correction. It concentrated on dependency identity, Semantic Versioning precedence, Create-page transaction rollback, malformed prior option state, emergency-disable behavior, test fidelity, documentation, and exact-head evidence.

## Defects confirmed and corrected

### 1. PHP version comparison was not Semantic Versioning comparison

The seventh cycle validated SemVer syntax and removed build metadata, but delegated prerelease ordering to PHP `version_compare()`. PHP's ordering differs from SemVer for numeric versus nonnumeric prerelease identifiers and for sequences such as `alpha.1` versus `alpha.beta`.

File 22 now performs its own bounded SemVer comparison. Core numeric identifiers are compared without integer conversion, numeric prerelease identifiers sort below nonnumeric identifiers, identifiers are compared left to right, a shorter equal prefix has lower precedence, a stable release outranks a prerelease, and build metadata does not affect precedence.

### 2. Coherent foreign Membership packages still passed provenance

A foreign plugin could define internally coherent `SMC_FILE`, `SMC_PATH`, version constants, and `smc_user_status()` inside its own directory. The earlier realpath check would accept that lookalike directory.

The resolver now also requires compatible runtime and database versions plus the canonical `sabri-membership-core/sabri-membership-core.php` package identity. The status callback must originate from that exact package directory.

### 3. Mapping rollback used a collision-prone magic sentinel

A literal prior option value equal to the missing-state sentinel was interpreted as absence and deleted instead of restored. The transaction now uses a request-local object marker and carries a separate existence flag, preserving literal strings, `null`, and other malformed prior values exactly.

### 4. Failed deletion could leave a published shortcode orphan

When permanent deletion returned `null` or `false`, the exact newly inserted record remained published and discoverable. The rollback now attempts a second fail-closed quarantine by converting that exact inserted record to an empty draft. Existing candidate pages are never passed to this cleanup path.

### 5. Total cleanup failure lacked an enforced runtime boundary

If both deletion and quarantine fail while the inserted shortcode remains published, File 22 now sets `supc_emergency_disabled`, emits `supc_created_page_cleanup_failed`, and reports the original controlled repair failure. The unsafe record cannot be treated as an ordinary successful repair.

## Regression evidence added

- `tests/EighthCompleteReviewTest.php`
- canonical File 00 fixture under `tests/fixtures/sabri-membership-core/`
- strengthened coherent-spoof isolation in `tests/run-untrusted-membership-core-test.php`
- expanded `tests/PageResolverRepairTest.php`
- `.github/workflows/eighth-review-evidence.yml`

Coverage includes the official SemVer prerelease chain, numeric/nonnumeric ordering, build metadata neutrality, arbitrary-length numeric core identifiers, whitespace and size rejection, canonical package identity, literal sentinel and `null` mapping restoration, delete-failure quarantine, and emergency disable after total cleanup failure.

## Release boundary

This cycle is a source-level Draft correction only. It does not authorize stacked-PR merge, Files 00/20/21/22 staging acceptance, release packaging, or production deployment. The complete role/status/document matrix, IDOR and native ownership, cache/indexing behavior, browser/accessibility/RTL acceptance, backup restoration, rollback proof, checksum/manifest, and explicit Founder approval remain mandatory.
