# Adapter Contract — API 1.0.0

Every content type is owned by a native module. File 22 coordinates discovery and creation without taking permanent ownership.

## Base Adapter

The base adapter supplies:

- exact adapter API version;
- canonical machine key;
- label and description;
- group, icon, and deterministic priority;
- native module identifier and minimum version;
- central required capability;
- privacy classification;
- native availability;
- adapter-specific authorization restriction;
- safe start URL.

The canonical key must match `^[a-z][a-z0-9_]{2,63}$`. Invalid keys are rejected, never silently rewritten.

## Workflow Adapter

A full workflow adapter additionally supplies:

- exact direct workflow API version;
- schema version;
- native-draft support declaration;
- versioned schema;
- create or resume draft with an explicit native reference;
- side-effect-free validation;
- private preview;
- idempotent submission;
- native status mapping;
- subject-aware canonical URL resolution.

Phase 22E exposes these operations only through guarded server-side PHP functions. It does not expose a REST, AJAX, or form endpoint.

`workflow_api_version()` must exactly equal `SUPC_WORKFLOW_API_VERSION`. The current frozen value is `1.0.0`. Workflow API version, required capability, and native-draft support are captured as registration-time metadata so central authorization can run before native runtime methods.

## Authenticated-subject boundary

Public interactive workflow functions do not accept a user ID. They bind to `get_current_user_id()`.

A future privileged background or service execution contract must be separate, explicitly capability-protected, and auditable. It must not reuse the interactive functions to impersonate another account.

`canonical_url()` has the contract:

```php
canonical_url( int $user_id, string $native_reference ): string
```

The native owner must verify ownership or visibility for that authenticated subject. An empty string is returned on native denial and File 22 converts it into a controlled error. Guessing another user's reference must never disclose its URL.

## Authorization and availability order

Runtime operations use this order:

1. File 22/File 20 Safe Mode;
2. valid authenticated subject and canonical adapter key;
3. Membership Core account eligibility;
4. registered workflow contract snapshot;
5. exact workflow API version;
6. central required capability;
7. adapter-specific authorization;
8. native availability;
9. requested native operation.

A suspended, rejected, expired-document, or otherwise ineligible account must not cause native adapter runtime methods to execute. An adapter may narrow central permission but cannot broaden it.

## Schema envelope

`schema()` must return `version` and `fields`. The encoded schema may not exceed 256 KiB. File 22 returns only normalized `version` and `fields`.

A schema may contain at most 100 fields. Field keys must be canonical. Allowed field types are:

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

Allowed field properties are limited to:

- `type`;
- `label_code`;
- `description_code`;
- `required`;
- `privacy_class`;
- `minimum`;
- `maximum`;
- `choices`.

`privacy_class` must be `public`, `private`, or `sensitive`. Labels and descriptions are canonical codes rather than arbitrary native prose. Data-bearing defaults, raw HTML, arbitrary metadata, unsupported nested structures, invalid numeric bounds, more than 100 choices, and unknown properties are rejected.

## Draft envelope

`create_draft()` may be called only when the registration-time `supports_native_drafts` contract is true. The native result must explicitly contain:

- valid opaque `native_reference`;
- `status`, limited to `draft` or `pending_review`.

A missing status and publication/scheduling/failure statuses are invalid for draft creation. File 22 does not create a shadow draft.

## Validation envelope

`validate()` must return a boolean `valid` value. Optional `errors` and `warnings` must be canonical bounded code collections, not free-form messages or payload values.

The normalized result contains only `valid`, `errors`, and `warnings`.

## Preview envelope

`preview()` must return:

- `preview_url`, as a relative internal path or absolute same-origin HTTPS URL;
- integer `expires_at`, in the future and no more than 30 minutes from the orchestration call.

Raw preview HTML and long-lived preview URLs are not accepted.

## Submission and status envelopes

`submit()` and `status()` must return:

- `native_reference`;
- one controlled status: `draft`, `pending_review`, `scheduled`, `published`, `rejected`, or `failed`;
- optional same-origin HTTPS `canonical_url`.

Only these approved keys are returned. The encoded native result may not exceed 1 MiB.

## Native errors and diagnostics

A native `WP_Error` must not expose a payload, patient narrative, filesystem path, stack detail, secret, or data-bearing error code.

File 22 exposes only this fixed native-error vocabulary:

- `permission_denied`;
- `validation_failed`;
- `conflict`;
- `rate_limited`;
- `temporarily_unavailable`;
- `not_found`;
- `expired`;
- `invalid_reference`.

Every other native-controlled error code becomes `native_error`. Raw native messages and data are discarded.

Native exception class names are not published. Exception diagnostics use only the canonical adapter key, controlled operation key, and fixed `native_exception` code.

## Diagnostic Adapter and System Check

A diagnostic adapter may expose a privacy-safe health report. Reports must never contain full unpublished bodies, identity evidence, consent evidence, patient narratives, secrets, or encryption keys.

Static Adapter Health additionally verifies direct workflow API compatibility, native-draft declaration, and strict schema compatibility without using a user's draft or content payload.

## Payload safety

Direct workflow payloads may contain only scalar values, `null`, and nested arrays within the controlled depth and 1 MiB encoded-size limits. Files and protected evidence must remain in native storage and be represented only by opaque native references or secure upload tokens.

## Idempotency

Final submission uses an immutable key consisting of two UUID-v4 values separated by a colon. File 22 may generate the key and forwards it unchanged.

The native owner is responsible for durable reconciliation. If a native object exists but the File 22 response was lost, repeating the same key returns the existing mapping instead of creating another object. A key reused with a conflicting payload must fail safely under the native owner's durable contract.

## Prohibited behavior

An adapter must not grant permissions independently, duplicate native records, expose unsafe content, store protected evidence in generic File 22 storage, claim publication before a durable native result, convert retries into duplicates, return arbitrary extra native data, expose another subject's reference, encode private information into an error code, or expose direct HTTP workflow handlers without separate nonce, CSRF, authenticated-subject, rate-limit, and request-method controls.
