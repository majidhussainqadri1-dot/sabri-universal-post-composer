=== Sabri Universal Post Composer ===
Contributors: majidhussainqadri1-dot
Tags: composer, publishing, workflow, homeopathy, platform
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
License: Proprietary

Universal, role-aware creation gateway for the Sabri Social Homeopathy Platform.

== Description ==

File 22 provides an adapter-driven creation facade and guarded server-side workflow orchestrator. Permanent content, moderation, secure storage, durable idempotency, consent evidence, identity evidence, clinical records, and canonical URLs remain owned by the relevant native platform modules.

Sabri Membership Core 1.2.2 or later, database schema 1.2.0 or later, and public contract 1.1.1 or later are mandatory. Development follows a staging-first, security-first, and rollback-safe workflow.

Version 0.1.1 corrects the complete Create authorization chain. It consumes File 00's explicit institutional state, validates plugin/database/contract versions independently, and adds one privacy-safe current-user diagnostic that evaluates every independent blocker in the same request: Membership state, native capability, File 21 runtime, duplicate copies, persisted settings, Safe Mode/Emergency Disable, adapter registration, native availability, and native Create policy.

Phase 22E provides internal PHP functions for versioned schema discovery, native draft creation or resumption, validation, same-origin preview, idempotent submission, status, and canonical URL retrieval. It does not expose a public REST, AJAX, or browser form endpoint and does not persist File 22-owned workflow payloads.

== Current Status ==

Corrective staging candidate 0.1.1. Production approval has not been declared.

== Installation ==

Install on controlled staging first with File 00 version 1.2.2 and File 21 package version 1.0.3.1. Verify Tools > Composer Health. The `current_user_authorization` row must report every active blocker together and must be PASS before production promotion.

== Changelog ==

= 0.1.1 =
* Consumed the explicit File 00 institutional membership-state contract.
* Separated minimum File 00 plugin, database-schema, and contract versions.
* Preserved disciplinary and erasure hard blocks while allowing Founder/Administrator institutional authority over non-disciplinary legacy application rows.
* Added a privacy-safe `current_user_authorization` forensic row.
* Audited all independent blockers in one request instead of stopping at the first denial.
* Added File 21 runtime, duplicate-copy, capability, settings, Safe Mode, Emergency Disable, adapter-registration, availability, and native-policy diagnostics.

= 0.1.0-dev =
* Added central Membership Core permission enforcement.
* Added safe Create page resolution and noindex/no-cache protection.
* Added versioned base, workflow, and diagnostic adapter contracts.
* Added fail-soft adapter isolation, canonical key validation, deterministic ordering, and request caching.
* Added Safe Mode integration and a File 20 shell contract.
* Added accessible Universal Create gateway and administrator health controls.
* Added guarded native workflow schema, draft, validation, preview, idempotent submission, status, and canonical URL operations.
* Added PHPUnit, PHPStan, WordPress coding standards, and repository-contract checks.
