# File 22 — Offline Recovery IndexedDB Transaction Durability Audit (2026-11-12)

## Source truth and scope
- Reviewed baseline GitHub main: `46f716c261ca0da5ca77b317ecf4b0d04329b41f`.
- Target: `assets/js/workflow-composer.js` function `recoveryStore()`; Master Plan V12.1, File22 WP-027 / CV-051 / T-29…T-31 offline recovery acceptance.
- **This is a source-code defect and a source-only corrective proposal.** Exact deployed code, active WordPress plugin, schema/migration state and live behaviour are unverified. No live incident is declared resolved.

## Root cause and affected boundary
The original request branch fulfilled the Promise in `requestResult.addEventListener('success')`. IndexedDB request completion can precede transaction commit. If a later operation/commit causes the transaction to abort, the already fulfilled Promise cannot be reversed; `persistOfflineRecovery()` may acknowledge an encrypted local recovery that was not durably committed.

This matters especially for weak-network recovery. The IndexedDB key and encrypted draft must not be presented as successfully saved until the containing transaction itself completes.

## Narrow source correction
- Capture request result/error without resolving or rejecting prematurely.
- Resolve only on `transaction.complete` and only if there was no request error.
- Reject on transaction abort or error, including after request success.
- Abort the transaction if `operation(store)` throws; retain the original exception reason.
- Close the database only after completion/rejection.
- Preserve the scope/TTL/AES-GCM encryption and all native server-autosave ownership semantics. No new storage of protected plaintext, server draft, media, profile, or clinical data.
- Add five dependency-free behavioural tests in `tests/run-indexeddb-transaction-completion-test.cjs` and an exact-head GitHub Actions CI job `offline-recovery-commit`. Existing PHPUnit source contract additionally checks the commit boundary.

## Test contract (Node synthetic IndexedDB events)
1. A request `success` alone does not settle the Promise or close the DB.
2. A later transaction `abort` rejects despite prior request success.
3. A request `error` may not become successful on transaction completion.
4. Synchronous operation exception aborts transaction and rejects with original cause.
5. A requestless operation resolves only at transaction completion.

The synthetic event tests validate the precise async control boundary; they do **not** replace real Chromium/Firefox/Safari IndexedDB persistence, private browsing, quota exhaustion, abrupt shutdown, user logout, or multi-device acceptance.

## Required further acceptance
- Exact PR SHA: PHP lint, PHPUnit, PHPStan, PHPCS, JS regression job, RC3 package CI.
- Two independent source reviews; no stale old-head CI as final evidence.
- Staging: real browser offline/reconnect, storage quota failure, crash/reload, wrong-account logout isolation, server-newer conflict, correct privacy headers, encrypted at rest, and test T-01..T-50.
- Release: Hostinger staging full package+DB parity and recovery drills; Founder explicit go/no-go; deployed checksum confirmation and live retest.
- Any CI success remains repository truth only.

## Operational truth fields
Repository HEAD (baseline): `46f716c261ca0da5ca77b317ecf4b0d04329b41f`.
Deployed Version: UNVERIFIED.
DB Version: UNVERIFIED.
Migration State: UNVERIFIED.
Live Verification Status: NOT PERFORMED.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
