=== Sabri Universal Post Composer ===
Contributors: majidhussainqadri1-dot
Tags: composer, publishing, workflow, homeopathy, platform
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.4.0
License: Proprietary

Universal, role-aware creation gateway for the Sabri Social Homeopathy Platform.

== Description ==

File 22 unifies authorized content-creation workflows without taking ownership from native modules. Permanent content, durable native drafts, moderation, secure media, Patient Case consent/evidence, identity evidence, clinical records, notification delivery truth, search truth, publication history, and canonical URLs remain with their canonical owners.

Version 0.4.0 reconciles the previous 0.3.0 browser/reconciliation core with the newly rewritten File 22 and central governing plans. It adds governed authoring declarations for rights/license, accessibility, translation, corrections/revisions, scheduling, Patient Case and medical safety, source/evidence, preview, search projection, and notification events. It also adds native-owner lifecycle orchestration for edit, revise, correct, schedule/unschedule, withdraw, archive, and restore.

The public governed PHP helpers are current-subject-only. Protected lifecycle actions revalidate File 00 eligibility/capability and the native owner's current lifecycle decision. Private REST governance/lifecycle endpoints require authentication and a WordPress REST nonce and are request-size limited, rate-limited, bounded, no-store, noindex, and fail closed.

The established File 22 browser Composer continues to provide metadata-only submission identity, payload-bound idempotency, request-time and scheduled reconciliation, bounded retry/dead-letter handling, partial-failure recovery, native-owner drafts/autosave/validation/preview/submit/status, and no persistent browser draft storage.

File 22 does not create a universal post type or duplicate domain database. File 00 is the sole hard runtime authority. File 20 is the production shell/Create integration. File 21 is the native provider for the social-publication adapter only; an unavailable social provider must not disable unrelated certified adapters. Learning, Encyclopedia, Video, Reels, PDF, and Marketplace remain separately certified native adapter domains.

== Contract Versions ==

* Software candidate: 0.4.0
* Database schema: 0.3.0
* Adapter API: 1.0.0
* Workflow API: 1.0.0
* Subject-schema API: 1.0.0
* Governance API: 1.0.0
* Lifecycle API: 1.0.0
* REST compatibility marker: 1.1.0
* Sabri Membership Core plugin: 1.2.3 or later
* File 00 database schema: 1.2.0 or later
* File 00 public contract: 1.1.2 or later

== Current Status ==

Repository/source candidate 0.4.0. Coded and exact-head automated QA/packaging are separate evidence gates from staging and live. This candidate is not, merely by being merged or packaged, Staging-Accepted, Live-Deployed, Operational, or approved as the final 1.0.0 production release.

== Installation ==

Install only on controlled staging with the exact reviewed candidate package and SHA-256 evidence. Verify File 00 authority; File 20 shell/Create integration; the native adapter actually used by each content type; real Founder/doctor/suspended-role workflows; Patient Case/rights/translation/correction/scheduling flows; browser, RTL/LTR, 400% zoom, no-JS and weak-network acceptance; LiteSpeed/theme/plugin compatibility; backup restoration; and rollback before production promotion.

== Changelog ==

= 0.4.0 =
* Added governed workflow declarations for current-plan cross-cutting authoring requirements.
* Added native-owner lifecycle orchestration for edit/revision/correction/scheduling and related commands.
* Bound public governed helpers to the current authenticated subject; blocked arbitrary authorization-subject injection.
* Separated existing-object edit/correction authority from new-create authority.
* Added Patient Case safety to the core social governing gate.
* Added governance consistency checks for notification and search declarations.
* Added private governed REST endpoints with nonce, size, rate, payload and no-cache/noindex controls.
* Added plan-derived adapter coverage diagnostics without guessing native plugin identifiers or creating substitute backends.
* Added exact source-version identity 0.4.0 while retaining database schema 0.3.0.
* Added new-plan regression tests and fresh review/correction records.

= 0.3.0 =
* Added metadata-only `wp_supc_submissions` and `wp_supc_outbox` stores.
* Bound each idempotency key to an order-stable SHA-256 payload fingerprint without storing draft content.
* Added request-time and scheduled reconciliation through authoritative native status.
* Added bounded retry backoff and dead-letter operator visibility.
* Prevented automatic resubmission after ambiguous network/native outcomes.
* Blocked changed-payload replay and submit attempts while reconciliation is pending.
* Added last-point authority rechecks before native draft and submit writes.
* Corrected ordinary inactive-session retention to 180 days and sensitive retention to 30 days.
* Protected unresolved/dead-letter sessions from automatic cleanup.
* Hardened private REST responses against browser, CDN, surrogate, object, database, and LiteSpeed caching.

= 0.2.0 =
* Added private REST namespace `sabri-composer/v1`.
* Added schema-driven accessible browser workflows.
* Added native-owner draft creation, bounded autosave, validation, preview, submit, and status operations.
* Added metadata-only `wp_supc_sessions` storage with compare-and-swap lock versions.
* Persisted idempotency identity before native submission.
* Added per-session operation leases and adapter-version drift rejection.
* Added no-cache/noindex, nonce, ownership, request-size, and rate-limit protections.
* Prohibited browser persistent draft storage.
* Added responsive, reduced-motion, forced-colors, and RTL-compatible workflow presentation.

= 0.1.1 =
* Corrected the complete Create authorization chain and independent File 00 version tracks.
* Added privacy-safe aggregate authorization diagnostics and regression coverage.

= 0.1.0 =
* Introduced the adapter registry, File 00 authority boundary, File 20 bridge, native workflow contracts, Create route resolver, private no-cache surface, and System Check foundation.
