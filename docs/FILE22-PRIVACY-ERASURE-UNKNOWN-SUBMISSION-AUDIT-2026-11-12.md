# File 22 — 12 November 2026: WordPress privacy erasure / uncertain native outcome boundary

## Authority and exact source
Governing master plan V12.1, File 22 implementation plan WP-028/WP-030/WP-045/WP-051, privacy retention and native owner authority.
Source original finding: GitHub `main` `d9c979178ded5e8a89c21b55664a52cc69859729`; clean rebase target after PR #42: `69136142be8aa1842dd641caabd4234561346fb3`, candidate `1.0.0-rc.3`, repository schema identifier `1.0.0`. Deployed source, active WordPress version and database are **not** verified.

## D22-SRC-06 — source-confirmed privacy-erasure reconciliation defect
The existing `Privacy_Integration::erase_personal_data()` deletes File 22 submission rows unless a matching `supc_outbox` row is queued/retrying/processing/dead-letter; it likewise deletes the parent Composer session unless an active outbox row exists. The previous ordinary TTL cleanup correction (D22-SRC-02) protected native attempts independently of outbox state, but **the privacy erasure callback had its own older, unguarded SQL**. If the native submission outcome is uncertain and outbox enqueue fails, a user erasure request can destroy both recovery identities while an adapter may already have performed the native write.

The same callback had a partial-migration fallback deleting all user sessions when `supc_submissions` or `supc_outbox` was unavailable. The status flag only counted active outbox events, incorrectly reporting `items_retained=false` for an unsettled submission with no outbox. These are related failure modes of **one root cause: missing independent submission-retention protection across the erasure path**. This is a source-level safety/privacy finding, NOT an observed live disclosure or an assertion that native duplicates occurred.

## Narrow correction
1. In `erase_personal_data()`, remove only terminal submission states (exclude `prepared/dispatched/reconcile/retryable/dead_letter`) and never remove sessions referenced by these protected attempts, independent of outbox state.
2. Fail closed on missing recovery tables: preserve sessions pending migration diagnosis; never run the legacy all-sessions fallback.
3. Reset session deletion result before each block to avoid double-counting an unrelated deletion.
4. `active_reconciliation_count()` counts both relevant outbox records and protected submission records; `erasable_count()` uses exactly the same exclusions so WordPress privacy pagination does not loop on legitimately retained items.
5. Return truthful `items_retained`, `done` and controlled privacy messages when uncertain native state or schema failure requires retention. No draft content, patient information, upload bytes or native records move into File 22.

## Regression
`tests/PrivacyErasureUnknownSubmissionTest.php` invokes the real privacy erasure callback with synthetic WordPress tables and checks:
- Outbox absent yet native attempt `dispatched/reconcile`: generated submission and session SQL both protect the identity; privacy status truthfully reports retained.
- Recovery tables missing: session erasure deferred, no blind delete, `done=false`, clear repair requirement.
- Retention/erasable counts independently use the state-set and exclude protected records.

The probe validates SQL intent; it does **not** execute native MySQL/InnoDB transactions. Source-only CI must pass exact HEAD PHP 8.1–8.3 lint, PHPUnit, PHPCS, PHPStan, dependency-lock check and package workflows.

## Remaining gates and safety
On real WordPress staging, create a simulated uncertain native submission, force outbox insertion failure, invoke Personal Data Eraser, confirm zero loss of native recovery identity; replay reconciliation, test active and terminal states, partial schema, repeated erasure batches, backup/rollback and MySQL query plans. Verify File 00 user identity, File 21 native ownership, DB schema/migration, PHP logs and plugin checksum. Do not release without explicit founder acceptance.

Repository HEAD (baseline): `d9c979178ded5e8a89c21b55664a52cc69859729`.
Deployed Version: UNVERIFIED.
DB Version: UNVERIFIED.
Migration State: UNVERIFIED.
Live Verification Status: NOT PERFORMED.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
