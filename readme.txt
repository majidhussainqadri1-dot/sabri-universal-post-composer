=== Sabri Universal Post Composer ===
Contributors: majidhussainqadri1-dot
Tags: composer, publishing, workflow, homeopathy, platform
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.3.0
License: Proprietary

Universal, role-aware creation gateway for the Sabri Social Homeopathy Platform.

== Description ==

File 22 unifies authorized content-creation workflows without taking ownership from native modules. Permanent content, durable native drafts, moderation, secure media, patient consent, identity evidence, clinical records, publication history, and canonical URLs remain with their canonical owners.

Version 0.3.0 adds a durable metadata-only submission map, payload-bound idempotency, request-time and scheduled reconciliation, bounded retry/dead-letter handling, partial-failure recovery, authoritative native-status recovery after network loss, and corrected ordinary/sensitive retention over the private schema-driven browser Composer.

File 22 never persists draft bodies, patient consent, identity evidence, or media bytes in its session, submission, or outbox tables. The browser client does not use localStorage, sessionStorage, or IndexedDB for draft content. Private surfaces are authenticated, nonce-protected, owner-scoped, no-store, noindex, bounded, and reauthorized on each operation.

Mandatory platform dependencies for this candidate:

* Sabri Membership Core plugin 1.2.3 or later;
* File 00 database schema 1.2.0 or later;
* File 00 public contract 1.1.2 or later;
* File 20 Create contract compatibility anchor 1.0.1;
* File 21 integrated-staging package identity 1.0.3.2 or later, with stable runtime/API 1.0.3.

== Current Status ==

Coded reconciliation candidate 0.3.0 on Draft PR #23. Exact-head automated QA and deterministic packaging are mandatory release evidence. It is not staging-accepted, live-deployed, operational, or approved as a production release.

== Installation ==

Install only on controlled staging with the exact reviewed package and SHA-256 evidence. Verify File 00, File 20, and File 21 contracts; Tools > Composer Health; real Founder/Administrator/doctor/suspended/visitor workflows; browser and WCAG 2.2 AA acceptance; LiteSpeed/theme/plugin compatibility; backup restoration; and rollback before any production promotion.

== Changelog ==

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
* Raised File 00 minimums to plugin 1.2.3, database 1.2.0, and contract 1.1.2.
* Added File 21 package-identity staging gate 1.0.3.2 while preserving stable runtime/API 1.0.3.
* Completed separate review/correction rounds and exact-head deterministic packaging.

= 0.1.1 =
* Corrected the complete Create authorization chain and independent File 00 version tracks.
* Added privacy-safe aggregate authorization diagnostics and regression coverage.

= 0.1.0 =
* Introduced the adapter registry, File 00 authority boundary, File 20 bridge, native workflow contracts, Create route resolver, private no-cache surface, and System Check foundation.
