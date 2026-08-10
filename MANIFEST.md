# Source Manifest — File 22 1.0.0-rc.3

This is the current repository/package manifest for the two-new-governing-plans source candidate. Historical phase manifests and review records remain evidence only for the exact earlier heads they tested.

## Runtime bootstrap

- `sabri-universal-post-composer.php`
- `uninstall.php`

## Contracts

- `includes/contracts/interface-adapter.php`
- `includes/contracts/interface-workflow-adapter.php`
- `includes/contracts/interface-governed-workflow-adapter.php`
- `includes/contracts/interface-lifecycle-adapter.php`
- `includes/contracts/interface-diagnostic-adapter.php`
- `includes/contracts/interface-draft-lifecycle-adapter.php`
- `includes/contracts/interface-draft-recovery-adapter.php`
- `includes/contracts/interface-upload-token-adapter.php`
- `includes/contracts/interface-revision-adapter.php`

## Core runtime

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

## Integration, HTTP, presentation and administration

- `includes/integration/class-shell-bridge.php`
- `includes/integration/class-core-adapter-requirements.php`
- `includes/integration/class-file23-dashboard-bridge.php`
- `includes/integration/class-file23-dashboard-adapter-runtime.php`
- `includes/http/class-rest-controller.php`
- `includes/http/class-reconciliation-rest-controller.php`
- `includes/http/class-plan-rest-controller.php`
- `includes/presentation/class-create-surface.php`
- `includes/presentation/class-workflow-surface.php`
- `includes/presentation/class-my-content-workspace.php`
- `includes/admin/class-system-check-page.php`
- `includes/admin/class-activation-wizard.php`

## Browser assets

- `assets/css/create-surface.css`
- `assets/css/workflow-composer.css`
- `assets/css/my-content.css`
- `assets/css/governing-plan-brand.css`
- `assets/js/workflow-composer.js`

## Current public documentation

- `README.md`
- `readme.txt`
- `CHANGELOG.md`
- `LICENSE.md`
- `docs/FILE22-RC3-NEW-PLANS-RELEASE-TRUTH-2026-08-10.md`
- `docs/FILE22-RC3-NEW-PLANS-SOURCE-MANIFEST-2026-08-10.md`
- `docs/FILE22-NEW-GOVERNING-PLANS-CODING-CLOSURE-2026-08-10.md`
- `docs/FILE22-NEW-PLANS-TWO-FRESH-REVIEW-CLOSURE-2026-08-10.md`
- `docs/ARCHITECTURE.md`
- `docs/ADAPTER-CONTRACT.md`
- `docs/ACCESSIBILITY.md`
- `docs/COMPATIBILITY-MATRIX.md`
- `docs/ERROR-CODES.md`
- `docs/FILE21-INTEGRATION.md`
- `docs/MIGRATION.md`
- `docs/PRIVACY.md`
- `docs/ROLLBACK.md`
- `docs/SECURITY.md`
- `docs/STAGING-ACCEPTANCE.md`
- `docs/SYSTEM-CHECK.md`

## Current development and QA

- `.github/workflows/ci.yml`
- `.github/workflows/file22-new-plans-1.0.0-rc.3.yml`
- `.github/workflows/file22-plan-complete-core.yml`
- `composer.json`
- `composer.lock`
- `phpcs.xml.dist`
- `phpstan.neon.dist`
- `phpunit.xml.dist`
- `tests/bootstrap.php`
- `tests/workflow-bootstrap.php`
- `tests/PlanCompletionCoreTest.php`
- `tests/PlanCompleteExperienceTest.php`
- `tests/GoverningPlanCompletionTest.php`
- `tests/GoverningPlanReleaseIdentityTest.php`
- `tests/NewCentralPlanIntegrationTest.php`
- `tests/FortyPassReviewTest.php`
- `tests/phpstan-wordpress-stubs.php`

The repository contains additional historical regression suites and review-evidence workflows. They remain cumulative evidence but are not a substitute for current exact-head RC3 QA.

## Production package boundary

Development files are excluded by `.distignore`. The exact-head RC3 workflow must build the installable package, generate an embedded `MANIFEST.sha256`, generate an external archive SHA-256, unpack and verify every included file, and upload the candidate evidence artifact.

## Truthful release boundary

`1.0.0-rc.3` is a repository source candidate. Staging-Accepted, Live-Deployed and Operational remain unclaimed until the corresponding environment evidence exists. The stable production identity `1.0.0` is intentionally not used by this candidate.
