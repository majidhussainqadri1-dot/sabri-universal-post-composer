# Source Manifest — Phases 22A–22E

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
- `docs/POST-IMPLEMENTATION-REVIEW-2026-07-29.md`
- `docs/PRIVACY.md`
- `docs/ROLLBACK.md`
- `docs/SECURITY.md`
- `docs/STAGING-ACCEPTANCE.md`
- `docs/SYSTEM-CHECK.md`

## Development-only files

- `.github/`
- `composer.json`
- `phpcs.xml.dist`
- `phpstan.neon.dist`
- `phpunit.xml.dist`
- `tests/`

Development-only files are excluded from a future production ZIP by `.distignore`.

## Release boundary

A release ZIP, SHA-256 checksum, machine-generated exact package manifest, staging acceptance record, and production approval have not been declared. Phases 22A, 22B, 22C, 22D, and 22E remain stacked development Drafts and must pass their merge order, cross-plugin staging, Founder acceptance, backup, and rollback gates before release packaging.
