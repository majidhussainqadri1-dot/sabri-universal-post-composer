# Source Manifest — File 22 Governing-Plans Candidate 0.4.0

This manifest describes the repository/source candidate after reconciliation with the newly rewritten File 22 plan and consolidated central governing plan. Historical review records remain valid only for their exact historical source heads.

## Candidate identity

- Software candidate: `0.4.0`
- Database schema: `0.3.0`
- Adapter API: `1.0.0`
- Workflow API: `1.0.0`
- Subject-schema API: `1.0.0`
- Governance API: `1.0.0`
- Lifecycle API: `1.0.0`
- REST compatibility marker: `1.1.0`
- Final production `1.0.0`: reserved until complete staging/release Definition of Done

## Runtime

- `sabri-universal-post-composer.php`
- `uninstall.php`
- `includes/contracts/interface-adapter.php`
- `includes/contracts/interface-workflow-adapter.php`
- `includes/contracts/interface-governed-workflow-adapter.php`
- `includes/contracts/interface-lifecycle-adapter.php`
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
- `includes/core/class-governing-plan-runtime.php`
- `includes/core/class-session-store.php`
- `includes/core/class-submission-store.php`
- `includes/core/class-reconciliation-service.php`
- `includes/core/class-browser-runtime.php`
- `includes/core/class-plugin.php`
- `includes/core/functions.php`
- `includes/core/governing-plan-functions.php`
- `includes/http/class-rest-controller.php`
- `includes/http/class-reconciliation-rest-controller.php`
- `includes/integration/class-shell-bridge.php`
- `includes/integration/class-core-adapter-requirements.php`
- `includes/presentation/class-create-surface.php`
- `includes/presentation/class-workflow-surface.php`
- `includes/admin/class-system-check-page.php`
- `assets/css/create-surface.css`
- `assets/css/workflow-composer.css`
- `assets/js/workflow-composer.js`

## Public documentation and release evidence

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
- earlier File 22 phase/review documents retained under `docs/` as exact-head historical evidence

## Development and QA

- `.github/workflows/ci.yml`
- `.github/workflows/file22-governing-plans-0.4.0.yml`
- historical review-evidence workflows under `.github/workflows/`
- `composer.json`
- `composer.lock`
- `phpcs.xml.dist`
- `phpstan.neon.dist`
- `phpunit.xml.dist`
- `tests/bootstrap.php`
- `tests/workflow-bootstrap.php`
- `tests/GoverningPlanCompletionTest.php`
- `tests/GoverningPlanReleaseIdentityTest.php`
- existing unit/integration/regression tests and controlled fixtures under `tests/`

Development files are excluded from the production-style candidate ZIP according to `.distignore`. The committed Composer lock freezes development-tool resolution for exact-head QA.

## Canonical ownership boundary

File 22 is a creation and command-orchestration facade. It does not become the canonical owner of permanent social/news content, Learning, Encyclopedia, Video, Reels, PDFs, Marketplace listings, profile data, notification delivery, search indexes, patient consent/evidence, or domain moderation databases. File 00 is the sole hard runtime authority; File 20 and native domain providers integrate through explicit contracts.

## Release boundary

A merged source candidate, deterministic ZIP, or green automated QA does not prove staging or live deployment. Promotion still requires the exact deployed artifact/checksum, companion/native adapter versions, DB/schema/migration state, real-role and native-domain workflows, browser/accessibility/RTL/no-JS/weak-network acceptance, backup/restore proof, rollback rehearsal, explicit release approval, live smoke testing, and post-deployment monitoring.
