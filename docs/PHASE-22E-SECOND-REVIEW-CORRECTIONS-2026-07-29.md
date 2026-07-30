# Phase 22E Second Independent Review Corrections — 2026-07-29

## Review disposition

The second independent review superseded the earlier no-known-blocker conclusion and placed PR #5 back under `REQUEST CHANGES`. The implementation was not advanced until every recorded finding below was corrected and covered by focused regression tests.

## Corrected findings

### 1. Canonical URL ownership and IDOR boundary

`Workflow_Adapter::canonical_url()` now receives both the authenticated `user_id` and the opaque native reference. The native owner must verify ownership or visibility for that subject before returning a URL. File 22 rejects an empty or unsafe result. Cross-user reference access is covered by regression tests.

### 2. Authenticated-subject binding

The public workflow PHP functions no longer accept a caller-supplied user ID. They bind interactive execution to `get_current_user_id()`. A future privileged service/background contract is outside Phase 22E and must use a separate explicit capability and audit boundary.

### 3. Authorization before native runtime methods

Membership Core account eligibility is evaluated before adapter lookup details or native runtime methods are exposed. Workflow API version, required capability, and native-draft support are captured as registration-time contract metadata. After central eligibility and capability checks, the adapter-specific authorization method runs before native availability and operation methods.

### 4. Privacy-safe diagnostic allowlists

Native-controlled error codes are no longer trusted merely because they match a regular expression. Only a fixed File 22 allowlist is exposed; every other native code becomes `native_error`. Exception class names are no longer emitted. Exception diagnostics use the fixed code `native_exception` and exclude payloads and raw messages.

### 5. Draft-state restriction

`create_draft()` now requires an explicit status in the native result. Only `draft` and `pending_review` are valid for this operation. Missing, scheduled, published, rejected, failed, or unknown statuses fail closed.

### 6. Strict schema-definition vocabulary

Schema fields are normalized through a frozen vocabulary. Allowed field types are:

- `text`;
- `textarea`;
- `select`;
- `multiselect`;
- `checkbox`;
- `number`;
- `date`;
- `datetime`;
- `url`;
- `email`;
- `opaque_reference`.

Allowed properties are limited to `type`, `label_code`, `description_code`, `required`, `privacy_class`, `minimum`, `maximum`, and `choices`. Arbitrary properties, data-bearing defaults, HTML, unsupported types, invalid bounds, excessive fields, and excessive choices are rejected.

### 7. Workflow health in System Check

Static Adapter Health now includes workflow API version, native-draft support, and role-independent schema-contract health. A direct workflow incompatibility can no longer appear as a healthy adapter merely because the base Adapter API is valid.

### 8. Public API and lifecycle analysis

PHPStan now analyzes `includes/core/functions.php`, `includes/core/class-plugin.php`, and Shell integration in addition to the coordinator. PHPUnit uses a workflow-aware bootstrap and tests the public current-user-bound API, plugin singleton registry path, cross-user ownership denial, strict schema, draft-state restrictions, and diagnostic allowlists.

### 9. Exact evidence chain

A new review-evidence workflow replaces phrase-only proof. It verifies the reviewed implementation commit, checks that later changes are limited to approved documentation/evidence files, queries GitHub Actions for the exact CI run and head SHA, verifies successful conclusion, and confirms the second-review records in the source manifest.

## Corrected runtime implementation head

`a9ad3882fb5f1305b375a0a55ac9501b9389cdcf`

## Automated correction result

File 22 CI run `173` (`30412078102`) completed successfully for the corrected runtime implementation head. The run passed PHP 8.1–8.3 syntax, WordPress security standards, PHPStan, PHPUnit contract tests, Composer validation, and the repository contract.

## Release boundary

These corrections do not authorize merge, staging acceptance, production packaging, or deployment. PR #5 remains Draft and must still pass exact evidence verification, a separate post-correction verification, cross-plugin staging, role-matrix acceptance, Founder review, backup, and rollback gates.
