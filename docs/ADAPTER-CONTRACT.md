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

### Schema envelope

`schema()` must return an array containing:

- `version`, exactly matching `schema_version()`;
- `fields`, as an array.

### Draft envelope

`create_draft()` must return a valid opaque `native_reference`. File 22 does not create a shadow draft.

### Validation envelope

`validate()` must return a boolean `valid` value and may return privacy-safe field error codes.

### Preview envelope

`preview()` must return `preview_url`. It must be a relative internal path or an absolute same-origin HTTPS URL. Raw preview HTML is not accepted by the Phase 22E coordinator.

### Submission and status envelopes

`submit()` and `status()` must return:

- `native_reference`;
- one controlled status: `draft`, `pending_review`, `scheduled`, `published`, `rejected`, or `failed`.

An optional `canonical_url` must satisfy the same internal HTTPS policy.

## Diagnostic Adapter

A diagnostic adapter may expose a privacy-safe health report. Reports must never contain full unpublished bodies, identity evidence, consent evidence, patient narratives, secrets, or encryption keys.

## Payload safety

Direct workflow payloads may contain only scalar values, `null`, and nested arrays within the controlled depth and 1 MiB encoded-size limits. Files and protected evidence must remain in native storage and be represented only by opaque native references or secure upload tokens.

## Idempotency

Final submission uses an immutable idempotency key bound to the composer session and submission attempt. Repeating the same request returns the same native result.

Recommended logical key:

`composer_session_uuid + submission_attempt_uuid`

File 22 may generate a two-UUID key, but the native owner is responsible for durable reconciliation. If a native object exists but the File 22 response was lost, the adapter reconciles the existing mapping instead of creating another object.

## Fail-soft behavior

File 22 isolates `Throwable` failures per adapter. One broken adapter is disabled for the request and recorded in privacy-safe diagnostics; healthy adapters remain available.

Workflow exception diagnostics contain only the canonical adapter key, controlled operation key, and exception class. They never contain the payload or raw exception message.

## Prohibited behavior

An adapter must not grant permissions independently, duplicate native records, expose unsafe content, store protected evidence in generic File 22 storage, claim publication before a durable native result, convert retries into duplicates, or expose direct HTTP workflow handlers without separate nonce, CSRF, rate-limit, and request-method controls.
