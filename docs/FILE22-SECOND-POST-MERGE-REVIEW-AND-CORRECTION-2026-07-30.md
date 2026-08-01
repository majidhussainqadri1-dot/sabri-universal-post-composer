# File 22 Second Post-Merge Review and Correction — 30 July 2026

## Scope

This independent second review examined the exact head of the first post-merge correction branch rather than relying on its earlier green checks. The review focused on whether the newly introduced immutable adapter contract was used consistently across every consumer.

Reviewed areas:

- registration-time metadata snapshots;
- Create-surface privacy and grouping;
- deterministic ordering;
- File 21 release-readiness diagnostics;
- incompatible Workflow Adapter visibility;
- stale adapter diagnostics after corrected re-registration;
- regression coverage and release boundaries.

## Findings and corrections

### F22-SPM-01 — Privacy and group metadata remained mutable on the Create surface

**Severity:** High

The registry captured privacy classification and native ownership, but the Create surface continued to call `privacy_classification()` and `group()` on the live adapter object. A mutable or defective adapter could register as `sensitive` and later present itself as `public`, or move itself into another group after acceptance.

**Correction:** Privacy classification and group are now read exclusively from the immutable registration contract. A missing contract fails the affected adapter independently with a controlled diagnostic.

### F22-SPM-02 — Ordering and minimum-version metadata were not immutable

**Severity:** Medium

Adapter ordering still re-read `priority()`, and release diagnostics re-read `minimum_native_version()`. A mutable adapter could reorder itself or alter its declared compatibility after registration.

**Correction:** Group, priority, minimum native version, privacy class, capability, and native owner are captured atomically. Registry ordering uses the captured priority. Labels remain dynamic for localization, but keys and structural ordering do not.

### F22-SPM-03 — File 21 readiness diagnostics trusted mutable security metadata

**Severity:** High

The release-critical File 21 System Check re-read native owner, capability, group, privacy class, and minimum version from the live adapter object. This contradicted the immutable authorization model and allowed post-registration metadata changes to influence release-readiness results.

**Correction:** File 21 readiness checks now use the registry's immutable base and workflow contracts. Only operational health, actual native version, and current availability remain dynamic.

### F22-SPM-04 — Incompatible Workflow Adapters could appear invokable

**Severity:** High

A Workflow Adapter declaring an unsupported workflow API could remain visible through the general Create surface because compatibility was enforced only when a workflow operation reached the coordinator.

**Correction:** The registry now treats a missing or incompatible workflow contract as unavailable before Create-surface exposure. The adapter remains registered for controlled diagnostics but cannot be invoked.

### F22-SPM-05 — Corrected re-registration retained stale error state

**Severity:** Medium

After a failed registration, a later corrected registration using the same canonical key could succeed while the old error remained in the request-level registry diagnostics.

**Correction:** Successful registration clears the stale error for that key. Unregistration also removes its associated runtime diagnostic and flushes availability/state caches.

## Regression coverage

A dedicated second-post-merge regression suite now proves that:

- privacy, group, priority, and minimum version cannot be rewritten after registration;
- Create cards retain the registered sensitive classification and group;
- ordering retains the registered priority;
- incompatible workflow API adapters are unavailable rather than invokable;
- successful corrected re-registration clears stale errors;
- File 21 readiness uses the registered owner, capability, group, privacy, and minimum-version snapshot.

## Files corrected

- `includes/core/class-registry.php`
- `includes/presentation/class-create-surface.php`
- `includes/integration/class-core-adapter-requirements.php`
- `tests/SecondPostMergeHardeningTest.php`
- `README.md`
- `CHANGELOG.md`
- `MANIFEST.md`

## Acceptance boundary

This work is a source-level correction on a separate review branch. It does not authorize merge, packaging, staging acceptance, live deployment, or production completion. Exact-head automated checks, Files 00/20/21/22 controlled staging, real role and IDOR testing, cache/indexing verification, browser/accessibility/RTL acceptance, backup restoration, rollback proof, and explicit Founder approval remain mandatory.
