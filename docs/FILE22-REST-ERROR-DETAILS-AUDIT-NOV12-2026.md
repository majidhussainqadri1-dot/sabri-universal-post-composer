# File 22 — REST error metadata minimization audit (12 November 2026)

## Source baseline
GitHub main prior to this change: `99034f03d140fad58e3d87fdae6cd0b04fda81b9`. Candidate `1.0.0-rc.3`, schema source identifier `1.0.0`. Governing V12.1 File 22 WP-028, WP-051, T-01…T-50 privacy-safe error boundary.

## Verified source defect D22-SRC-04
`Session_Store::update()` and other compare-and-swap failures construct `WP_Error('supc_session_conflict', ..., ['current' => $current])`. `$current` is the entire owned orchestration row and includes user ID, native reference, raw idempotency key and payload hash. The `normalize_error()` routines in all three private controllers — `Rest_Controller`, `Reconciliation_Rest_Controller`, and `Plan_Rest_Controller` — previously passed the complete error data into a response using `array_merge($details, $extra)`. A signed-in user encountering a conflict could thus receive internal metadata that the public session projection deliberately omits.

The root cause is one **shared error-serialization boundary**, not three independent security incidents. This source finding is data minimization / diagnostic disclosure to an authorized request; it is **not evidence of cross-user exploitation or a live leak**.

## Correction scope
- `Contract_Boundary::safe_rest_error_details()` accepts only source-owned `supc_policy_violation` payloads and strips all other raw WP_Error data.
- The policy exception exposes only fixed `privacy_hold/medical_hold/copyright_hold/security_hold` states and one bounded list of 21 known File22 policy reason codes. Unknown plugin/native codes, raw session rows, patient narratives, URLs, identities, hashes and request payload are not forwarded.
- Three REST normalizers apply this filter to **raw internal error data** while retaining separately curated `$extra` response details from explicit call sites. The existing status mapping still derives from original error data internally, not from public details.
- No WordPress database schema, authorization check, session state, native storage, patient record or media payload is modified.

## Regression and acceptance
`tests/RestErrorDetailsBoundaryTest.php` uses controlled WP_Error objects and Reflection of the three real normalizers to verify:
1. CAS conflict metadata (`current`, raw idempotency key, payload hash, patient narrative) is excluded.
2. Fixed policy holds and known controlled codes survive; unknown code is rejected.
3. Explicitly curated `$extra` details remain available for browser conflict recovery.
4. Malformed or unbounded policy data is not passed.
5. Raw native adapter error payload is discarded.

All exact PR HEAD GitHub CI must pass. A real WordPress staging test must exercise conflicting users/tabs, native exceptions, patient privacy, response headers, caches and actual REST serialization. This is not a claim of deployed/live repair.

## Separate follow-up candidate — no extra patch in this PR
Reconciliation REST's `normalize_error()` currently maps `supc_policy_violation` to its fallback HTTP 400, while the other two controllers map it to 422. This is a separate status-consistency issue requiring its own source-level proof/regression before modification; it is **not marked corrected here**.

## Truth ledger
Repository HEAD baseline: `99034f03d140fad58e3d87fdae6cd0b04fda81b9`.
Deployed Version: UNVERIFIED.
DB Version: UNVERIFIED.
Migration State: UNVERIFIED.
Live Verification Status: NOT PERFORMED.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
