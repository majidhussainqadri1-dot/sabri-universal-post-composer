# Source Manifest — Cumulative Phases 22A–22E and Complete Review Corrections

## Runtime

- `sabri-universal-post-composer.php`
- `uninstall.php`
- `includes/contracts/interface-adapter.php`
- `includes/contracts/interface-workflow-adapter.php`
- `includes/contracts/interface-diagnostic-adapter.php`
- `includes/core/class-version.php`
- `includes/core/class-contract-boundary.php`
- `includes/core/class-runtime-trust.php`
- `includes/core/class-safe-mode.php`
- `includes/core/class-permission-resolver.php`
- `includes/core/class-page-resolver.php`
- `includes/core/class-registry.php`
- `includes/core/class-workflow-validator.php`
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
- `docs/FILE22-CUMULATIVE-POST-CORRECTION-VERIFICATION-2026-07-30.md`
- `docs/FILE22-POST-MERGE-INDEPENDENT-REVIEW-AND-CORRECTION-2026-07-30.md`
- `docs/FILE22-SECOND-POST-MERGE-REVIEW-AND-CORRECTION-2026-07-30.md`
- `docs/FILE22-THIRD-POST-MERGE-REVIEW-AND-CORRECTION-2026-07-30.md`
- `docs/FILE22-FOURTH-COMPLETE-REPOSITORY-REVIEW-AND-CORRECTION-2026-07-30.md`
- `docs/FILE22-FIFTH-COMPLETE-REPOSITORY-REVIEW-AND-CORRECTION-2026-07-30.md`
- `docs/FILE22-SIXTH-COMPLETE-REPOSITORY-REVIEW-AND-CORRECTION-2026-07-30.md`
- `docs/FILE22-SEVENTH-COMPLETE-REPOSITORY-REVIEW-AND-CORRECTION-2026-07-30.md`
- `docs/FILE22-EIGHTH-COMPLETE-REPOSITORY-REVIEW-AND-CORRECTION-2026-07-31.md`
- `docs/FILE22-NINTH-COMPLETE-REPOSITORY-REVIEW-AND-CORRECTION-2026-07-31.md`
- `docs/FILE22-TENTH-COMPLETE-REPOSITORY-REVIEW-AND-CORRECTION-2026-07-31.md`
- `docs/FILE22-ELEVENTH-FULL-DEFECT-CENSUS-AND-CORRECTION-2026-07-31.md`
- `docs/FILE22-TWELFTH-COMPLETE-REVIEW-AND-CORRECTION-2026-08-01.md`
- `docs/MASTER-PLAN-AMENDMENT-v2.1.md`
- `docs/MIGRATION.md`
- `docs/PHASE-22C-CREATE-SURFACE.md`
- `docs/PHASE-22C-POST-IMPLEMENTATION-REVIEW-2026-07-29.md`
- `docs/PHASE-22C-SECOND-REVIEW-CORRECTIONS-2026-07-29.md`
- `docs/PHASE-22C-POST-CORRECTION-VERIFICATION-2026-07-29.md`
- `docs/PHASE-22D-ADMIN-SYSTEM-CHECK.md`
- `docs/PHASE-22D-POST-CORRECTION-VERIFICATION-2026-07-29.md`
- `docs/PHASE-22D-POST-IMPLEMENTATION-REVIEW-2026-07-29.md`
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

Historical phase review records remain evidence for their exact earlier source heads. The cumulative verification and eleven independent review cycles through 31 July 2026 supersede outdated status wording; they do not replace controlled staging or production acceptance.

## Development and QA files

- `.github/workflows/ci.yml`
- `.github/workflows/cumulative-review-evidence.yml`
- `.github/workflows/fifth-review-evidence.yml`
- `.github/workflows/sixth-review-evidence.yml`
- `.github/workflows/seventh-review-evidence.yml`
- `.github/workflows/eighth-review-evidence.yml`
- `.github/workflows/ninth-review-evidence.yml`
- `.github/workflows/tenth-review-evidence.yml`
- `.github/workflows/eleventh-review-evidence.yml`
- `.github/workflows/twelfth-review-evidence.yml`
- `.github/workflows/phase22d-review-evidence.yml`
- `.github/workflows/phase22e-review-evidence.yml`
- `composer.json`
- `composer.lock`
- `phpcs.xml.dist`
- `phpstan.neon.dist`
- `phpunit.xml.dist`
- `tests/bootstrap.php`
- `tests/workflow-bootstrap.php`
- `tests/fixtures/sabri-membership-core/sabri-membership-core.php`
- `tests/fixtures/sabri-membership-core/includes/functions.php`
- `tests/fixtures/sabri-unified-application-shell/sabri-unified-application-shell.php`
- `tests/fixtures/sabri-unified-application-shell/includes/class-safe-mode.php`
- `tests/fixtures/sabri-unified-application-shell/includes/class-inherited-safe-mode.php`
- `tests/fixtures/sabri-unified-application-shell/includes/functions.php`
- `tests/fixtures/file20-legacy-1.0.0/sabri-unified-application-shell/sabri-unified-application-shell.php`
- `tests/AccessibilityContrastTest.php`
- `tests/AdminSystemCheckTest.php`
- `tests/CoreAdapterRequirementsTest.php`
- `tests/CreateSurfaceTest.php`
- `tests/EighthCompleteReviewTest.php`
- `tests/EleventhCompleteReviewTest.php`
- `tests/EleventhWorkflowHardeningTest.php`
- `tests/FifthCompleteReviewTest.php`
- `tests/FounderAdminCreateSurfaceRegressionTest.php`
- `tests/FourthCompleteReviewTest.php`
- `tests/NinthCompleteReviewTest.php`
- `tests/PageResolverRepairTest.php`
- `tests/PluginPrivacyTest.php`
- `tests/PublicApiSubjectBindingTest.php`
- `tests/RegistryTest.php`
- `tests/SafeModeTest.php`
- `tests/SecondPostMergeHardeningTest.php`
- `tests/SeventhCompleteReviewTest.php`
- `tests/ShellBridgeTest.php`
- `tests/SixthCompleteReviewTest.php`
- `tests/TenthCompleteReviewTest.php`
- `tests/TwelfthCompleteReviewTest.php`
- `tests/ThirdPostMergeHardeningTest.php`
- `tests/SubjectSchemaTest.php`
- `tests/WorkflowCoordinatorTest.php`
- `tests/run-bootstrap-constant-collision-test.php`
- `tests/run-bootstrap-symbol-collision-test.php`
- `tests/run-file20-contract-collision-test.php`
- `tests/run-file20-legacy-1-0-0-compatibility-test.php`
- `tests/run-file20-partial-create-contract-test.php`
- `tests/run-global-scope-isolation-test.php`
- `tests/run-invalid-idempotency-generator-test.php`
- `tests/run-late-shortcode-privacy-test.php`
- `tests/run-managed-page-rollback-test.php`
- `tests/run-page-discovery-query-test.php`
- `tests/run-page-transaction-hardening-test.php`
- `tests/run-public-api-collision-test.php`
- `tests/run-public-api-marker-collision-test.php`
- `tests/run-shell-safe-mode-no-autoload-test.php`
- `tests/run-untrusted-membership-core-test.php`
- `tests/run-untrusted-shell-safe-mode-test.php`
- `tests/phpstan-wordpress-stubs.php`

Development files are excluded from a future production ZIP according to `.distignore`. The committed Composer lock freezes development-tool resolution for exact-head QA.

## Release boundary

PR #6 has been merged into canonical `main`; subsequent cumulative review branches remain unmerged source corrections. No release ZIP, production checksum, machine-generated package manifest, controlled Files 00/20/21/22 staging acceptance, live deployment, or production approval has been declared. Complete role/IDOR/cache/browser/accessibility/RTL acceptance, actual File 20 visibility-contract upgrade, backup restoration, rollback proof, and explicit Founder authorization remain mandatory.
