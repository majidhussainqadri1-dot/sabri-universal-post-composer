# File 22 — 13 November 2026: validate actual REST lifecycle request body size

## Source baseline and governing controls
GitHub main SHA `3626e97b87c05dc90ffffab77eb4c14f7d1f0f43`. Governing V12.1 WP-051, protected lifecycle/approval, 1 MiB `MAX_REQUEST_BYTES`. Live deployed code, WordPress version and DB/migration parity not verified.

## D22-SRC-13 — source-proven request-size bypass
`Governing_Plan_Runtime::rest_permission()` limited only the optional `Content-Length` request header, while `rest_lifecycle_execute()` called `get_json_params()` without inspecting actual body bytes. Missing, omitted or inaccurate length metadata therefore bypassed the intended early 1 MiB payload-size gate. Its later `bounded_payload()` does not guard JSON decoding costs and does not account for arbitrarily large non-payload request properties.

The other File22 private mutation controller explicitly checks `strlen($request->get_body())` before parsing, confirming the intended boundary. This is source-level input-validation risk, not an observed production DoS.

## Correction
Check `strlen((string)$request->get_body()) > self::MAX_REQUEST_BYTES` before JSON decode in the lifecycle command handler; return HTTP 413 with a generic controlled error. Preserve existing authentication, X-WP-Nonce, File00 eligibility, native owner permission and command-specific idempotency. No schema or content payload changes.

## Regression proof
`tests/PlanRestSessionLockTest.php` extends its isolated WordPress REST request fixture with a body method and verifies that 1,048,577 bytes are rejected *before* `get_json_params()` (which throws if unexpectedly invoked). CI remains exact-head. A browser and actual HTTP reverse proxy/body-limit staging test is still mandatory. Large compressed transfer encoding, rate limiting and web firewall remain infrastructure responsibilities.

## Truth ledger
Repository HEAD (baseline): `3626e97b87c05dc90ffffab77eb4c14f7d1f0f43`.
Deployed Version: UNVERIFIED.
DB Version: UNVERIFIED.
Migration State: UNVERIFIED.
Live Verification Status: NOT PERFORMED.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
