# File 22 — WP-030 / WP-045: uncertain-session cleanup audit (12 November 2026)

## Evidence baseline and source-only scope
- File 22 GitHub `main` baseline: `0dd2ff5473415fa5341f884135a5320440f75c1b`, release candidate `1.0.0-rc.3`, source schema identifier `1.0.0`.
- Audited `Session_Store::cleanup_expired()` and `Submission_Store::enqueue_stale_submissions()`, `mark_uncertain()`, and `cleanup_expired()`.
- Governing File 22 plan: WP-030 (unknown-success/outbox reconciliation), WP-045 (migration/upgrade), WP-056 (provider/storage failure), T-26…T-35 and V12.1 data-preservation boundaries.

## Reproducible source defect: D22-SRC-02
The old cleanup SQL excluded only Composer sessions having an `supc_outbox` event in `queued/retry/processing/dead_letter` status. It did not exclude sessions with an unsettled `supc_submissions` attempt whose outbox insert failed. When such a session expires, cleanup could delete the session identity while a native submit remains uncertain and the submissions table still has `dispatched` or `reconcile` state. This interrupts later reconciliation. Moreover, when the submission/outbox tables were unavailable, the fallback branch deleted all expired sessions without an uncertainty check. These are **two failure modes of one retention-boundary root cause**, not two independent live incidents.

### Exact mechanism
`mark_uncertain()` may persist a submission state even if its `enqueue_reconciliation()` write fails. The periodic stale submission recovery can later try to enqueue again, but sessions may expire in the interim. File 22 should not delete a recoverable session merely because an outbox event is missing.

## Minimal correction
1. Permit expiration cleanup only when both submission and outbox schema tables are verifiably present. If absent, return zero; migration/DB repair is a separate operation.
2. In addition to active outbox events, exclude sessions with submission rows in `prepared`, `dispatched`, `reconcile`, `retryable`, or `dead_letter`. A terminal `resolved` or `failed` attempt alone does not block normal session retention cleanup.
3. Preserve the bounded `LIMIT 500` and indexes: `outbox.session_uuid` and `submissions.session_submission(session_uuid,user_id)`.
4. Add executable isolated `tests/UncertainSessionCleanupTest.php` verifying the actual generated cleanup query, migration-incomplete fail-closed path, and missing-DB safe return. This is a SQL-contract test, **not an actual MySQL execution**.

## Privacy and operations trade-off
Fail-closed cleanup can temporarily retain sensitive *orchestration metadata* during failed schema migration; it does not retain plaintext payloads. Such retention must be operationally visible and repaired promptly. Uncertain/dead-letter attempts must be disposed of only through governed reconciliation/investigation and their pre-existing bounded retention policy, not premature session deletion.

## Required future proof
- CI on the exact PR head: PHP syntax 8.1–8.3, PHPUnit, PHPStan, WordPress PHPCS, cumulative evidence and deterministic packaging.
- Independent review of native submit identity, migration/table status and query indexes.
- WordPress/MySQL staging with: outbox insertion failure, expiring session, delayed cron, re-enqueue after recovery, completed and dead-letter cleanup, repeated migrations; database backup+restore and rollbacks.
- No release/staging/live resolution claim until exact deployed package and DB status are captured.

## Truth ledger
- Repository HEAD baseline: `0dd2ff5473415fa5341f884135a5320440f75c1b`.
- Deployed Version: UNVERIFIED.
- DB Version: UNVERIFIED.
- Migration State: UNVERIFIED.
- Live Verification Status: NOT PERFORMED.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
