# File 22 — Policy-violation HTTP status parity, 12 November 2026

## Exact source finding: D22-SRC-05
At `main` SHA `5cb5532d3241127989269469513191d4d8c72c5f`, `Policy_Engine::evaluate()` returns WP_Error `supc_policy_violation` with intended HTTP 422 for a failed compliance or medical/rights/privacy hold. `Rest_Controller::normalize_error()` maps that code to 422. `Plan_Rest_Controller::normalize_error()` explicitly overrides it to 422. But `Reconciliation_Rest_Controller::normalize_error()` omitted the code from its 422 match branch and fell through to HTTP 400, despite being the main durable submission path.

This is a **source-proven HTTP contract inconsistency**, not evidence of a live failed publish or security breach.

## Narrow root-cause correction
- Add `supc_policy_violation` to Reconciliation REST's fixed 422 status mapping.
- Strengthen `tests/RestErrorDetailsBoundaryTest.php` to require 422 across all three actual controller normalizers while continuing to verify that their public details contain only fixed policy codes and holds.
- No changes to error identifiers, permissions, policy decisions, native content, database, migrations, token storage or request payload.

## Acceptance limits
Require exact PR SHA CI green, independent diff review, then real WordPress staging tests for explicit policy holds: signed-in accounts, correct 422 status, safe details/no patient text, no native mutation, no cache leakage, recovery consistency. Any production deployment requires verified package checksum and live retest.

Repository baseline: `5cb5532d3241127989269469513191d4d8c72c5f`.
Deployed Version: UNVERIFIED.
DB Version: UNVERIFIED.
Migration State: UNVERIFIED.
Live Verification Status: NOT PERFORMED.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
