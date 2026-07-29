# Source Manifest — Cumulative Phases 22A–22E Corrections

## Runtime

- `sabri-universal-post-composer.php`
- `uninstall.php`
- `includes/contracts/interface-adapter.php`
- `includes/contracts/interface-workflow-adapter.php`
- `includes/contracts/interface-diagnostic-adapter.php`
- `includes/core/class-safe-mode.php`
- `includes/core/class-permission-resolver.php`
- `includes/core/class-page-resolver.php`
- `includes/core/class-registry.php`
- `includes/core/class-workflow-coordinator.php`
- `includes/core/class-plugin.php`
- `includes/core/functions.php`
- `includes/integration/class-shell-bridge.php`
- `includes/integration/class-core-adapter-requirements.php`
- `includes/presentation/class-create-surface.php`
- `includes/admin/class-system-check-page.php`
- `assets/css/create-surface.css`

## Public documentation

- `README.md`
- `readme.txt`
- `LICENSE.md`
- `CHANGELOG.md`
- `docs/ARCHITECTURE.md`
- `docs/ADAPTER-CONTRACT.md`
- `docs/ACCESSIBILITY.md`
- `docs/COMPATIBILITY-MATRIX.md`
- `docs/DECISION-LOG.md`
- `docs/ERROR-CODES.md`
- `docs/FILE21-INTEGRATION.md`
- `docs/MASTER-PLAN-AMENDMENT-v2.1.md`
- `docs/MIGRATION.md`
- `docs/PHASE-22C-CREATE-SURFACE.md`
- `docs/PHASE-22C-POST-IMPLEMENTATION-REVIEW-2026-07-29.md`
- `docs/PHASE-22C-SECOND-REVIEW-CORRECTIONS-2026-07-29.md`
- `docs/PHASE-22C-POST-CORRECTION-VERIFICATION-2026-07-29.md`
- `docs/PHASE-22D-ADMIN-SYSTEM-CHECK.md`
- `docs/PHASE-22D-POST-IMPLEMENTATION-REVIEW-2026-07-29.md`
- `docs/PHASE-22D-POST-CORRECTION-VERIFICATION-2026-07-29.md`
- `docs/PHASE-22D-SECOND-REVIEW-CORRECTIONS-2026-07-29.md`
- `docs/PHASE-22D-SECOND-POST-CORRECTION-VERIFICATION-2026-07-29.md`
- `docs/PHASE-22E-WORKFLOW-ORCHESTRATION.md`
- `docs/PHASE-22E-POST-IMPLEMENTATION-REVIEW-2026-07-29.md`
- `docs/PHASE-22E-POST-CORRECTION-VERIFICATION-2026-07-29.md`
- `docs/PHASE-22E-SECOND-REVIEW-CORRECTIONS-2026-07-29.md`
- `docs/PHASE-22E-SECOND-POST-CORRECTION-VERIFICATION-2026-07-29.md`
- `docs/POST-IMPLEMENTATION-REVIEW-2026-07-29.md`
- `docs/PRIVACY.md`
- `docs/ROLLBACK.md`
- `docs/SECURITY.md`
- `docs/STAGING-ACCEPTANCE.md`
- `docs/SYSTEM-CHECK.md`

Historical Phase 22E review records remain immutable evidence for the earlier Phase 22E runtime. They do not approve the cumulative corrective runtime and are explicitly superseded by the forthcoming cumulative independent review and post-correction verification.

## Development and QA files

- `.github/workflows/ci.yml`
- `.github/workflows/corrective-lock-sync.yml`
- `.github/workflows/phase22d-review-evidence.yml`
- `.github/workflows/phase22e-review-evidence.yml`
- `composer.json`
- `composer.lock`
- `phpcs.xml.dist`
- `phpstan.neon.dist`
- `phpunit.xml.dist`
- `tests/bootstrap.php`
- `tests/workflow-bootstrap.php`
- `tests/AccessibilityContrastTest.php`
- `tests/AdminSystemCheckTest.php`
- `tests/CoreAdapterRequirementsTest.php`
- `tests/CreateSurfaceTest.php`
- `tests/PageResolverRepairTest.php`
- `tests/PluginPrivacyTest.php`
- `tests/RegistryTest.php`
- `tests/SubjectSchemaTest.php`
- `tests/WorkflowCoordinatorTest.php`
- `tests/run-public-api-collision-test.php`
- `tests/phpstan-wordpress-stubs.php`

Development files are excluded from a future production ZIP according to `.distignore`. The committed Composer lock freezes development-tool resolution for exact-head QA.

## Release boundary

No release ZIP, production checksum, machine-generated package manifest, staging acceptance record, or production approval has been declared. Draft PR #6 targets canonical `main` and remains unmerged. Files 00/20/21/22 staging, complete role/IDOR/cache/browser/accessibility/RTL acceptance, backup restoration, rollback proof, independent cumulative review, post-correction verification, and explicit Founder authorization remain mandatory.
