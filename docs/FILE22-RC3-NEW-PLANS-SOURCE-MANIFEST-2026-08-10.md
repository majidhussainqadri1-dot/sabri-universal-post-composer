# File 22 RC3 — New Plans Source Manifest

This manifest identifies the runtime and QA files that define the integrated `1.0.0-rc.3` source candidate. Historical manifests remain historical evidence and are not the authority for this RC.

## Runtime identity and bootstrap

- `sabri-universal-post-composer.php`
- `uninstall.php`

## Adapter and lifecycle contracts

- `includes/contracts/interface-adapter.php`
- `includes/contracts/interface-workflow-adapter.php`
- `includes/contracts/interface-governed-workflow-adapter.php`
- `includes/contracts/interface-lifecycle-adapter.php`
- `includes/contracts/interface-diagnostic-adapter.php`
- `includes/contracts/interface-draft-lifecycle-adapter.php`
- `includes/contracts/interface-draft-recovery-adapter.php`
- `includes/contracts/interface-upload-token-adapter.php`
- `includes/contracts/interface-revision-adapter.php`

## Core orchestration and plan runtime

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

## Integration and presentation

- `includes/integration/class-shell-bridge.php`
- `includes/integration/class-core-adapter-requirements.php`
- `includes/integration/class-file23-dashboard-bridge.php`
- `includes/integration/class-file23-dashboard-adapter-runtime.php`
- `includes/presentation/class-create-surface.php`
- `includes/presentation/class-workflow-surface.php`
- `includes/presentation/class-my-content-workspace.php`
- `includes/http/class-rest-controller.php`
- `includes/http/class-reconciliation-rest-controller.php`
- `includes/http/class-plan-rest-controller.php`
- `includes/admin/class-system-check-page.php`
- `includes/admin/class-activation-wizard.php`

## Browser assets

- `assets/css/create-surface.css`
- `assets/css/workflow-composer.css`
- `assets/css/my-content.css`
- `assets/css/governing-plan-brand.css`
- `assets/js/workflow-composer.js`

## New-plan evidence and QA

- `docs/FILE22-RC3-NEW-PLANS-RELEASE-TRUTH-2026-08-10.md`
- `docs/FILE22-RC3-NEW-PLANS-SOURCE-MANIFEST-2026-08-10.md`
- `docs/FILE22-RC3-TWO-FRESH-REVIEWS-AND-FINAL-SOURCE-CLOSURE-2026-08-10.md`
- `docs/FILE22-NEW-GOVERNING-PLANS-CODING-CLOSURE-2026-08-10.md`
- `docs/FILE22-NEW-PLANS-TWO-FRESH-REVIEW-CLOSURE-2026-08-10.md`
- `tests/PlanCompletionCoreTest.php`
- `tests/PlanCompleteExperienceTest.php`
- `tests/GoverningPlanCompletionTest.php`
- `tests/GoverningPlanReleaseIdentityTest.php`
- `tests/NewCentralPlanIntegrationTest.php`
- `tests/workflow-bootstrap.php`
- `.github/workflows/ci.yml`
- `.github/workflows/file22-new-plans-1.0.0-rc.3.yml`

## Production package boundary

Development-only files, tests, GitHub workflows and review documentation are excluded from the installable production package according to `.distignore`. The RC3 workflow must build from the exact checked-out commit, verify package/source identity, produce SHA-256 evidence and explicitly report that staging/live/operational acceptance remains outside repository CI.
