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

Sabri Membership Core 1.0.1 or later is mandatory. Development follows a staging-first, security-first, and rollback-safe workflow.

Phase 22E provides internal PHP functions for versioned schema discovery, native draft creation or resumption, validation, same-origin preview, idempotent submission, status, and canonical URL retrieval. It does not expose a public REST, AJAX, or browser form endpoint and does not persist File 22-owned workflow payloads.

== Current Status ==

Development version 0.1.0-dev. No stable production tag or production release has been declared.

== Installation ==

Do not install on the live website. Development builds are for controlled staging only after a documented package, checksum, test result, backup, and rollback plan are available.

== Changelog ==

= 0.1.0-dev =
* Added central Membership Core permission enforcement.
* Added safe Create page resolution and noindex/no-cache protection.
* Added versioned base, workflow, and diagnostic adapter contracts.
* Added fail-soft adapter isolation, canonical key validation, deterministic ordering, and request caching.
* Added Safe Mode integration and a File 20 shell contract.
* Added accessible Universal Create gateway and administrator health controls.
* Added guarded native workflow schema, draft, validation, preview, idempotent submission, status, and canonical URL operations.
* Added PHPUnit, PHPStan, WordPress coding standards, and repository-contract checks.
