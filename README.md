# Sabri Universal Post Composer

File 22 for the Sabri Social Homeopathy Platform.

This repository contains the role-aware, adapter-driven creation facade and workflow orchestrator for WordPress. It does not replace native publishing, media, learning, encyclopedia, PDF, marketplace, moderation, or clinical data owners.

## Governing principles

- One creation gateway, multiple authorized native content systems.
- One canonical native record, multiple projections.
- Sabri Membership Core remains the identity, verification, and capability authority.
- Native modules retain ownership of permanent content and secure media.
- Staging-first development; direct experimental changes on the live website are prohibited.
- Security, privacy, medical safety, accessibility, migration, and rollback are required from the beginning.

## Ownership boundary

File 22 owns the universal content-type selector, adapter registry, shared creation experience, orchestration state, and integration health. Native modules own permanent records, review decisions, secure storage, canonical URLs, and module-specific lifecycle rules.

See:

- `docs/ARCHITECTURE.md`
- `docs/ADAPTER-CONTRACT.md`
- `docs/DECISION-LOG.md`
- `docs/SECURITY.md`

## Current phase

Phase 22A — Governance and adapter contracts.

Current development branch:

`phase-22a-governance-contracts`

## Technical baseline

- WordPress 6.5 or later
- PHP 8.1–8.3 test matrix
- Production HTTPS required
- American English interface baseline
- No external runtime CDN or remote fonts

## Development workflow

Audit → branch → coding → short automated checks → controlled staging → Founder verification → pull-request review → merge.

Long continuous QA loops are not part of this phase. Tracked-file changes invalidate earlier check results and require fresh short confirmation checks.

## Status

Development version `0.1.0-dev`. No production package, staging acceptance, live deployment, or completion claim has been issued.
