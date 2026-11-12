# File 22 — WP-027 offline-save UI acknowledgement audit (12 November 2026)

## Exact-source baseline
The previously merged source SHA `f5b4f964873af57d64e306d45010966fb3721263` retains File 22 candidate `1.0.0-rc.3`. In `assets/js/workflow-composer.js` the `save()` offline branch unconditionally executed `announce(strings.offlineStored)` after `await persistOfflineRecovery()`, ignoring its boolean result. The latter returns `false` for unsupported IndexedDB/WebCrypto, absent dirty data, quota errors, encryption failures and failed transactions. This is a **source-confirmed false-success UX defect (D22-SRC-03)**, not a verified live incident.

## Root cause
The earlier D22-SRC-01 fix moved IndexedDB success to the transaction commit boundary, but the outer offline `save()` path did not honor `persistOfflineRecovery()`'s outcome. When storage was unsupported or failed, the UI could falsely announce the draft was stored. `persistOfflineRecovery()` already reports success/failure when an offline storage attempt executes, so another unconditional notice was redundant and incorrect.

## Narrow correction
- Capture `storedLocally` and set a truthful “Not saved” status on failure.
- For unsupported storage only, announce `offlineRecoveryUnavailable` from the outer handler; the inner handler already reports attempted failures.
- Remove the redundant outer `offlineStored` announcement. On success the inner routine announces precisely once, only after committed encrypted storage.
- Preserve native server draft ownership, account-scoped AES-GCM, TTL, no plaintext browser storage and existing online saving.

## Regression proof
New executable `tests/run-offline-save-acknowledgement-test.cjs` extracts the exact `save()` helper from the source; five synthetic conditions cover:
1. Browser lacks supported offline storage, no false success.
2. Storage rejects, only unavailable notice and not-saved status.
3. Committed local recovery, exactly one local-success notice and still no assertion of server success.
4. Already-saved clean native draft, no unnecessary persistence.
5. Online native autosave continues returning success with its original notice.

The existing exact-head GitHub CI Node job now runs this new case suite alongside `run-indexeddb-transaction-completion-test.cjs`. These source-level event tests do **not** replace real Safari/Firefox/Chrome storage and multi-device testing.

## Acceptance and truth limits
- Exact PR HEAD must pass PHP lint 8.1–8.3, PHPUnit, PHPCS, PHPStan, Node regressions and deterministic packaging. Re-review diff and safety boundary.
- Stage without IndexedDB, without WebCrypto, with quota rejection, interrupted transaction, two accounts, reload, online recovery and locale/RTL.
- Production: no deploy until source-package checksum parity, deployed version, schema/migration state, real-role staging, backup/rollback and Founder release approval are recorded.

Repository baseline: `f5b4f964873af57d64e306d45010966fb3721263`.
Deployed Version: UNVERIFIED.
DB Version: UNVERIFIED.
Migration State: UNVERIFIED.
Live Verification Status: NOT PERFORMED.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
