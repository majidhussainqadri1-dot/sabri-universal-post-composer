# Changelog

## 0.3.0 — Reconciliation candidate

### Fresh adversarial review R4

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

## 0.1.0 — Foundation

- Introduced the adapter registry, File 00 authority boundary, File 20 bridge, native workflow contracts, Create route resolver, private no-cache surface, and System Check foundation.

Detailed historical review and correction records remain preserved in the `docs/` directory and in Git history.
