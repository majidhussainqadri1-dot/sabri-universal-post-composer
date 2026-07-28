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
- Read-only `supc_adapter_matches()` contract for exact native-owner and current-availability confirmation.
- Phase 22C grouped Universal Create surface for Publishing, Knowledge and Learning, Media, Commerce, and controlled custom workflows.
- Responsive Create cards with accessible native links, privacy indicators, explicit user states, visible focus, reduced-motion support, forced-colors support, and RTL-aware direction cues.
- Route validation, adapter metadata escaping, unknown-group and unknown-privacy diagnostics, and conservative privacy fallback.
- Separate Phase 22C post-implementation review record and regression tests for its corrections.

### Changed

- Content choices are accessible links to native start routes rather than inactive buttons.
- Adapter registration is available through `supc_register_adapter()` and is not limited to a one-shot hook.
- File 21 version `1.0.3` is the frozen minimum owner for the first release-critical social publication adapter.
- File 21 must implement the diagnostic contract and declare the canonical `sabri_feed_create_posts` capability.
- Wrong owner, wrong capability, wrong group/privacy class, old declared version, and old actual runtime version are release failures rather than warnings.
- A duplicate `social_publication` key is not accepted as successful File 21 registration.
- File 21 fallback removal requires the exact File 20 producer contract and current-user Create visibility; a version string alone is insufficient.
- WordPress readme no longer declares a stable development tag.
- Create group order is now controlled independently of cross-group adapter priority.
- Create typography inherits the active Shell or theme instead of forcing a separate font stack.
- Unknown privacy classifications display as restricted content and emit a diagnostic event.
- Empty-state language now refers to creation permission rather than only publishing permission.

### Fixed

- Prevented double-prefixing when adapters return a full `dashicons-*` class.
- Added RTL mirroring for the presentational Continue arrow.
- Added keyboard-focus fallback in addition to `:focus-visible`.
- Corrected the isolated PHPStan target after the first Phase 22C run included unrelated Page Resolver WordPress symbols.

### Security

- Suspended, rejected, and expired-document accounts are denied centrally.
- Sensitive Patient Case drafts remain server-side by default.
- File 22 does not own PDF bytes, identity evidence, patient-consent evidence, or private clinical records.
- File 21 fallback remains available during partial rollout, duplicate-key collision, incompatible shell, missing producer hook, Safe Mode, or rollback.
- Unsafe or unauthorized external adapter routes are omitted from the Create surface.
