# Changelog

## 0.4.0 — New governing-plan coding completion candidate

### Governing-plan reconciliation

- Reconciled File 22 repository source with the newly rewritten File 22 plan and consolidated central governing plan while preserving File 22 as a creation/orchestration facade rather than a duplicate domain backend.
- Added `Governed_Workflow_Adapter` for explicit rights/license, accessibility-authoring, translation, correction/revision, scheduling, Patient Case/medical safety, source/evidence, preview, search-projection, and notification-event declarations.
- Added `Lifecycle_Adapter` for native-owner edit, revise, correct, schedule/unschedule, withdraw, archive, and restore commands.
- Added the governed runtime and current-subject-only PHP helpers for governance inspection and lifecycle execution.
- Added private governance/lifecycle REST endpoints with authentication, WordPress REST nonce, request-size limits, per-user rate limiting, bounded command payloads, strict top-level fields, no-cache/noindex responses, and fail-closed errors.
- Revalidated current File 00 eligibility and capability before protected lifecycle operations.
- Separated existing-object edit/correction authority from new-create authority and bound lifecycle results to the originally requested native reference.
- Added System Check evidence for the governed social-publication contract and optional adapter coverage.
- Added a plan-derived adapter catalog keyed by canonical File 22 adapter keys and owner file numbers; no substitute backend or guessed provider slug is created.
- Added regression coverage for Patient Case governance, current-subject binding, authorization revocation, create/edit separation, REST/payload boundaries, governance-profile consistency, and no duplicate File 22 permanent content storage.

### Fresh review/correction rounds

- Corrected an initial confused-deputy risk by removing caller-supplied user IDs from public governed helpers and rejecting internal subject mismatch.
- Preserved the existing REST compatibility marker `1.1.0` after a premature marker bump was detected by exact reconciliation QA; new governance/lifecycle APIs are independently versioned at `1.0.0`.
- Corrected lifecycle authorization so existing-object edit/correction does not require the separate new-create capability.
- Added the omitted Patient Case safety requirement to the core social governance gate.
- Added fail-closed notification/search governance consistency checks.
- Removed unverified guessed native-module identifiers from the optional adapter catalog.
- Added REST abuse controls and expanded adversarial regression coverage.
- Performed a second corrected-source adversarial review and exact-head retest.

### Release-integrity closure

- Assigned a unique software identity `0.4.0` because the governing-plan source materially differs from historical `0.3.0` artifacts; reusing `0.3.0` for different source trees would break package/checksum traceability.
- Kept database schema at `0.3.0` because this batch adds contracts/runtime orchestration but no new schema migration.
- Reconciled README, WordPress readme, manifest, test evidence, and deterministic package workflow with the 0.4.0 source identity.
- The plan-reserved `1.0.0` production identity remains intentionally unused until full staging Definition of Done, rollback/restore evidence, and release approval are satisfied.

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

The following headings are retained because the corresponding exact-head evidence workflows and documents remain part of the repository's cumulative release record.

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
