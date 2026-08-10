# Sabri Universal Post Composer — File 22

Sabri Universal Post Composer is the role-aware, adapter-driven creation gateway for the Sabri Social Homeopathy Platform. It unifies authorized creation workflows while preserving every native module as the canonical owner of its permanent content, media, consent, moderation, publication state, durable native draft, and canonical URL.

## Governing law

- One creation gateway; multiple authorized native content systems.
- One canonical native record; multiple projections.
- File 00 remains the sole hard runtime authority for membership, verification, suspension, eligibility, and capabilities. File 22 fails closed when that authority is unavailable or incompatible.
- File 20 remains the application-shell and global Create-placement owner. It is a production-expected integration, not a substitute identity or publishing backend.
- File 21 remains the social/news publishing owner. It is the native dependency of the `social_publication` adapter; its absence must not disable unrelated certified adapters.
- Files 05, 06, 10, 11, 12, and 18 remain the native owners for Learning, Encyclopedia, Video, Reels, PDF, and Marketplace workflows. Those domains integrate through separately certified adapters and are hidden when their native provider is unavailable.
- File 19 remains notification projection/delivery owner; File 22 may emit or expose native event declarations but never duplicates delivery truth.
- File 23 remains the private publishing operations dashboard; File 24 the cross-platform security/privacy assurance center; File 25 the public profile/timeline/visual-experience owner.
- Search/discovery remains with its canonical platform owner; File 22 exposes only native indexing policy/projection metadata.
- Missing or incompatible adapters fail independently. File 22 never creates substitute domain backends.

## Version 0.4.0 — new governing-plan coding candidate

Version `0.4.0` is the repository candidate that reconciles the previous 0.3.0 browser/reconciliation core with the newly rewritten File 22 plan and consolidated central governing plan. The database schema remains `0.3.0`; no new universal content database or post type is introduced.

In addition to the 0.3.0 reconciliation core, this candidate adds:

- `Governed_Workflow_Adapter` for explicit rights/license, accessibility-authoring, translation, correction/revision, scheduling, Patient Case/medical safety, source/evidence, preview, search-projection, and notification-event declarations;
- `Lifecycle_Adapter` for native-owner edit, revision, correction, schedule/unschedule, withdrawal, archive, and restore orchestration;
- current-subject-only PHP helpers: `supc_adapter_governance()`, `supc_lifecycle_capabilities()`, and `supc_execute_lifecycle()`;
- current File 00 eligibility/capability revalidation before protected lifecycle commands;
- separation of existing-object edit/correction authority from new-create authority;
- native-reference binding so a lifecycle response cannot silently substitute another object;
- private REST governance/lifecycle endpoints under `sabri-composer/v1` with authentication, REST nonce, request-size limits, per-user rate limiting, bounded payloads, no-cache/noindex responses, and rejection of unexpected top-level command fields;
- System Check gates for the governed social-publication contract and optional-adapter coverage;
- plan-derived adapter catalog keys and canonical owner file numbers without guessed provider slugs;
- explicit prohibition on File 22 duplicate permanent content, media, moderation, search, notification, or domain databases;
- fresh regression coverage for subject spoofing, capability revocation, lifecycle/create separation, Patient Case governance, governance-profile consistency, REST boundaries, and native ownership.

The established browser Composer remains schema-driven and accessible, with native-owner draft creation, bounded autosave, validation, same-origin private preview, payload-bound idempotency, durable submission identity, request-time/scheduled reconciliation, bounded retry/dead-letter handling, no persistent browser draft storage, and metadata-only File 22 orchestration tables.

## Technical baseline and contract versions

- WordPress 6.5 or later
- PHP 8.1–8.3
- File 22 software candidate: `0.4.0`
- File 22 database schema: `0.3.0`
- Adapter API: `1.0.0`
- Workflow API: `1.0.0`
- Subject-schema API: `1.0.0`
- Governance API: `1.0.0`
- Lifecycle API: `1.0.0`
- REST compatibility marker: `1.1.0`
- Sabri Membership Core plugin `1.2.3` or later
- File 00 database schema `1.2.0` or later
- File 00 contract `1.1.2` or later
- Production HTTPS required
- American English interface baseline with Urdu/Arabic/RTL readiness
- No external runtime CDN or remote fonts

## Public PHP integration

Native modules register their adapter after File 22 loads:

```php
$result = supc_register_adapter( $adapter );
```

Base adapters implement `Sabri\UniversalComposer\Contracts\Adapter`. Full native-draft/browser orchestration implements `Workflow_Adapter`. Adapters that certify the current governing-plan authoring contract implement `Governed_Workflow_Adapter`; native owners that expose edit/revision/correction/scheduling commands through File 22 additionally implement `Lifecycle_Adapter`.

File 22 validates contract versions and public-safe declarations, but native modules retain object/state/ownership checks and all domain-specific field, media, consent, review, correction, and publishing rules.

## Data and privacy boundary

File 22 stores only bounded orchestration metadata: session and attempt UUIDs, user ID, adapter identity/version, opaque native reference, workflow/reconciliation state, lock version, payload/response hashes, idempotency key, retry counters, controlled error codes, and timestamps. It does not duplicate permanent content, secure files, Patient Case consent/evidence, identity evidence, clinical data, reviewer notes, notification delivery truth, search indexes, or native publication history.

Patient Case plaintext is not persisted in `localStorage`, `sessionStorage`, or `IndexedDB`. Private Create/REST surfaces are authenticated, nonce-protected, owner-scoped, no-store, noindex, bounded, and reauthorized on protected actions.

## Review and release discipline

Each coding batch is subject to implementation review, correction/retest, a separate fresh/adversarial review, second correction/retest where needed, and exact-head deterministic packaging. The 10 August 2026 governing-plan batch records these reviews in:

- `docs/FILE22-NEW-GOVERNING-PLANS-CODING-CLOSURE-2026-08-10.md`
- `docs/FILE22-NEW-PLANS-TWO-FRESH-REVIEW-CLOSURE-2026-08-10.md`

`0.4.0` is intentionally a pre-staging repository candidate. The plan-reserved `1.0.0` production identity must not be used until the full Definition of Done, staging acceptance, rollback/restore evidence, and release approval are satisfied.

## Truthful status

Current repository status: **Coded / exact-head automated-QA candidate**. It is **not** thereby Staging-Accepted, Live-Deployed, or Operational.

Remaining environment gates include the actual deployed package and checksum, installed native adapter versions, live/staging DB and migration state, real Founder/doctor/suspended-role workflows, native Patient Case/rights/translation/correction/scheduling flows, browser/device/RTL/LTR/400% zoom/no-JS/weak-network acceptance, active theme/cache compatibility, backup restoration, rollback rehearsal, explicit Founder acceptance, approved live smoke testing, and post-deployment monitoring.
