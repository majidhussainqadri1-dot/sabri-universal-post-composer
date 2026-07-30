# Changelog

## 0.1.0-dev — Unreleased

### Cumulative corrective reconciliation

- Reconciled Phases 22B–22E into one Draft PR targeting canonical `main`.
- Protected every page or post that renders `[sabri_universal_composer]` with no-cache and noindex/nofollow/noarchive controls, including noncanonical and ambiguous shortcode pages.
- Added WordPress, object-cache, database-cache, LiteSpeed, and `Vary: Cookie` no-cache signals at the direct render boundary.
- Added complete File 22 public PHP API version, owner, function-ownership, collision, and current-subject contracts.
- Bound the File 20 presentation bridge to the authenticated current user and ignored mismatched caller-supplied subject IDs.
- Required `supc_adapter_matches()` to confirm exact owner plus current-subject availability before File 21 may remove its fallback.
- Corrected native availability/adapter authorization ordering so an offline File 21 integration is not mislabeled as permission denial.
- Required File 21's release-critical adapter to implement Diagnostic Adapter, full Workflow Adapter, native drafts, role-neutral base schema, and subject-aware schema extension.
- Added `SUPC_SUBJECT_SCHEMA_API_VERSION` and interactive `schema_for_user( int $user_id )` support without changing the frozen Workflow Adapter interface.
- Bound payload validation to the authenticated subject's schema, including unknown-field, required-field, type, choice, range, date, datetime, email, URL, checkbox, and opaque-reference enforcement.
- Added File 20 Create contract and File 22 public API rows to System Check with actionable controlled codes.
- Added subject-schema support to Static Adapter and Workflow Contract Health.
- Removed raw exception classes/messages from registry and File 21 requirement diagnostics.
- Replaced arbitrary System Check keys, adapter health codes, and render exception classes with fixed privacy-safe vocabularies.
- Added exact-head checkout verification to every CI job and dependency-lock evidence generation.
- Added isolated public API collision, noncanonical shortcode privacy, File 21 route-only rejection, unavailable-state, schema-bound payload, and subject-schema regression tests.
- Expanded controlled staging acceptance to cover installation/load order, role/status/document matrix, IDOR, cache/indexing, browsers, accessibility, Urdu RTL, backup restoration, and rollback.

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
- Phase 22E guarded server-side `Workflow_Adapter` coordinator for schema, native drafts, validation, preview, idempotent submission, status, and canonical URL operations.
- Public PHP workflow integration functions and a two-UUID idempotency-key generator.
- Payload type, nesting, and 1 MiB encoded-size boundaries before native invocation.
- Calendar-aware date/datetime validation and HTTP(S)-only schema URL validation without embedded credentials.
- Controlled native-reference, workflow-status, preview URL, and canonical URL result validation.
- PHPUnit contracts for authorization, payload safety, idempotency, same-origin URLs, native exceptions, and invalid native statuses.

### Changed

- Content choices are accessible links to native start routes rather than inactive buttons.
- Adapter registration is available through `supc_register_adapter()` and is not limited to a one-shot hook.
- File 21 version `1.0.3` is the frozen minimum owner for the first release-critical social publication adapter.
- File 21 must implement the diagnostic contract and declare the canonical `sabri_feed_create_posts` capability.
- Wrong owner, wrong capability, wrong group/privacy class, old declared version, old actual runtime version, route-only integration, and missing subject schema are release failures.
- Empty capability, malformed native owner, malformed minimum version, and unknown privacy metadata are rejected at registration for every adapter type.
- A duplicate `social_publication` key is not accepted as successful File 21 registration.
- File 21 fallback removal requires the exact File 20 producer contract and current-user Create visibility; a version string alone is insufficient.
- WordPress readme no longer declares a stable development tag.
- Create group order is controlled independently of cross-group adapter priority.
- Create typography inherits the active Shell or theme instead of forcing a separate font stack.
- Invalid privacy classifications hide only the invalid adapter and fail System Check instead of being relabeled.
- Permission denial and native integration failure render distinct states.
- Create-page inspection is separated from mutation, memoized per request, and reports `ready`, `repairable`, `ambiguous`, or `missing`.
- Managed-page repair performs one insertion attempt and accepts only an exact validated File 22-owned page.
- Full workflow operations recheck Safe Mode, Membership Core eligibility, central capability, native availability, and adapter-specific authorization rather than trusting a prior gateway decision.

### Fixed

- Prevented double-prefixing when adapters return a full `dashicons-*` class.
- Added RTL mirroring for the presentational Continue arrow.
- Added keyboard-focus fallback in addition to `:focus-visible`.
- Corrected insufficient contrast for the Sign In action and explicitly controlled its visited state.
- Rejected external allow-listed hosts, HTTP downgrade routes, protocol-relative routes, mismatched ports, URL credentials, control characters, and backslashes.
- Added built-in request-level diagnostics for invalid routes, invalid privacy, unknown groups, and rendering exceptions.
- Prevented false repair success when WordPress does not persist `supc_create_page_id`.
- Prevented published posts or custom post types from being treated as canonical Create pages.
- Prevented silent first-ID selection when multiple shortcode pages exist.
- Prevented repeated orphan insertion attempts after managed-page validation failure.
- Added result-specific administrator notice severity and accessible table captions and column scopes.
- Prevented WordPress Administrator privileges from expanding a pending or otherwise unapproved Membership Core state.
- Prevented foreign or colliding File 20 producer functions from executing during health checks.
- Made external File 20 Safe Mode exceptions fail closed instead of breaking File 22.
- Rejected impossible calendar dates, invalid clock values, invalid timezone offsets, non-HTTP URL schemes, and credential-bearing schema URLs.
- Removed the temporary write-enabled corrective workflow and corrected the source manifest.

### Security

- Suspended, rejected, and expired-document accounts are denied centrally.
- Pending, draft, unknown, and otherwise unapproved Membership Core states are denied even when the account has `manage_options`.
- Sensitive Patient Case drafts remain server-side by default.
- File 22 does not own PDF bytes, identity evidence, patient-consent evidence, or private clinical records.
- File 21 fallback remains available during partial rollout, duplicate-key collision, incompatible shell, missing producer hook, Safe Mode, or rollback.
- Adapter routes must be relative internal paths or absolute same-origin HTTPS URLs.
- Invalid privacy metadata fails closed without disabling healthy adapters.
- Administrator health output excludes user data, content data, native routes, raw exception messages, identity evidence, and clinical information.
- Repair controls are restricted to File 22-owned Create-page mapping and cannot edit or delete unrelated pages or native-module records.
- Concurrent File 22 repair requests are serialized by a short-lived atomic lock.
- Phase 22E exposes no HTTP endpoint and persists no workflow payloads.
- Native modules remain responsible for secure draft storage, protected evidence, durable idempotency reconciliation, moderation, publication, and canonical records.
