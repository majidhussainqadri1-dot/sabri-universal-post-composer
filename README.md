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

## Version 1.0.0-rc.1 plan-complete Core candidate

This branch extends the private browser Composer with durable submission identity, partial-failure recovery, and bounded reconciliation:

- schema-driven accessible forms;
- private REST namespace `sabri-composer/v1`;
- native-owner draft creation and bounded autosave;
- validation, same-origin private preview, idempotent submission, and status retrieval;
- metadata-only `wp_supc_sessions` orchestration storage;
- durable metadata-only `wp_supc_submissions` identity map and `wp_supc_outbox` reconciliation queue;
- order-stable payload fingerprints that bind an idempotency key to one exact payload without storing the payload;
- request-time and scheduled native-status reconciliation after timeout, network loss, or partial local failure;
- five bounded retry attempts with 1 minute, 5 minute, 30 minute, 2 hour, and 12 hour backoff before dead-letter;
- compare-and-swap lock versions and ten-minute per-session operation leases;
- authority revalidation immediately before every native write and final submit dispatch;
- ordinary session retention of 180 days and sensitive-session retention of 30 days;
- adapter-version drift detection;
- no File 22 storage of draft bodies, patient consent, identity evidence, or media bytes;
- no browser `localStorage`, `sessionStorage`, or `IndexedDB` draft persistence;
- no-cache/noindex, nonce, ownership, request-size, and rate-limit boundaries;
- responsive, keyboard-aware, reduced-motion, forced-colors, and RTL-compatible presentation;
- deterministic exact-head packaging with embedded and external SHA-256 manifests.


## Plan-complete Core contract 1.0.0 — R6 source candidate

The merged R5 reconciliation runtime is now extended by a plan-to-code Core layer that closes the remaining source-contract gaps identified against the Definitive Master Plan v3.0 and the harmonized File 22 specification:

- four independent session dimensions: Composer, Review, Publication, and Safety/Hold;
- additive REST 1.2.0 contracts for type discovery, My Content session listing, PATCH autosave, discard, status alias, revisions, and native upload-token orchestration;
- optional native draft-discard, upload-token, and revision interfaces;
- common patient privacy, medical safety, reference, copyright-rights, emergency-content, and verified-marketplace-seller holds;
- metadata-only upload-token and audit ledgers with bounded cleanup;
- versioned taxonomy aliases without taking taxonomy ownership;
- metadata-only projection events for Files 19, 23, 24, 25, Search, and SEO;
- fail-soft diagnostic rows for separately certified Learning, Encyclopedia, Video, Reel, PDF, and Marketplace adapter packs.

This is a **Core source candidate**, not a declaration that native adapter packs, Hostinger staging, production deployment, or operations are accepted. Permanent records and enforcement remain with native owners.

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

File 22 stores only bounded orchestration metadata: session and attempt UUIDs, user ID, adapter identity/version, opaque native reference, workflow/reconciliation state, lock version, payload and response hashes, idempotency key, retry counters, error codes, and timestamps. It does not duplicate permanent content, secure files, patient consent, identity evidence, clinical data, reviewer notes, or native publication history.

Private Create and REST surfaces are authenticated, nonce-protected, no-store, noindex, owner-scoped, bounded, and reauthorized on each operation. If required private response headers can no longer be guaranteed, the browser workflow fails closed.

## Review and QA

Each coding batch follows:

1. implementation review;
2. correction and regression tests;
3. separate fresh/adversarial review;
4. second correction and complete retest;
5. exact-head deterministic package creation.

The current reconciliation candidate is governed by:

`docs/FILE22-0.3.0-RECONCILIATION-REVIEW-R4-2026-08-03.md`

## Truthful status

Version `1.0.0-rc.1` is the plan-complete File 22 Core source candidate. It includes the reconciled 0.3.0 submission/outbox foundation plus the missing Core contracts for type discovery, native draft recovery/discard, four-dimensional workflow state, My Content metadata, policy holds, revision/upload-token orchestration, audit/projection events, migration and rollback. Optional adapter packs are independently certified. It is not yet staging-accepted, live-deployed, or operational.

The detailed earlier corrective history remains in `CHANGELOG.md` and the existing `docs/` review records.
