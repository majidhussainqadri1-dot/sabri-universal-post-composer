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
- File 22 Safe Mode and emergency-disable integration.
- PHPUnit, PHPStan, WordPress Coding Standards, and repository-contract CI.
- Formal File 22/File 23 master-plan amendment.

### Changed

- Content choices are accessible links to native start routes rather than inactive buttons.
- Adapter registration is available through `supc_register_adapter()` and is not limited to a one-shot hook.
- WordPress readme no longer declares a stable development tag.

### Security

- Suspended, rejected, and expired-document accounts are denied centrally.
- Sensitive Patient Case drafts remain server-side by default.
- File 22 does not own PDF bytes, identity evidence, patient-consent evidence, or private clinical records.
