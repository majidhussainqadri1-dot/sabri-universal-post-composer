# Sabri Universal Post Composer

File 22 for the Sabri Social Homeopathy Platform.

Sabri Universal Post Composer is a role-aware, adapter-driven creation facade and workflow orchestrator for WordPress. It does not replace native publishing, media, learning, encyclopedia, PDF, marketplace, moderation, consent, identity, or clinical-data owners.

## Governing principles

- One creation gateway, multiple authorized native content systems.
- One canonical native record, multiple projections.
- Sabri Membership Core remains the identity, verification, suspension, and capability authority.
- Native modules retain ownership of permanent content, moderation, secure media, and canonical URLs.
- Unavailable or broken adapters fail independently and do not disable healthy adapters.
- Staging-first development; direct experimental changes on the live website are prohibited.
- Security, privacy, medical safety, accessibility, migration, and rollback are required from the beginning.
- Every substantial implementation is followed by a separate mandatory review before the next phase.

## Ownership boundary

File 22 owns the universal content-type selector, adapter registry, shared creation experience, temporary orchestration boundaries, page resolution, and integration health. Native modules own permanent records, review decisions, secure storage, canonical URLs, durable idempotency reconciliation, and module-specific lifecycle rules.

Authorization-critical adapter metadata—API version, required capability, native owner, minimum native version, and privacy class—is captured atomically at registration. A native adapter cannot weaken its central capability or change its exact owner after acceptance by returning different metadata later in the same request.

## Current development stack

- **Phase 22A:** governance, contracts, central permission enforcement, page routing, Safe Mode, and automated contract tests.
- **Phase 22B:** release-critical File 21 `social_publication` adapter acceptance, ownership diagnostics, and fail-soft gateway rules.
- **Phase 22C:** accessible, responsive Universal Create gateway surface that routes authorized users to native workflows without duplicating content.
- **Phase 22D:** capability-protected administrator health dashboard, privacy-safe adapter diagnostics, and bounded Create-page mapping repair with dry-run support.
- **Phase 22E:** guarded server-side native workflow orchestration for schema, native drafts, validation, preview, idempotent submission, status, and canonical URL retrieval.

Phases 22A–22E were integrated into canonical `main` through merged PR #6. That merge establishes a reviewed source baseline only; it does not constitute staging acceptance, package approval, live deployment, or production completion.

## Technical baseline

- WordPress 6.5 or later
- PHP 8.1–8.3 supported test matrix
- Sabri Membership Core 1.0.1 or later is mandatory
- Production HTTPS required
- American English interface baseline
- No external runtime CDN or remote fonts
- Core Create navigation works without JavaScript

## Public integration

Native modules may register an adapter at any time after File 22 loads:

```php
$result = supc_register_adapter( $adapter );
```

Adapters must implement `Sabri\UniversalComposer\Contracts\Adapter`. Full native draft orchestration additionally implements `Workflow_Adapter`.

Every adapter must declare a nonempty canonical central capability, canonical native-module slug, semantic minimum native version, and one of the controlled privacy classes. Registration rejects malformed metadata before the adapter can reach the Create surface or workflow coordinator. Registration is atomic: if any required metadata method throws, no partial adapter or workflow contract remains registered.

## Create surface

The resolved Create page groups only authorized and available adapters into controlled Publishing, Knowledge and Learning, Media, Commerce, and Other sections.

Every route must be either a relative internal path or an absolute same-origin HTTPS URL. External hosts, HTTP downgrade routes, credentials, mismatched ports, protocol-relative URLs, control characters, and backslashes are rejected.

Unknown privacy classifications are not relabeled. The invalid adapter is omitted, a privacy-safe diagnostic is reported, and healthy adapters remain available.

An eligible account with no registered native adapter is reported as a service-integration outage, not falsely as an account-permission denial.

The user-facing law is:

> One gateway, one native record.

## Native workflow orchestration

Phase 22E adds guarded server-side PHP functions for native modules implementing `Workflow_Adapter`:

- schema discovery;
- create or resume native draft;
- validation;
- same-origin preview;
- idempotent submission;
- native status;
- canonical URL retrieval.

The coordinator rechecks Safe Mode, Membership Core eligibility, central capability, adapter authorization, native availability, payload safety, native-reference format, idempotency-key format, result envelopes, and same-origin HTTPS URLs on every operation. Schema URL fields accept only HTTP(S) URLs without embedded credentials; date and datetime fields must be real calendar and clock values with valid timezone offsets.

Phase 22E does not expose a REST, AJAX, or browser form controller and does not persist File 22-owned draft payloads. Native modules remain responsible for secure storage, durable idempotency, publication, and canonical records.

## Administrator health

Authorized administrators can open `Tools → Composer Health` to view normalized System Check rows and privacy-safe adapter metadata. Static Adapter Health is role-independent; current Create-surface invocation diagnostics are explicitly limited to the signed-in administrator and do not replace the staging role matrix.

Create-page inspection is read-only and memoized per request. It distinguishes `ready`, `repairable`, `ambiguous`, and `missing` states. Multiple shortcode pages require explicit administrator selection rather than silent first-match mapping.

The bounded repair operation verifies option persistence, uses a short-lived mutation lock, performs at most one managed-page insertion attempt, and accepts a new page only after exact page type, slug, publication, shortcode, permalink, and File 22 ownership checks. It never edits or deletes unrelated pages or native-module records.

## Development workflow

Audit → branch → coding → short automated checks → separate post-implementation review → correction → fresh checks → controlled staging → Founder verification → pull-request review → merge.

## Status

Development version `0.1.0-dev`. Source phases are merged on `main`, but no production package, controlled staging acceptance, live deployment, or completion claim has been issued. The post-merge independent audit dated 30 July 2026 corrects atomic registration, immutable authorization/owner metadata, empty-registry state classification, and repository-status documentation.

See the `docs/` directory for architecture, privacy, security, accessibility, migration, rollback, compatibility, error codes, staging acceptance, phase contracts, review records, and the formal File 22/File 23 amendment.
