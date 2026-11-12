# File 22 — V12.1 WP-026/WP-042 native mutation locking and stale-delete audit

## Exact-source baseline (12 November 2026)
Repository `main`: `d9c979178ded5e8a89c21b55664a52cc69859729`. The source label remains `1.0.0-rc.3` and schema identifier `1.0.0`; the active WordPress package and DB/migration are unverified.

## D22-SRC-07: session lock is not uniformly held around native mutations
`Rest_Controller::autosave()`, `Reconciliation_Rest_Controller::submit()`, and `Plan_Rest_Controller::patch_session()` all acquire a shared per-session `supc_lock_` option and release in `finally`. Five other File 22 native-mutating paths in Plan REST did **not**:
- `delete_session()` (permanent native draft discard),
- `revision()` (native revision submission),
- `begin_upload()` (native upload initiation),
- `complete_upload()` and `cancel_upload()` (native upload state transitions).

Thus two File22 requests on the same session could overlap the native mutation boundary even though other routes enforced a shared lock. On deletion, `delete_session()` also treated missing `lock_version` as the **current** server version; a stale client could discard newer draft content without proving it had observed that version. These are two closely related manifestations of the same missing serializable session-mutation discipline; this is **source evidence**, not an observed live loss.

## Narrow correction
1. Acquire the exact same session-key lock before *every* listed mutation. If busy, return HTTP 409 `supc_session_busy` before loading/mutating owner state; always release in `finally`.
2. For destructive `delete_session`, require an explicit integer `lock_version` matching the current owned session; never silently substitute current server version.
3. Preserve existing File00 user-eligibility checks, File21 native ownership, adapter contracts, upload tokens, idempotency keys, and actual state updates. No new table, shadow post, patient record, or front-end content cache.

## Test scope and remaining gaps
`tests/PlanRestSessionLockTest.php` verifies all five methods reject a pre-held lock using the actual controller methods, checks `try/finally` release in source, checks explicit stale-delete version before native discard, and verifies the shared lock key matches Reconciliation REST. It is intentionally not a multi-process MySQL/WordPress stress test.

Exact PR HEAD must pass PHPUnit, PHPStan, PHPCS, PHP lint 8.1–8.3 and all cumulative workflows. Real staging must inject two concurrent workers for PATCH/submit/revision/upload/delete, prove same-session exclusion and no lost native draft/media; also test expired/reclaimed locks, slow native provider, missing client version, partial provider success, rollback and audit. The underlying lock expiry/reclaim algorithm is not declared fully certified by this patch and remains a separate acceptance boundary.

## Truth ledger
Repository baseline: `d9c979178ded5e8a89c21b55664a52cc69859729`.
Deployed Version: UNVERIFIED.
DB Version: UNVERIFIED.
Migration State: UNVERIFIED.
Live Verification Status: NOT PERFORMED.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
