# File 22 Sixth Complete Repository Review and Correction — 30 July 2026

reviewed_source_head: `f713779b9cbfdba7b1d9d97f71c4c3cbe19ea76d`
review_branch: `fix/file22-sixth-complete-review-2026-07-30`
status: `source corrections implemented; exact-head CI and controlled staging remain mandatory`

## Review boundary

This sixth independent review inspected the complete cumulative File 22 repository rather than only Draft PR #11's preceding diff. The reviewed boundary included bootstrap ownership, class and interface collisions, Membership Core authorization, File 20 Safe Mode trust, registry availability decisions, workflow metadata, Create-page discovery and repair, managed-page ownership, public PHP integration, tests, documentation, and GitHub evidence.

## Findings corrected

### 1. Critical — bootstrap checked constants but not preclaimed runtime symbols

File 22 checked its core constants before loading source files, but did not check whether another component had already declared a File 22 class or interface. A preclaimed namespaced symbol could therefore produce a fatal redeclaration instead of a controlled fail-closed state.

Correction: bootstrap now checks every File 22-owned interface and runtime class with autoload disabled before defining constants or requiring source files. A constant or symbol collision leaves the plugin inert, schedules deactivation, and emits only a generic administrator notice.

### 2. High — registry cached authorization and availability decisions

`available_for_user()` and `creation_state_for_user()` could return a same-request cached allow/state decision before rechecking Membership Core status, Safe Mode, WordPress capability, native availability, or adapter policy. A suspension, capability revocation, emergency disable, or native outage occurring later in the same request could therefore be ignored.

Correction: security and operational decisions are now re-evaluated on every call. `flush_cache()` remains only as a compatibility method and no longer controls authorization state.

### 3. High — File 20 Safe Mode callback lacked ownership verification

A callable `Sabri\UnifiedShell\SafeMode::disabled()` method was trusted without proving the exact File 20 contract version, owner, and function-ownership markers. A foreign, obsolete, or colliding class could therefore influence the platform-wide emergency boundary.

Correction: the callback is trusted only when the exact File 20 `1.0.1` contract, canonical owner, and ownership marker agree. A callable but unowned shell class fails closed.

### 4. Medium — Create-page discovery hydrated every published page ID

When the mapping was absent or damaged, discovery queried every published page ID and filtered all pages in PHP. Large sites could incur unnecessary memory and execution cost during inspection.

Correction: discovery now asks WordPress for likely shortcode-bearing pages using the exact shortcode token and sentence search while still revalidating every returned candidate, returning IDs only, and preserving ambiguity handling.

### 5. Medium — invalid newly inserted managed pages were left behind

If WordPress mutated the requested slug, stripped ownership metadata, changed the object type, or otherwise caused post-insert validation to fail, the newly created invalid object remained in the database and could consume approved slugs across retries.

Correction: File 22 now permanently removes only the exact object created by that failed repair attempt, emits a privacy-safe rollback action, and never deletes an unrelated pre-existing page.

### 6. Medium — malformed workflow API metadata entered the immutable registry

Workflow API metadata was trimmed and captured but was not required to be a bounded semantic version. Malformed values could enter the immutable contract and be treated as an ordinary compatibility mismatch.

Correction: malformed Workflow API metadata is rejected atomically during registration. A well-formed but unsupported version remains registered for controlled compatibility diagnostics.

## Regression coverage

- `tests/SixthCompleteReviewTest.php` proves same-request status, Safe Mode, and capability changes invalidate prior allow decisions without an explicit flush.
- The same test proves malformed Workflow API metadata cannot leave an adapter or partial immutable contract.
- `tests/run-bootstrap-symbol-collision-test.php` proves a preclaimed File 22 class leaves bootstrap inert without loading runtime files.
- `tests/run-untrusted-shell-safe-mode-test.php` proves an unowned shell class cannot clear the emergency boundary.
- `tests/run-page-discovery-query-test.php` proves discovery is narrowed to the shortcode token and IDs-only query.
- `tests/run-managed-page-rollback-test.php` proves a newly inserted invalid File 22 page is deleted and rollback evidence is emitted.
- `.github/workflows/sixth-review-evidence.yml` pins all sixth-cycle evidence to the exact pull-request head.

## Release boundary

This correction is source-level only. It does not merge the stacked Draft chain, create a production ZIP, authorize staging acceptance, or permit live deployment. Files 00, 20, 21, and 22 must still pass the controlled role/status/document matrix, real IDOR and native-record ownership tests, cache/indexing validation, supported browsers, mobile and tablet layouts, keyboard and screen-reader acceptance, Urdu RTL, backup restoration, rollback proof, package checksum and manifest, and explicit Founder approval.
