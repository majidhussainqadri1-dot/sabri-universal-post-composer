## 0.3.1 — File 23 Composer Bridge

- Publish only the exact File 22 managed Create URL to File 23.
- Register bounded Composer readiness without exposing drafts or direct operations.
- Keep Safe Mode, authority, no-store and canonical ownership boundaries fail closed.
- Add no-guessed-route, privacy, load-order and direct-write-denial gates.

# Changelog

## 0.3.0 — Reconciliation candidate

### Fourth fresh adversarial review corrections

- Added metadata-only `wp_supc_submissions` and `wp_supc_outbox` stores for durable attempt identity and partial-failure reconciliation.
- Bound each idempotency key to an order-stable SHA-256 payload fingerprint without storing draft bodies or sensitive content.
- Added explicit `prepared`, `dispatched`, `reconcile`, `retryable`, `resolved`, and `dead_letter` attempt states.
- Added authoritative request-time and five-minute scheduled native-status reconciliation after timeout, response loss, or local finalization failure.
- Added five bounded retry attempts with 1 minute, 5 minute, 30 minute, 2 hour, and 12 hour backoff before dead-letter.
- Blocked changed-payload replay and a second submit while an earlier native outcome remains uncertain.
- Prevented the browser from automatically resubmitting after ambiguous network/native outcomes; it reconciles the existing attempt instead.
- Rechecked File 00 eligibility, capability, native availability, adapter policy, and Safe Mode immediately before native draft and submit writes.
- Corrected ordinary inactive-session retention to 180 days and sensitive-session retention to 30 days.
- Protected queued, retrying, processing, and dead-letter reconciliation evidence from expired-session cleanup.
- Hardened private REST responses against browser, CDN, surrogate, WordPress object/database, and LiteSpeed caching.
- Added queue, dead-letter, schema, and reconciliation-cron System Check rows and focused regressions.

## 0.2.0 — Core browser Composer candidate

- Added the private `sabri-composer/v1` browser workflow API, schema-driven accessible forms, native-owner drafts, autosave, validation, preview, submit, and status retrieval.
- Added metadata-only orchestration sessions, optimistic lock versions, operation leases, and adapter-version drift rejection.
- Added object-reference binding, resume sequencing, current-authority rechecks, controlled HTTP semantics, no-JavaScript URL validation, and accessibility description associations.
- Added exact-head deterministic packaging and cumulative automated QA.

## 0.1.1 — Authorization forensic correction

- Corrected the complete Create authorization chain and independent File 00 plugin/database/contract version tracks.
- Added privacy-safe aggregate authorization diagnostics and regression coverage.

## 0.1.0-dev — Historical corrective inventory

The following headings are retained verbatim because the corresponding exact-head evidence workflows and documents remain part of the repository's cumulative release record.

### Twelfth complete-review corrections

- Historical twelfth-cycle corrections, warning-free regressions, and exact-head evidence remain preserved in Git history and `docs/`.

### Eleventh full defect census and real-package hardening

- Historical real-package, bounded-contract, transaction, and exact-head evidence remains preserved in Git history and `docs/`.

### Tenth complete-repository hardening

- Historical public-API ownership, Safe Mode, provenance, and exact-head evidence remains preserved in Git history and `docs/`.

### Ninth complete-repository hardening

- Historical Reflection-backed ownership, canonical File 20 provenance, and exact-head evidence remains preserved in Git history and `docs/`.

### Eighth complete-repository hardening

- Historical bounded Semantic Versioning, canonical Membership Core, rollback quarantine, and exact-head evidence remains preserved in Git history and `docs/`.

### Seventh complete-repository hardening

- Required the Membership Core status callback to originate from the declared canonical File 00 runtime before File 22 accepts it as an authorization authority.
- Historical transactional page repair, global-scope isolation, and exact-head evidence remains preserved in Git history and `docs/`.

### Sixth complete-repository hardening

- Historical symbol-collision, no-autoload, Safe Mode, discovery, rollback, and exact-head evidence remains preserved in Git history and `docs/`.

### Fifth complete-repository hardening

- Failed closed before source loading when any File 22 core constant is preclaimed, preventing a foreign runtime from controlling bootstrap identity.
- Historical API-collision, route/privacy, repair-lock, and exact-head evidence remains preserved in Git history and `docs/`.

### Fourth complete-repository hardening

- Historical direct-asset, workflow-health, authorization-order, schema-choice, and exact-head evidence remains preserved in Git history and `docs/`.

### Third post-merge hardening

- Historical immutable authorization, deterministic ordering, native-version, and regression evidence remains preserved in Git history and `docs/`.

### Second post-merge hardening

- Historical immutable registration metadata, Create-surface privacy/group integrity, and regression evidence remains preserved in Git history and `docs/`.

### Cumulative corrective reconciliation

- Historical Phase 22B–22E reconciliation, private cache/indexing, File 20/File 21 contracts, subject-schema, payload validation, and staging matrix remain preserved in Git history and `docs/`.

### Phase 22D administrator diagnostics and repair evidence

- Historical Composer Health, inspection, dry-run, repair, lock, and rollback evidence remains preserved in Git history and `docs/`.

### Phase 22E guarded workflow coordinator evidence

- Historical schema, draft, validation, preview, idempotent submit, status, canonical URL, and payload-boundary evidence remains preserved in Git history and `docs/`.

### Added

- Mandatory Membership Core boundary, versioned adapters, Create route, Safe Mode, File 20 bridge, File 21 contract, System Check, private no-cache surface, and cumulative CI.

### Changed

- Native modules remain canonical owners; File 22 remains the capability-driven creation facade and orchestration layer.

### Fixed

- Historical security, authorization, provenance, validation, accessibility, rollback, and diagnostic defects remain traceable in the dedicated review documents and Git history.

### Security

- Suspended or otherwise unauthorized accounts fail closed; sensitive native data remains outside File 22; private surfaces remain no-store, noindex, nonce-protected, owner-scoped, and reauthorized.
