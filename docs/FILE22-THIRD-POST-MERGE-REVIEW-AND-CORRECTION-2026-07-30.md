# File 22 — Third Post-Merge Independent Review and Correction

Date: 30 July 2026  
Reviewed source head: `76363f66a6a1e71ca16e07038cfa7eabd7c699d9`  
Corrective branch: `fix/file22-third-post-merge-review-2026-07-30`

## Review scope

This review independently examined the complete exact head of the second post-merge correction branch, not only its diff. The review covered the adapter registry, immutable registration contracts, current-subject authorization, workflow compatibility, administrator diagnostics, File 21 release readiness, error classification, deterministic ordering, and regression coverage.

## Findings and corrections

### High — workflow compatibility was evaluated before adapter capability

The Registry documentation claimed capability-first behavior, but incompatible workflow metadata was inspected before the immutable capability gate. An account without the adapter capability could therefore be classified as integration-unavailable rather than permission-denied, and could trigger a workflow mismatch diagnostic for an adapter it was not authorized to use.

Correction: both availability and creation-state resolution now apply the immutable central capability gate before workflow compatibility and native runtime checks.

### High — administrator health diagnostics re-read mutable structural metadata

The Create surface and File 21 readiness checks had been migrated to immutable registration snapshots, but the administrator adapter table still called live adapter methods for API version, native owner, minimum version, capability, group, and privacy. A mutable adapter could make System Check disagree with the actual authorization and ownership contract.

Correction: administrator static health now uses the immutable base contract for every structural field. Only operational availability, native health, and workflow schema health remain dynamic. A missing registration contract fails closed.

### Medium — duplicate registration polluted the active adapter diagnostic slot

A duplicate-key attempt wrote `duplicate_key` under the same error key as the already registered adapter. The healthy adapter remained operational, but the diagnostic ledger falsely associated the collision with the active instance and could overwrite its prior runtime diagnostic.

Correction: duplicate attempts use a separate internal collision key and cannot replace the active adapter's error slot. Successful re-registration and unregistration clear stale collision diagnostics.

### Medium — equal-priority ordering depended on mutable labels

After immutable priority capture, equal-priority sorting still called live labels. A label mutation or exception could reorder otherwise stable adapters.

Correction: equal-priority adapters now use the canonical adapter key as the deterministic tie-break.

### Medium — File 21 actual version was not checked against its declared minimum

The release-critical readiness check compared File 21's actual version with the platform floor `1.0.3`, but not with the adapter's immutable declared minimum. An adapter could declare `2.0.0`, report an actual `1.5.0`, and still pass the hard floor.

Correction: actual native version must satisfy both the platform minimum and the adapter's immutable declared minimum.

### Low — controlled diagnostic codes were missing from the administrator allowlist

Several valid registration and File 21 failure codes were reduced to `unrecognized_diagnostic`.

Correction: the exact controlled codes introduced by the cumulative and post-merge reviews are now preserved without opening the allowlist to arbitrary native strings.

## Regression coverage added

`tests/ThirdPostMergeHardeningTest.php` proves:

- capability denial precedes incompatible workflow classification;
- duplicate registration cannot pollute the active adapter error slot;
- equal-priority order is key-stable despite label mutation;
- administrator System Check uses registration snapshots;
- File 21 readiness fails when actual version is below the declared minimum.

## Governance boundary

These corrections are source-level and automated-test candidates only. They do not constitute controlled WordPress staging acceptance, release packaging, production approval, or live deployment. Files 00, 20, 21, and 22 still require the complete role, IDOR, cache/indexing, browser, accessibility, Urdu RTL, backup-restoration, rollback, and Founder-acceptance gates.
