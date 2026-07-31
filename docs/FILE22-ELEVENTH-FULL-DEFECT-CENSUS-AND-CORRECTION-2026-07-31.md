# File 22 — Eleventh Full Defect Census and Correction

Date: 31 July 2026  
Source baseline: Draft PR #16 exact head `a945646f0a5cc0ff13e3c5937911c4cbdb3572cc`  
Review basis: complete cumulative repository plus the distributed File 00 `sabri-membership-core-1.0.1` and File 20 `sabri-unified-application-shell-1.0.0-FINAL` packages.

## Governing position

The report that the file contains more than 400 defects is treated as a serious audit trigger, not as an unverified target count. This census does not invent findings to reach a number and does not dismiss the report because earlier automated tests were green. A defect is counted only when it is supported by source inspection, a real-package mismatch, a reproducible execution path, or a regression test.

File 22 remains the role-aware creation gateway and workflow orchestrator. File 00 remains the mandatory identity/authorization authority. File 20 is the application shell. Native modules remain the canonical owners of drafts, media, moderation, publication, and permanent records.

## Confirmed defects and corrections

### 1. Critical — the distributed File 20 version 1.0.0 could disable File 22 globally

The real File 20 package exposes its base package constants but does not expose File 22's later `SABRI_SHELL_CREATE_CONTRACT_*` markers or Create producer functions. The previous File 22 Safe Mode treated any File 20 base-package claim as a claimed Create contract and therefore failed closed for the entire Composer when the real File 20 package was installed.

Correction: base package identity and optional Create-contract identity are now separate trust domains. A canonical legacy File 20 version 1.0.0 package does not disable File 22 merely because the later Create contract is absent.

### 2. High — the synthetic File 20 fixture did not match the distributed package

The shared PHPUnit fixture represented a future File 20 contract with Create markers/functions that are absent from the distributed FINAL ZIP. Earlier green tests therefore did not exercise the real integration shape.

Correction: an isolated fixture and executable test reproduce the actual File 20 version 1.0.0 package shape.

### 3. High — File 22 registered a visibility filter not consumed by File 20 version 1.0.0

The real File 20 package consumes `sabri_shell_create_url` but does not apply `sabri_shell_can_show_create`. Registering that visibility filter created a false integration claim.

Correction: File 22 always supplies the supported URL filter, but registers the role-aware visibility filter only when the later complete, package-owned Create contract exists. File 20 must be upgraded before that visibility contract can be production-accepted.

### 4. High — function-only or partial File 20 Create claims were not detected atomically

A function or one marker could be introduced without the full family being considered a claimed contract.

Correction: every Create marker and both producer functions participate in one claim inventory. Partial claims fail closed.

### 5. High — File 20 health diagnostics conflated legacy compatibility with full integration

The prior System Check could label the real File 20 package as a collision rather than a canonical legacy package missing a later contract.

Correction: diagnostics now distinguish absent package, foreign/colliding package, canonical legacy package, and complete owned Create contract.

### 6. High — adapter registration was unbounded

An integration hook could register an arbitrary number of adapters, increasing sorting, authorization, health-check, and rendering cost.

Correction: the registry accepts at most 100 adapters.

### 7. High — registry diagnostics were unbounded

Repeated invalid or colliding registrations could grow the in-memory error store for the request without a limit.

Correction: diagnostics are bounded to 200 entries plus one controlled registry-limit marker.

### 8. Medium — adapter group metadata accepted noncanonical/control-bearing values

Registration checked only nonempty length. Newlines and other noncanonical group strings could enter the immutable contract and later be normalized differently by presentation and diagnostics.

Correction: group metadata must match a canonical lowercase key pattern at registration.

### 9. Medium — adapter priority was unbounded

Arbitrary platform integers were accepted even though priority is presentation metadata.

Correction: priority is bounded to `-10000..10000`.

### 10. High — Create-page discovery still requested an unlimited result set

The query was narrowed by search token but used `posts_per_page => -1`, so it could still retrieve every matching published page.

Correction: discovery is limited to 101 likely candidates and stores at most 100 validated candidates.

### 11. High — malformed platform UUID output could be persisted as a repair lock

The generated lock token was written before its UUID-v4 shape was verified.

Correction: malformed UUID output is rejected before the atomic option write.

### 12. Medium — semantically restored option values could be reported as failed rollback

WordPress serialization can restore an equivalent object as a different object instance. Strict identity therefore produced false rollback-failure evidence.

Correction: rollback verification uses strict identity first and serialized semantic equality as the WordPress-compatible fallback.

### 13. High — Create-surface diagnostics could report PASS without evaluating a workflow

Safe Mode, missing subject, or a centrally denied subject could result in an empty diagnostic collection and therefore PASS.

Correction: these states are explicit controlled warnings and are never represented as successful evaluation.

### 14. Medium — adapter display metadata was unbounded

Labels, descriptions, icons, and routes came from dynamic adapters without byte limits.

Correction: each value has a fixed byte limit and control characters are rejected before rendering.

### 15. Medium — System Check rows were unbounded

A filter producer could return an arbitrarily large list for the Administrator health page.

Correction: at most 100 System Check rows are normalized and rendered.

### 16. Medium — raw System Check counts were unbounded and could disagree with codes

A producer could report an extreme count unrelated to the normalized code list.

Correction: counts are nonnegative, never lower than the normalized code count, and capped at 1000.

### 17. Medium — Administrator health used a second, weaker version regex

The repository already had a strict Semantic Versioning utility, but adapter-health rendering used a separate permissive regex.

Correction: all Administrator minimum-version validation uses the canonical `Version::valid()` utility.

### 18. High — workflow schema versions used a second, weaker version regex

Malformed Semantic Versions such as leading-zero numeric components could pass the workflow schema boundary.

Correction: schema versions use the same canonical strict Semantic Versioning utility.

### 19. Medium — multiselect payloads allowed duplicate and excessive values

A valid choice could be repeated, and a payload was not independently bounded to the schema choice ceiling.

Correction: multiselect values are unique, list-shaped, and limited to 100 entries.

### 20. Medium — validation-code collections were unbounded by item count

A native adapter could return a small-byte but very large nested validation structure.

Correction: each validation-code collection is bounded to 100 entries and depth remains bounded.

### 21. Medium — workflow arrays were bounded by bytes and depth but not element count

Large element-count arrays can impose iteration and normalization cost even when values are short.

Correction: every workflow array node is limited to 1000 elements.

### 22. High — generated idempotency keys were not validated

File 22 concatenated two platform UUID results and returned them without verifying the final contract.

Correction: the generated key is returned only when it matches the complete two-UUID idempotency pattern; otherwise generation fails closed.

### 23. Medium — workflow and Create routes lacked a consistent maximum length

Very long strings could reach URL parsing and redirect validation.

Correction: Create and workflow URLs/routes are bounded to 2048 bytes before parsing.

### 24. Build/evidence defect — the manifest and repository inventory were stale

Tenth-cycle assets and the new real-package compatibility assets were not all represented in the source manifest and main repository contract.

Correction: the manifest, main CI inventory, and historical evidence checks are updated cumulatively.

## Regression evidence added

- `tests/EleventhCompleteReviewTest.php`
- `tests/EleventhWorkflowHardeningTest.php`
- `tests/run-file20-legacy-1-0-0-compatibility-test.php`
- `tests/run-file20-partial-create-contract-test.php`
- `tests/run-page-transaction-hardening-test.php`
- `tests/run-invalid-idempotency-generator-test.php`
- strengthened `tests/run-page-discovery-query-test.php`
- actual-shape File 20 version 1.0.0 fixture
- `.github/workflows/eleventh-review-evidence.yml`

## Required exact-head evidence

The final corrected head must pass cumulative PHPUnit, all isolated real-package and fail-closed contracts, PHP 8.1/8.2/8.3 syntax, PHPStan, WordPress Coding Standards/security checks, Composer lock validation, repository inventory, and every cumulative historical evidence workflow.

## Release boundary

This census is source-level correction evidence. It does not authorize stacked-PR merge, File 20 contract promotion, controlled Files 00/20/21/22 staging acceptance, package promotion, live deployment, or production completion. The actual File 20 visibility contract, full role/status/document matrix, IDOR/native ownership, cache/indexing, browsers/accessibility/RTL, backup restoration, rollback proof, reproducible ZIP/checksum/manifest, and explicit Founder approval remain mandatory.
