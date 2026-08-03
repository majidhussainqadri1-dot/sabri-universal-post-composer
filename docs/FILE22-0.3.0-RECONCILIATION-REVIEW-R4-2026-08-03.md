# File 22 — Reconciliation Review R4 and Release Evidence

Date: 03 August 2026  
Branch: `feat/file22-core-composer-0.2.0-2026-08-02`  
Pull request: Draft PR #23  
Target runtime: `0.3.0`  
Status: corrective source candidate; exact-head CI and package evidence govern promotion. Not staging-accepted, live-deployed, or operational.

## Governing boundary

File 22 remains a universal creation facade and orchestration layer. Native modules remain the canonical owners of draft bodies, permanent records, media bytes, consent evidence, moderation, publication state, revisions, and canonical URLs. File 22 stores only bounded privacy-safe orchestration metadata.

The File 22 specification requires a submission map, idempotency identity, outbox, retry/dead-letter processing, authoritative status reconciliation, background jobs, locks, and partial-failure recovery. A timeout or client-network failure must never be guessed as either success or failure and must never trigger an automatic duplicate submit.

## Fresh adversarial review R4 — defects found and corrected

1. **Idempotency payload mutation:** an existing idempotency identity was not durably bound to the exact payload. A canonical order-stable SHA-256 payload fingerprint is now persisted and changed-payload replay fails closed.
2. **Missing durable submission map:** the session record alone could not prove whether native dispatch had been prepared, dispatched, acknowledged, or left ambiguous. A metadata-only `wp_supc_submissions` table now records the durable attempt identity and state.
3. **Missing reconciliation outbox:** partial local/native failure had no durable queue. A metadata-only `wp_supc_outbox` table now records reconciliation work without storing draft bodies or sensitive evidence.
4. **Ambiguous native success:** timeout, response loss, or local finalize failure could not be authoritatively resolved. Request-time and background reconciliation now query the native owner by the exact bound reference.
5. **Unsafe repeat submit while uncertain:** a user or client could retry while the earlier dispatch was still ambiguous. Sessions in `submitting` or `reconcile` state now reject another submit until reconciliation resolves the prior attempt.
6. **No bounded retry/dead-letter law:** retries now use five attempts with approximately 1 minute, 5 minutes, 30 minutes, 2 hours, and 12 hours backoff; exhausted work becomes dead-letter and emits a privacy-safe operator event.
7. **Expired cleanup could erase unresolved evidence:** automatic session cleanup now preserves sessions referenced by queued, retrying, processing, or dead-letter outbox work.
8. **Retention mismatch:** ordinary inactive orchestration sessions now use the governed 180-day baseline and sensitive sessions use 30 days. Completed session metadata remains bounded separately.
9. **Insufficient last-point authorization:** File 00 eligibility, capability, native availability, adapter policy, and Safe Mode are rechecked immediately before native draft writes and final submit dispatch.
10. **Private REST cache ambiguity:** private endpoints now assert browser, CDN, surrogate, WordPress object/database, and LiteSpeed no-cache boundaries in addition to nonce, ownership, rate, size, and noindex controls.
11. **Browser network-loss behavior:** the browser never automatically resubmits an uncertain operation. It schedules authoritative reconciliation and tells the user that the result is pending or safely retryable.
12. **Native mapping exposure on failed status:** session recovery no longer exposes the opaque native mapping when the native owner cannot reauthorize/status-check that object.

## New runtime components

- `Submission_Store` — durable submission identity, payload fingerprint, response hash, queue and dead-letter metadata.
- `Reconciliation_Service` — request-time and scheduled native-status reconciliation.
- REST `POST /sabri-composer/v1/sessions/{uuid}/reconcile`.
- Five-minute reconciliation cron plus request-time recovery.
- System Check rows for submission/outbox schema, queue/dead-letter state, and reconciliation cron.

## Data-minimization statement

The new tables do not store draft bodies, patient narratives, consent evidence, identity documents, reviewer notes, or media bytes. They store opaque native references, public-safe adapter/session/attempt identifiers, hashes, machine states, retry counters, controlled error codes, and timestamps.

## Acceptance evidence required for this exact source head

The authoritative exact-head workflow must pass:

- cumulative PHPUnit and isolated contract suites;
- PHPStan;
- WordPress Coding Standards;
- PHP 8.1, 8.2, and 8.3 syntax;
- JavaScript syntax;
- repository and dependency-lock contracts;
- deterministic ZIP creation;
- embedded and external SHA-256 verification;
- ZIP CRC/integrity verification.

## Mandatory external gates still pending

- exact File 21 package identity `1.0.3.2` with stable runtime/API `1.0.3`;
- File 20 Create contract `1.0.1` integrated staging;
- real WordPress/MySQL migration from session schema 1.0.0 to 1.1.0 and new submission/outbox schema;
- real cron delay, duplicate worker, object-cache, LiteSpeed, database failure, disk-full, and backup/restore tests;
- Founder, Administrator, trusted/verified/pending/suspended doctor and visitor workflows;
- browser, mobile, RTL, keyboard, screen-reader, forced-colors, 400% zoom and WCAG 2.2 AA acceptance;
- rollback and orphan native-draft recovery rehearsal;
- Founder staging acceptance, controlled production smoke test, and monitoring.

## Truthful release decision

R4 closes the known repository-level partial-failure, idempotency, outbox, reconciliation, retention, last-point authority, and private-cache defects described above. This is not proof of staging acceptance, live deployment, operational readiness, or absolute defect-free status. Any CI, integration, staging, or production finding reopens the review-and-correction cycle.
