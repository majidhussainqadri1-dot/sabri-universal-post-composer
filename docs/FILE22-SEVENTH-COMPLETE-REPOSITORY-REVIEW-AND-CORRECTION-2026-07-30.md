# File 22 Seventh Complete Repository Review and Correction

**Date:** 30 July 2026  
**Scope baseline:** `2f6cf6f57df3905d24eeac06743cb1f03aa8cfd2`  
**Correction branch:** `fix/file22-seventh-complete-review-2026-07-30`

## Review boundary

This independent cycle reviewed the complete cumulative File 22 source, with additional focus on mandatory Membership Core authority, bootstrap/public-API ownership, semantic-version handling, Create-page transactionality, rollback evidence, tests, documentation, and exact-head CI.

## Defects confirmed and corrected

### 1. Mandatory Membership authority lacked runtime provenance

`Permission_Resolver::core_available()` previously accepted only a version constant and a function name. A foreign component could therefore present a lookalike `smc_user_status()` callback and become File 22's authorization authority.

The resolver now requires `SMC_FILE` and `SMC_PATH`, verifies their real paths are coherent, and uses `ReflectionFunction` to confirm that `smc_user_status()` originates from the declared Membership Core runtime directory. Activation uses this same resolver instead of a weaker duplicate check.

### 2. A valid newly created page survived mapping persistence failure

When WordPress successfully inserted a File 22-managed Create page but failed to persist `supc_create_page_id`, the page remained orphaned and consumed an approved slug.

Mapping persistence is now transactional. The prior option value is snapshotted and restored after failure, and only the exact new page created by the failed attempt is permanently removed.

### 3. Null deletion results were recorded as successful rollback

The prior condition treated any result other than `false` as success, although `wp_delete_post()` may return `null`. Rollback now succeeds only when the return value is neither `false` nor `null`; the privacy-safe action records the real result.

### 4. Version parsing and compatibility did not implement full Semantic Versioning

The earlier regular expression accepted malformed identifiers and `version_compare()` treated build metadata as precedence, which could make `1.0.3+build.9` appear lower than `1.0.3`.

A central `Version` utility now validates strict SemVer, supports simultaneous prerelease and build metadata, rejects leading-zero numeric identifiers, and removes build metadata before precedence comparison. Registry metadata, Membership Core minimum checks, and File 21 readiness use this contract.

### 5. Generic preflight variables leaked into WordPress global scope

Bootstrap and public-API ownership arrays were declared at file scope. In WordPress plugin loading, these generic variables entered the global symbol table and could overwrite or be overwritten by unrelated plugins.

Both preflights now run inside static closures. Their functions and constants remain global by contract, while temporary variables remain local.

## Regression evidence added

- `tests/SeventhCompleteReviewTest.php`
- `tests/run-untrusted-membership-core-test.php`
- `tests/run-global-scope-isolation-test.php`
- expanded `tests/PageResolverRepairTest.php`
- `.github/workflows/seventh-review-evidence.yml`

The tests cover strict SemVer, build-metadata-neutral precedence, malformed registered versions, owned Membership fixtures, spoofed Membership provenance, generic global-variable isolation, prior mapping restoration, exact orphan cleanup, and null/false deletion evidence.

## Release boundary

This correction does not authorize merge, staging promotion, release ZIP generation, or production deployment. Files 00/20/21/22 integrated staging, role and account-state matrix, IDOR/native ownership, cache and indexing validation, browser/accessibility/RTL acceptance, backup restoration, rollback proof, package checksum/manifest, and explicit Founder approval remain mandatory.
