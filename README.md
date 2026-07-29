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

File 22 owns the universal content-type selector, adapter registry, shared creation experience, orchestration state, page resolution, and integration health. Native modules own permanent records, review decisions, secure storage, canonical URLs, and module-specific lifecycle rules.

## Current development stack

- **Phase 22A:** governance, contracts, central permission enforcement, page routing, Safe Mode, and automated contract tests.
- **Phase 22B:** release-critical File 21 `social_publication` adapter acceptance, ownership diagnostics, and fail-soft gateway rules.
- **Phase 22C:** accessible, responsive Universal Create gateway surface that routes authorized users to native workflows without duplicating content.
- **Phase 22D:** capability-protected administrator health dashboard, privacy-safe adapter diagnostics, and bounded Create-page mapping repair with dry-run support.

All phases remain stacked Draft pull requests. No merge, staging approval, package approval, or production approval is implied.

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

## Create surface

The resolved Create page groups only authorized and available adapters into controlled Publishing, Knowledge and Learning, Media, Commerce, and Other sections.

Every route must be either a relative internal path or an absolute same-origin HTTPS URL. External hosts, HTTP downgrade routes, credentials, mismatched ports, protocol-relative URLs, control characters, and backslashes are rejected.

Unknown privacy classifications are not relabeled. The invalid adapter is omitted, a privacy-safe diagnostic is reported, and healthy adapters remain available.

The user-facing law is:

> One gateway, one native record.

## Administrator health

Authorized administrators can open `Tools → Composer Health` to view normalized System Check rows and privacy-safe adapter metadata. Static Adapter Health is role-independent; current Create-surface invocation diagnostics are explicitly limited to the signed-in administrator and do not replace the staging role matrix.

Create-page inspection is read-only and memoized per request. It distinguishes `ready`, `repairable`, `ambiguous`, and `missing` states. Multiple shortcode pages require explicit administrator selection rather than silent first-match mapping.

The bounded repair operation verifies option persistence, uses a short-lived mutation lock, performs at most one managed-page insertion attempt, and accepts a new page only after exact page type, slug, publication, shortcode, permalink, and File 22 ownership checks. It never edits or deletes unrelated pages or native-module records.

## Development workflow

Audit → branch → coding → short automated checks → separate post-implementation review → correction → fresh checks → controlled staging → Founder verification → pull-request review → merge.

## Status

Development version `0.1.0-dev`. No production package, staging acceptance, live deployment, or completion claim has been issued.

See the `docs/` directory for architecture, privacy, security, accessibility, migration, rollback, compatibility, error codes, staging acceptance, phase contracts, review records, and the formal File 22/File 23 amendment.
