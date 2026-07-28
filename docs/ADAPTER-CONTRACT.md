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
- canonical URL.

Phase 22E exposes these operations only through guarded server-side PHP functions. It does not expose a REST, AJAX, or form endpoint.

`workflow_api_version()` must exactly equal `SUPC_WORKFLOW_API_VERSION`. The current frozen value is `1.0.0`.

### Schema envelope

`schema()` must return an array containing:

- `version`, exactly matching `schema_version()`;
- `fields`, as an array with canonical field keys.

The encoded schema may not exceed 256 KiB. File 22 returns only `version` and `fields`.

### Draft envelope

`create_draft()` may be called only when `supports_native_drafts()` returns true. The normalized result contains only:

- valid opaque `native_reference`;
- controlled `status`.

File 22 does not create a shadow draft.

### Validation envelope

`validate()` must return a boolean `valid` value. Optional `errors` and `warnings` must be canonical bounded code collections, not free-form messages or payload values.

The normalized result contains only `valid`, `errors`, and `warnings`.

### Preview envelope

`preview()` must return:

- `preview_url`, as a relative internal path or absolute same-origin HTTPS URL;
- integer `expires_at`, in the future and no more than 30 minutes from the orchestration call.

Raw preview HTML and long-lived preview URLs are not accepted by the Phase 22E coordinator.

### Submission and status envelopes

`submit()` and `status()` must return:

- `native_reference`;
- one controlled status: `draft`, `pending_review`, `scheduled`, `published`, `rejected`, or `failed`;
- optional same-origin HTTPS `canonical_url`.

Only these approved keys are returned. The encoded native result may not exceed 1 MiB.

## Native errors

A native `WP_Error` must not be used to expose a payload, patient narrative, filesystem path, stack detail, or secret. File 22 replaces native errors with `supc_native_workflow_error` and retains only a sanitized native code in privacy-safe diagnostics.

## Diagnostic Adapter

A diagnostic adapter may expose a privacy-safe health report. Reports must never contain full unpublished bodies, identity evidence, consent evidence, patient narratives, secrets, or encryption keys.

## Payload safety

Direct workflow payloads may contain only scalar values, `null`, and nested arrays within the controlled depth and 1 MiB encoded-size limits. Files and protected evidence must remain in native storage and be represented only by opaque native references or secure upload tokens.

## Idempotency

Final submission uses an immutable key consisting of two UUID-v4 values separated by a colon. File 22 may generate the key and forwards it unchanged.

The native owner is responsible for durable reconciliation. If a native object exists but the File 22 response was lost, repeating the same key returns the existing mapping instead of creating another object. A key reused with a conflicting payload must fail safely under the native owner’s durable contract.

## Authorization and availability order

The coordinator verifies central and adapter permission before exposing native availability. An unauthorized account must not learn whether the native module is online or offline through different workflow responses.

## Fail-soft behavior

File 22 isolates `Throwable` failures per adapter. One broken adapter is disabled for the request and recorded in privacy-safe diagnostics; healthy adapters remain available.

Workflow exception diagnostics contain only the canonical adapter key, controlled operation key, and exception class. They never contain the payload or raw exception message.

## Prohibited behavior

An adapter must not grant permissions independently, duplicate native records, expose unsafe content, store protected evidence in generic File 22 storage, claim publication before a durable native result, convert retries into duplicates, return arbitrary extra native data through normalized envelopes, or expose direct HTTP workflow handlers without separate nonce, CSRF, authenticated-subject, rate-limit, and request-method controls.
