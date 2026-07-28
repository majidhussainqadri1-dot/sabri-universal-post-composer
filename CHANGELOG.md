# Changelog

## 0.1.0-dev — Unreleased

### Added

- Mandatory Sabri Membership Core activation and permission boundary.
- Versioned adapter API with base, workflow, and diagnostic contracts.
- Canonical adapter-key validation and collision diagnostics.
- Per-adapter exception isolation and per-request availability caching.
- Deterministic adapter ordering.
- Safe Create page creation and resolution without overwriting existing pages.
- Private-surface noindex, noarchive, and no-cache controls.
- File 20 shell bridge with final capability-driven Create visibility.
- File 21 `social_publication` release contract, System Check readiness report, compatibility matrix, and integration tests.
- File 22 Safe Mode and emergency-disable integration.
- PHPUnit, PHPStan, WordPress Coding Standards, and repository-contract CI.
- Formal File 22/File 23 master-plan amendment.

### Changed

- Content choices are accessible links to native start routes rather than inactive buttons.
- Adapter registration is available through `supc_register_adapter()` and is not limited to a one-shot hook.
- File 21 version `1.0.3` is the frozen minimum owner for the first release-critical social publication adapter.
- A missing File 21 adapter is reported as a Core 1.0 release failure without causing a public-site fatal.
- WordPress readme no longer declares a stable development tag.

### Security

- Suspended, rejected, and expired-document accounts are denied centrally.
- Sensitive Patient Case drafts remain server-side by default.
- File 22 does not own PDF bytes, identity evidence, patient-consent evidence, or private clinical records.
