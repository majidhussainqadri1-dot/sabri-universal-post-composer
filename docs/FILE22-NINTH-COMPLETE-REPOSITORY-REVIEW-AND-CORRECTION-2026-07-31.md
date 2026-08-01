# File 22 Ninth Complete Repository Review and Correction

**Date:** 31 July 2026  
**Scope baseline:** `f39c4c38c5d36f06e2fde7360491fc81db32d5c0`  
**Correction branch:** `fix/file22-ninth-complete-review-2026-07-31`

## Review boundary

This independent cycle re-read the complete cumulative runtime after the eighth correction. It concentrated on cross-plugin symbol ownership, public API provenance, File 20 Safe Mode trust, File 20 health functions, collision behavior, System Check diagnostics, and test-fixture fidelity.

## Defects confirmed and corrected

### 1. Public API markers did not prove function ownership

A coherent foreign component could predeclare the complete `supc_*` function family and matching marker constants. File 22 would correctly avoid redeclaration, but its System Check could still mistake the foreign functions for the owned API.

File 22 now reflects every required public function and requires its source file to resolve exactly to `includes/core/functions.php`. A source mismatch is reported through the existing controlled public API collision code and no foreign function is treated as owned.

### 2. File 20 markers did not prove package or symbol provenance

A coherent foreign runtime could copy the expected File 20 version/owner markers, define the two Create contract functions, and expose a same-named Safe Mode class. Marker equality alone could cause File 22 to invoke foreign code or accept a false normal-mode decision.

A new `Runtime_Trust` boundary now requires:

- canonical `sabri-unified-application-shell/sabri-unified-application-shell.php` package identity;
- coherent real paths, slug, and valid File 20 runtime version;
- Reflection source ownership for both File 20 Create contract functions;
- Reflection source ownership for `Sabri\UnifiedShell\SafeMode`.

An absent File 20 installation remains optional. Any claimed but incomplete, obsolete, colliding, or foreign-source File 20 runtime fails closed.

### 3. File 20 health could invoke a foreign producer

The administrator health row previously invoked `sabri_shell_create_contract_available()` after marker checks only. It now invokes the function only after function completeness and Reflection-backed package ownership both pass.

### 4. Repeated Membership availability reads could produce a contradictory row

The System Check previously called the dynamic Membership Core availability check twice while constructing one row. It now captures one result and uses the same snapshot for both status and codes.

## Regression evidence added

- `includes/core/class-runtime-trust.php`
- canonical File 20 fixtures under `tests/fixtures/sabri-unified-application-shell/`
- `tests/NinthCompleteReviewTest.php`
- strengthened `tests/run-untrusted-shell-safe-mode-test.php`
- updated Safe Mode and File 20 collision contracts
- `.github/workflows/ninth-review-evidence.yml`

## Release boundary

This cycle is an unmerged source-level Draft correction. It does not authorize stacked-PR merge, controlled Files 00/20/21/22 staging acceptance, package promotion, live deployment, or production approval. Role/status/document, IDOR/native ownership, cache/indexing, browser/accessibility/RTL, backup restoration, rollback proof, checksum/manifest, and explicit Founder approval remain mandatory.
