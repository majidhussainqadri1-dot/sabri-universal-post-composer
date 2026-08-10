# Source Manifest — File 22 1.0.0-rc.3 Two-New-Plans Candidate

This manifest describes the current repository/source candidate after reconciliation with the newly supplied File 22 plan and consolidated central governing plan. Historical review records remain valid only for the exact historical source heads they tested and are retained below so their evidence workflows remain verifiable.

## Candidate identity

- Software candidate: `1.0.0-rc.3`
- Database schema: `1.0.0`
- Adapter API: `1.0.0`
- Workflow API: `1.0.0`
- Subject-schema API: `1.0.0`
- Governance API: `1.0.0`
- Lifecycle API: `1.0.0`
- REST API: `1.2.0`
- Plan contract: `1.0.0`
- Stable production `1.0.0`: reserved until staging/release/live Definition of Done

## Runtime

- `sabri-universal-post-composer.php`
- `uninstall.php`
- `includes/contracts/interface-adapter.php`
- `includes/contracts/interface-workflow-adapter.php`
- `includes/contracts/interface-governed-workflow-adapter.php`
- `includes/contracts/interface-lifecycle-adapter.php`
- `includes/contracts/interface-diagnostic-adapter.php`
- `includes/contracts/interface-draft-lifecycle-adapter.php`
- `includes/contracts/interface-draft-recovery-adapter.php`
- `includes/contracts/interface-upload-token-adapter.php`
- `includes/contracts/interface-revision-adapter.php`
- `includes/core/class-version.php`
- `includes/core/class-contract-boundary.php`
- `includes/core/class-runtime-trust.php`
- `includes/core/class-safe-mode.php`
- `includes/core/class-migration-manager.php`
- `includes/core/class-permission-resolver.php`
- `includes/core/class-page-resolver.php`
- `includes/core/class-workspace-page-resolver.php`
- `includes/core/class-registry.php`
- `includes/core/class-workflow-validator.php`
- `includes/core/class-workflow-coordinator.php`
- `includes/core/class-session-store.php`
- `includes/core/class-submission-store.php`
- `includes/core/class-reconciliation-service.php`
- `includes/core/class-policy-engine.php`
- `includes/core/class-audit-store.php`
- `includes/core/class-upload-token-store.php`
- `includes/core/class-taxonomy-map.php`
- `includes/core/class-projection-bus.php`
- `includes/core/class-plan-completion-runtime.php`
- `includes/core/class-governing-plan-runtime.php`
- `includes/core/class-browser-runtime.php`
- `includes/core/class-plugin.php`
- `includes/core/functions.php`
- `includes/core/governing-plan-functions.php`
- `includes/http/class-rest-controller.php`
- `includes/http/class-reconciliation-rest-controller.php`
- `includes/http/class-plan-rest-controller.php`
- `includes/integration/class-shell-bridge.php`
- `includes/integration/class-core-adapter-requirements.php`
- `includes/integration/class-file23-dashboard-bridge.php`
- `includes/integration/class-file23-dashboard-adapter-runtime.php`
- `includes/presentation/class-create-surface.php`
- `includes/presentation/class-workflow-surface.php`
- `includes/presentation/class-my-content-workspace.php`
- `includes/admin/class-system-check-page.php`
- `includes/admin/class-activation-wizard.php`
- `assets/css/create-surface.css`
- `assets/css/workflow-composer.css`
- `assets/css/my-content.css`
- `assets/css/governing-plan-brand.css`
- `assets/js/workflow-composer.js`

## Public documentation and current release evidence

- `README.md`
- `readme.txt`
- `LICENSE.md`
- `CHANGELOG.md`
- `MANIFEST.md`
- `docs/ARCHITECTURE.md`
- `docs/ADAPTER-CONTRACT.md`
- `docs/ACCESSIBILITY.md`
- `docs/COMPATIBILITY-MATRIX.md`
- `docs/DECISION-LOG.md`
- `docs/ERROR-CODES.md`
- `docs/FILE21-INTEGRATION.md`
- `docs/MIGRATION.md`
- `docs/PRIVACY.md`
- `docs/ROLLBACK.md`
- `docs/SECURITY.md`
- `docs/STAGING-ACCEPTANCE.md`
- `docs/SYSTEM-CHECK.md`
- `docs/FILE22-NEW-GOVERNING-PLANS-CODING-CLOSURE-2026-08-10.md`
- `docs/FILE22-NEW-PLANS-TWO-FRESH-REVIEW-CLOSURE-2026-08-10.md`
- `docs/FILE22-RC3-NEW-PLANS-RELEASE-TRUTH-2026-08-10.md`
- `docs/FILE22-RC3-NEW-PLANS-SOURCE-MANIFEST-2026-08-10.md`

## Historical exact-head review documents retained

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

## Development and QA

- `.github/workflows/ci.yml`
- `.github/workflows/file22-new-plans-1.0.0-rc.3.yml`
- `.github/workflows/file22-plan-complete-core.yml`
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
- `tests/GoverningPlanCompletionTest.php`
- `tests/GoverningPlanReleaseIdentityTest.php`
- `tests/NewCentralPlanIntegrationTest.php`
- `tests/PlanCompletionCoreTest.php`
- `tests/PlanCompleteExperienceTest.php`
- `tests/FortyPassReviewTest.php`
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
- `tests/fixtures/sabri-membership-core/sabri-membership-core.php`
- `tests/fixtures/sabri-membership-core/includes/functions.php`
- `tests/fixtures/sabri-unified-application-shell/sabri-unified-application-shell.php`
- `tests/fixtures/sabri-unified-application-shell/includes/class-safe-mode.php`
- `tests/fixtures/sabri-unified-application-shell/includes/class-inherited-safe-mode.php`
- `tests/fixtures/sabri-unified-application-shell/includes/functions.php`
- `tests/fixtures/file20-legacy-1.0.0/sabri-unified-application-shell/sabri-unified-application-shell.php`
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

Development files are excluded from the production-style candidate ZIP according to `.distignore`. The committed Composer lock freezes development-tool resolution for exact-head QA. Historical evidence workflows are retained so prior exact-head claims can still be validated; they do not define the current candidate identity.

## Canonical ownership boundary

File 22 is a creation and command-orchestration facade. It does not become the canonical owner of permanent social/news content, Learning, Encyclopedia, Video, Reels, PDFs, Marketplace listings, profile data, notification delivery, File 26 search indexes/ranking, patient consent/evidence or domain moderation databases. File 00 remains the hard identity/capability authority; File 20 and native domain providers integrate through explicit contracts.

## Release boundary

A source candidate, deterministic ZIP or green automated QA does not prove staging or live deployment. Promotion still requires exact deployed artifact/checksum, companion/native adapter versions, DB/schema/migration state, real-role and native-domain workflows, browser/accessibility/RTL/no-JS/weak-network acceptance, backup/restore proof, rollback rehearsal, explicit Founder release approval, live smoke testing and post-deployment monitoring/parity confirmation.
