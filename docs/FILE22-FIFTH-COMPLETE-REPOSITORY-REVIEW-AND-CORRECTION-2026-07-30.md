# File 22 Fifth Complete Repository Review and Correction — 30 July 2026

reviewed_source_head: `9af82c0e1a3e6f37954a5dfbe76fb104af422889`
review_branch: `fix/file22-fifth-complete-review-2026-07-30`
status: `source corrections implemented; exact-head CI and controlled staging remain mandatory`

## Review boundary

This fifth independent cycle reviewed the complete cumulative File 22 source rather than only Draft PR #10. The inspected boundary included the plugin bootstrap, global constants, public PHP API ownership, Create-page resolution, filtered permalink trust, repair locking, direct shortcode privacy, late style delivery, workflow-schema documentation, PHPUnit contracts, isolated process contracts, GitHub Actions evidence, changelog, manifest, and release boundary.

## Findings corrected

### 1. Critical — preclaimed core constants could control bootstrap paths

The plugin defined `SUPC_PATH`, `SUPC_URL`, version, schema, and contract constants without first proving that the names were unclaimed. PHP does not replace an existing constant; therefore a foreign `SUPC_PATH` could survive the failed `define()` call and be consumed by subsequent `require_once` statements.

Correction: File 22 now inspects every core constant before defining any of them. Any collision prevents all runtime loading, registers a generic administrator error, and schedules File 22 deactivation. Foreign path, URL, version, and contract values are never consumed.

### 2. High — the public API collision marker was not itself protected

`SUPC_PUBLIC_API_COLLISIONS` was written by the success branch but omitted from the preclaim check. A foreign component could predefine that marker and cause a warning or inconsistent ownership state.

Correction: all four public API markers and all public functions now participate in one atomic collision set. A preclaimed marker prevents every `supc_*` function from being declared. System Check also requires the collision marker to exist, be a string, and be empty before reporting PASS.

### 3. High — filtered external Create-page permalinks were trusted

A published page containing the shortcode was considered valid when `get_permalink()` returned any nonempty string. WordPress permalink filters could therefore supply an external, downgraded, credential-bearing, protocol-relative, or mismatched-port URL that File 20 or a login redirect could expose.

Correction: the resolver validates the permalink as an internal relative route or absolute same-origin HTTPS URL with matching port and no credentials, control characters, or backslashes. Invalid filtered URLs are excluded from configured and discovered candidates and `Page_Resolver::url()` returns an empty string.

### 4. High — malformed repair locks could deadlock Create-page repair indefinitely

Only an array with an old positive timestamp was treated as stale. A scalar value, missing timestamp, invalid token, or far-future timestamp could remain forever and force every repair request to return `repair_locked`.

Correction: an active repair lock must be an array containing a current UUID-v4 token and a timestamp inside the bounded lock window. Malformed, expired, invalid-token, and implausibly future-dated locks are removed before a new atomic lock is attempted. A valid current lock still serializes concurrent repairs.

### 5. High — late direct shortcodes could evaluate private state after headers were already sent

The fourth review enqueued CSS and called the header method at render time, but the method returned no success state. When output had already begun, File 22 continued into account, role, and adapter evaluation even though no-cache and noindex headers could no longer be established.

Correction: private header application is now a boolean gate. A direct shortcode invoked after output begins receives only a generic, data-free notice. File 22 does not inspect login state, Membership Core status, adapter availability, drafts, or publication state in that context.

### 6. Medium — secure late renders could remain unstyled after the head queue closed

Calling `wp_enqueue_style()` after `wp_head` does not by itself prove that the stylesheet will be emitted in the response.

Correction: after the private boundary succeeds, File 22 enqueues its stylesheet and, when the normal head queue has already run and the handle is not done, prints that single registered style handle once.

### 7. Medium — schema documentation contradicted the implemented normalization boundary

Runtime intentionally strips unknown top-level native result metadata while rejecting unknown properties inside executable field definitions. Documentation stated that every unknown top-level property was rejected.

Correction: the contract now distinguishes the two boundaries accurately. The normalized public schema contains only `version` and `fields`; extra native top-level metadata is discarded and never trusted, while unknown field-contract properties remain a hard failure.

## Regression evidence added

- `tests/FifthCompleteReviewTest.php`
  - external filtered permalink rejection;
  - same-origin HTTPS permalink acceptance;
  - malformed/future repair-lock recovery;
  - valid active lock serialization.
- `tests/run-bootstrap-constant-collision-test.php`
  - proves runtime classes are not loaded through a preclaimed core path.
- `tests/run-public-api-marker-collision-test.php`
  - proves a marker-only collision blocks the complete public function family.
- `tests/run-late-shortcode-privacy-test.php`
  - proves an after-output shortcode returns only the generic privacy notice.
- `.github/workflows/fifth-review-evidence.yml`
  - exact-head checkout, cumulative PHPUnit execution, all three isolated contracts, and inventory/documentation checks.

## Files changed in this cycle

- `sabri-universal-post-composer.php`
- `includes/core/functions.php`
- `includes/core/class-plugin.php`
- `includes/core/class-page-resolver.php`
- `tests/PluginPrivacyTest.php`
- `tests/FifthCompleteReviewTest.php`
- `tests/run-bootstrap-constant-collision-test.php`
- `tests/run-public-api-marker-collision-test.php`
- `tests/run-late-shortcode-privacy-test.php`
- `.github/workflows/fifth-review-evidence.yml`
- `docs/ADAPTER-CONTRACT.md`
- `docs/PRIVACY.md`
- `CHANGELOG.md`
- `MANIFEST.md`
- this review record.

## Release boundary

This review does not declare production completion. Exact-head automated checks must pass on the final branch head. Draft PR stacking must remain correct. Controlled Files 00/20/21/22 staging must still prove role/status/document behavior, IDOR resistance, cache and indexing behavior, browser/device support, keyboard and screen-reader accessibility, Urdu RTL, backup restoration, rollback, package manifest/checksum, and explicit Founder approval before any production deployment.
