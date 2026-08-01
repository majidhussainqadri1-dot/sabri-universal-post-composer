# File 22 — Twelfth Complete Review and Correction

Date: 1 August 2026  
Source baseline: Draft PR #18 exact head `101793200bcebb7d93e73311f836591604346c50`  
Governing scope: File 22 source implementation, exact-head CI, and the harmonized File 22 contract under Master Plan v3.0.

## Governing position

This review corrects the GitHub implementation rather than treating the Word specification as proof of source completion. A source claim is accepted only when the exact pull-request head passes the cumulative executable contracts. This record does not convert source-level evidence into staging, release, deployment, or production approval.

## Confirmed defects and corrections

1. Create Surface referenced a nonexistent `_Contract_Boundary` class, converting otherwise valid cards into controlled render exceptions. The canonical `Contract_Boundary` reference is restored.
2. The modular Workflow Validator was absent from PHPStan discovery. Its source is now analyzed directly.
3. Repository evidence still searched the former coordinator owner for validator contracts. Main and historical evidence now inspect the actual owner file.
4. Registry and coordinator live authorization rechecks were reported as unreachable after earlier checks. Explicit mutable-authority boundaries retain the race-condition recheck while making the intended semantics analyzable.
5. A username-only HTTP(S) URL could pass the credential rejection because the previous condition rejected only simultaneous username and password fields. Either credential component now fails closed.
6. Administrator adapter health called `health_report()` twice for one row, permitting status and codes from different snapshots. One captured report now supplies both values.
7. A missing repair-request method defaulted to POST. Missing or non-POST methods now fail before nonce or mutation processing.
8. The File 21 readiness fixture omitted the required explicit diagnostic status and codes. The fixture now conforms without weakening the production contract.
9. The isolated idempotency runner did not load the newly separated Workflow Validator. Its bootstrap now loads the complete dependency set.
10. A shared PHPUnit `nocache_headers()` stub incremented an undefined global outside its owning test setup. The stub now initializes its counter deterministically, and the twelfth suite treats warnings as failures.

## Evidence progression

- First corrective head `009a17370542854a0931c47e4f1ba782fb0ebed8` restored cumulative PHPUnit and repository contracts, then exposed the remaining isolated-runner and PHPStan integration defects.
- Second corrective head `af960a5849e6616cc25780f8a9c5b5daade4b3ed` passed all eleven exact-head workflows and every main-CI job. Cumulative PHPUnit reported 130 tests and 618 assertions; the single remaining warning was then traced to the shared test stub and corrected.
- The final exact-head gate includes a dedicated twelfth workflow that runs cumulative PHPUnit with `--fail-on-warning`, PHPStan, WordPress Coding Standards/security checks, the isolated idempotency contract, and inventory/ownership assertions.

## Release boundary

The corrected draft branch remains unmerged. Controlled Files 00/20/21/22 staging, actual File 20 visibility-contract promotion, role/status/document and IDOR testing, browser/accessibility/RTL acceptance, backup/restore and rollback proof, reproducible package checksums, merge approval, deployment, and production acceptance remain separate mandatory gates.
