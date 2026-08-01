# File 22 Fourth Complete Repository Review and Correction — 30 July 2026

reviewed_source_head: `55025808f232834e6c591283a5c84311428c0bc7`
review_branch: `fix/file22-fourth-complete-review-2026-07-30`
status: `source corrections implemented; exact-head CI and controlled staging remain mandatory`

## Review boundary

This review inspected the complete cumulative File 22 repository rather than only the preceding pull-request diff. The inspected boundary included plugin bootstrap and uninstall behavior, base/workflow/diagnostic contracts, Membership Core authorization, registry state, Workflow Coordinator, Create surface rendering, File 20 and File 21 integration, administrator diagnostics, public PHP APIs, tests, GitHub Actions evidence, manifests, release documentation, privacy, accessibility, migration, and rollback boundaries.

## Findings corrected

### 1. High — direct shortcode rendering could omit Create-surface CSS

`render_shortcode()` already repeated the private response-header boundary for template, widget, and programmatic `do_shortcode()` calls, but visual assets were still dependent on an earlier `wp_enqueue_scripts` request detector. A direct render not represented by the global post could therefore receive no-cache/noindex protection while rendering without File 22 styling.

Correction: the actual shortcode render boundary now idempotently enqueues `supc-create-surface` before producing markup. Regression coverage proves both the private-header and visual-asset boundaries on an otherwise undetectable direct render.

### 2. High — Workflow Coordinator disclosed compatibility before central capability denial

The interactive coordinator checked workflow API compatibility before the immutable central capability. An approved account lacking the adapter capability could receive `workflow_api_mismatch` instead of `workflow_permission_denied`, revealing integration state for a workflow it could not use.

Correction: registered capability denial now precedes workflow compatibility, native availability, adapter authorization, schema access, and every native operation.

### 3. High — incompatible workflow health invoked unsupported schema methods

Role-independent contract health recorded a workflow API mismatch but continued into `schema_version()` and `schema()`. An incompatible adapter was therefore able to execute unsupported workflow methods during administrator diagnostics.

Correction: workflow API mismatch now returns a controlled failure immediately and never invokes schema methods.

### 4. High — missing workflow registration metadata was reported as not applicable

A registered object implementing `Workflow_Adapter` but lacking its immutable workflow snapshot was grouped with ordinary non-workflow adapters and returned a passing `not_applicable` result.

Correction: a non-workflow adapter remains not applicable, while a declared Workflow Adapter without registration metadata fails closed with `workflow_registration_metadata_missing`.

### 5. Medium — choice fields could declare no usable choices

`select` and `multiselect` schemas could omit `choices` or provide an empty map. Such a schema was structurally accepted even though no value could satisfy it.

Correction: both choice field types now require one to one hundred canonical choices. Other field types continue to reject a `choices` property.

### 6. Medium — legacy permission helper re-read mutable adapter capability

`Permission_Resolver::can_use_adapter()` re-read `required_capability()` from the live adapter after registration, contradicting the immutable authorization boundary used by the Registry and Workflow Coordinator.

Correction: the helper now requires the caller to supply the registration-time capability and fails closed on adapter authorization exceptions.

### 7. Medium — Phase 22D evidence was not explicitly pinned to the PR head

The Phase 22D evidence workflow used the checkout action without an explicit pull-request head ref or a subsequent exact-SHA assertion. GitHub normally checks out the synthetic merge ref for pull requests, so the evidence job was not proving the same exact source head as the other File 22 jobs.

Correction: the workflow now checks out `github.event.pull_request.head.sha` and verifies `git rev-parse HEAD` before inspecting evidence.

### 8. Medium — repository contract omitted cumulative review assets

The CI repository contract did not require several files added during the first three post-merge reviews and did not require the fourth-review test and record. Their accidental deletion could therefore pass the repository-structure gate.

Correction: the required-file inventory now covers bootstrap/uninstall/readme files, every cumulative post-merge regression suite and review record, and the complete Phase 22E second-review evidence pair.

## Regression coverage

`tests/FourthCompleteReviewTest.php` verifies:

- permission denial precedes workflow compatibility;
- incompatible workflow health does not invoke schema;
- missing immutable workflow metadata fails closed;
- select fields require nonempty declared choices;
- the adapter permission helper uses the registration-time capability.

`tests/PluginPrivacyTest.php` additionally verifies that direct shortcode rendering enqueues File 22 visual assets while preserving all private response controls.

## Files changed in this review

- `includes/core/class-plugin.php`
- `includes/core/class-permission-resolver.php`
- `includes/core/class-workflow-coordinator.php`
- `tests/PluginPrivacyTest.php`
- `tests/FourthCompleteReviewTest.php`
- `.github/workflows/phase22d-review-evidence.yml`
- `.github/workflows/ci.yml`
- `docs/ADAPTER-CONTRACT.md`
- `docs/ERROR-CODES.md`
- `MANIFEST.md`
- `CHANGELOG.md`
- this review record

## Release boundary

These corrections are source-level evidence only. They do not authorize package promotion, staging acceptance, or live deployment. Files 00, 20, 21, and 22 must still pass the controlled account/role/status/document matrix, IDOR and native ownership tests, real LiteSpeed/CDN/indexing tests, supported browser and responsive checks, keyboard and accessibility acceptance, Urdu RTL acceptance, migration and fresh-install verification, backup restoration, rollback proof, and explicit Founder approval.
