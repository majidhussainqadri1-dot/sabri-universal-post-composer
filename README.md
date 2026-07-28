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

## Ownership boundary

File 22 owns the universal content-type selector, adapter registry, shared creation experience, orchestration state, page resolution, and integration health. Native modules own permanent records, review decisions, secure storage, canonical URLs, and module-specific lifecycle rules.

## Current phase

Phase 22A — Governance, contracts, permission enforcement, page routing, safe-mode integration, and automated contract tests.

## Technical baseline

- WordPress 6.5 or later
- PHP 8.1–8.3 supported test matrix
- Sabri Membership Core 1.0.1 or later is mandatory
- Production HTTPS required
- American English interface baseline
- No external runtime CDN or remote fonts

## Public integration

Native modules may register an adapter at any time after File 22 loads:

```php
$result = supc_register_adapter( $adapter );
```

Adapters must implement `Sabri\UniversalComposer\Contracts\Adapter`. Full native draft orchestration additionally implements `Workflow_Adapter`.

## Development workflow

Audit → branch → coding → short automated checks → controlled staging → Founder verification → pull-request review → merge.

## Status

Development version `0.1.0-dev`. No production package, staging acceptance, live deployment, or completion claim has been issued.

See the `docs/` directory for architecture, privacy, security, accessibility, migration, rollback, compatibility, error codes, staging acceptance, and the formal File 22/File 23 amendment.
