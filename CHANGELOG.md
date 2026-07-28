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
- Route validation, adapter metadata escaping, controlled group diagnostics, and fail-closed privacy rejection.
- Built-in privacy-safe Create-surface diagnostics in the System Check report.
- Separate Phase 22C first-review and second-review correction records with focused regression tests.
- Phase 22D `Tools → Composer Health` administrator dashboard with normalized System Check rows and privacy-safe adapter health metadata.
- Capability-protected and nonce-protected Create-page mapping dry run and bounded repair operation.
- Read-only Create-page inspection and explicit repair result codes.
- Role-independent Static Adapter Health separated from current-administrator invocation diagnostics.
- Explicit administrator selection when multiple valid Create-page candidates exist.
- Short-lived atomic Create-page repair lock and post-insert ownership validation.
- PHPUnit contracts for option-write failure, ambiguity, non-page mappings, repair locking, slug mutation, ownership metadata, one-attempt insertion, inspection memoization, and administrator-table accessibility.

### Changed

- Content choices are accessible links to native start routes rather than inactive buttons.
- Adapter registration is available through `supc_register_adapter()` and is not limited to a one-shot hook.
- File 21 version `1.0.3` is the frozen minimum owner for the first release-critical social publication adapter.
- File 21 must implement the diagnostic contract and declare the canonical `sabri_feed_create_posts` capability.
- Wrong owner, wrong capability, wrong group/privacy class, old declared version, and old actual runtime version are release failures rather than warnings.
- A duplicate `social_publication` key is not accepted as successful File 21 registration.
- File 21 fallback removal requires the exact File 20 producer contract and current-user Create visibility; a version string alone is insufficient.
- WordPress readme no longer declares a stable development tag.
- Create group order is controlled independently of cross-group adapter priority.
- Create typography inherits the active Shell or theme instead of forcing a separate font stack.
- Invalid privacy classifications now hide only the invalid adapter and fail System Check instead of being relabeled.
- Permission denial and native integration failure now render distinct states.
- An unavailable native module is no longer reported as a user permission denial.
- Create-page inspection is separated from mutation, memoized per request, and reports `ready`, `repairable`, `ambiguous`, or `missing`.
- Managed-page repair performs one insertion attempt and accepts only an exact validated File 22-owned page.

### Fixed

- Prevented double-prefixing when adapters return a full `dashicons-*` class.
- Added RTL mirroring for the presentational Continue arrow.
- Added keyboard-focus fallback in addition to `:focus-visible`.
- Corrected the isolated PHPStan target after the first Phase 22C run included unrelated Page Resolver WordPress symbols.
- Corrected insufficient contrast for the Sign In action and explicitly controlled its visited state.
- Rejected external allow-listed hosts, HTTP downgrade routes, protocol-relative routes, mismatched ports, URL credentials, control characters, and backslashes.
- Added built-in request-level diagnostics for invalid routes, invalid privacy, unknown groups, and rendering exceptions.
- Prevented false repair success when WordPress does not persist `supc_create_page_id`.
- Prevented published posts or custom post types from being treated as Create pages.
- Prevented silent first-ID selection when multiple shortcode pages exist.
- Prevented repeated orphan insertion attempts after managed-page validation failure.
- Added result-specific administrator notice severity and accessible table captions and column scopes.

### Security

- Suspended, rejected, and expired-document accounts are denied centrally.
- Sensitive Patient Case drafts remain server-side by default.
- File 22 does not own PDF bytes, identity evidence, patient-consent evidence, or private clinical records.
- File 21 fallback remains available during partial rollout, duplicate-key collision, incompatible shell, missing producer hook, Safe Mode, or rollback.
- Adapter routes must be relative internal paths or absolute same-origin HTTPS URLs.
- Invalid privacy metadata fails closed without disabling healthy adapters.
- Administrator health output excludes user data, content data, native routes, raw exception messages, identity evidence, and clinical information.
- Repair controls are restricted to File 22-owned Create-page mapping and cannot edit or delete unrelated pages or native-module records.
- Concurrent File 22 repair requests are serialized by a short-lived atomic lock.
