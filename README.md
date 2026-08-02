# Sabri Universal Post Composer — File 22

Sabri Universal Post Composer is the role-aware, adapter-driven creation gateway for the Sabri Social Homeopathy Platform. It unifies authorized creation workflows while preserving every native module as the canonical owner of its content, media, consent, moderation, publication state, durable draft, and canonical URL.

## Governing law

- One creation gateway; multiple authorized native content systems.
- One canonical native record; multiple projections.
- File 00 remains the membership, verification, suspension, and capability authority.
- File 20 remains the global shell and Create-control owner; Create contract compatibility anchor: `1.0.1`.
- File 21 remains the social/news publishing owner. Integrated staging requires package identity `1.0.3.2` or later while the stable runtime/API remains `1.0.3`.
- File 23 remains the private publishing operations dashboard.
- File 24 remains the cross-platform security/privacy assurance center without replacing native security.
- File 25 remains the public profile, timeline, and visual-experience owner.
- Missing or incompatible adapters fail independently; File 22 does not create substitute backends.

## Version 0.2.0 candidate

This branch adds the first private browser Composer over the reviewed server-side workflow coordinator:

- schema-driven accessible forms;
- private REST namespace `sabri-composer/v1`;
- native-owner draft creation and bounded autosave;
- validation, same-origin private preview, idempotent submission, and status retrieval;
- metadata-only `wp_supc_sessions` orchestration storage;
- compare-and-swap lock versions and per-session operation leases;
- idempotency identity persisted before native submission;
- adapter-version drift detection;
- no File 22 storage of draft bodies, patient consent, identity evidence, or media bytes;
- no browser `localStorage`, `sessionStorage`, or `IndexedDB` draft persistence;
- no-cache/noindex, nonce, ownership, request-size, and rate-limit boundaries;
- responsive, keyboard-aware, reduced-motion, forced-colors, and RTL-compatible presentation;
- deterministic exact-head packaging with embedded and external SHA-256 manifests.

## Technical baseline

- WordPress 6.5 or later
- PHP 8.1–8.3
- Sabri Membership Core plugin `1.2.3` or later
- File 00 database schema `1.2.0` or later
- File 00 contract `1.1.2` or later
- Production HTTPS required
- American English interface baseline with Urdu/Arabic/RTL readiness
- No external runtime CDN or remote fonts

## Public PHP integration

Native modules register an adapter after File 22 loads:

```php
$result = supc_register_adapter( $adapter );
```

Base adapters implement:

```php
Sabri\UniversalComposer\Contracts\Adapter
```

Full browser/native-draft orchestration additionally implements:

```php
Sabri\UniversalComposer\Contracts\Workflow_Adapter
```

Every adapter declares immutable versioned authority, native ownership, capability, privacy, group, priority, schema, availability, draft, validation, preview, submit, status, and canonical-URL contracts. Unknown, malformed, colliding, incompatible, or unauthorized adapters fail closed and remain out of the invokable Create surface.

## Data and privacy boundary

File 22 stores only bounded orchestration metadata: session UUID, user ID, adapter identity/version, opaque native reference, workflow state, lock version, idempotency key, error code, and timestamps. It does not duplicate permanent content, secure files, patient consent, identity evidence, clinical data, reviewer notes, or native publication history.

Private Create and REST surfaces are authenticated, nonce-protected, no-store, noindex, owner-scoped, bounded, and reauthorized on each operation. If required private response headers can no longer be guaranteed, the browser workflow fails closed.

## Review and QA

Each coding batch follows:

1. implementation review;
2. correction and regression tests;
3. separate fresh/adversarial review;
4. second correction and complete retest;
5. exact-head deterministic package creation.

The 0.2.0 candidate has passed PHPUnit, PHPStan, WordPress Coding Standards, PHP 8.1/8.2/8.3 syntax, repository contracts, JavaScript syntax, embedded manifest verification, external SHA-256 verification, and ZIP integrity at its reviewed source head. See:

`docs/FILE22-0.2.0-CORE-COMPOSER-REVIEW-AND-RELEASE-EVIDENCE-2026-08-02.md`

## Truthful status

Version `0.2.0` is a coded, packaged, automated-QA-green candidate on Draft PR #23. It is not yet staging-accepted, live-deployed, or operational. Required remaining gates include exact File 21 `1.0.3.2` integration, Hostinger staging, real-role workflows, browser/device and WCAG 2.2 AA acceptance, active theme and LiteSpeed/cache tests, backup restoration, rollback rehearsal, Founder acceptance, approved live smoke testing, and post-deployment monitoring.

The detailed pre-0.2.0 corrective history remains in `CHANGELOG.md` and the existing `docs/` review records.
