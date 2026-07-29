# Phase 22E — Guarded Native Workflow Orchestration Foundation

## Purpose

Phase 22E introduces a server-side orchestration boundary for native modules implementing `Workflow_Adapter`. It does not add a public REST endpoint, browser form, autosave store, or File 22-owned permanent draft record.

> One gateway, one native record.

## Supported operations

The coordinator provides guarded server-side access to:

- versioned native schema discovery;
- create or resume a native draft;
- side-effect-free validation;
- same-origin private preview;
- idempotent native submission;
- native status retrieval;
- subject-aware canonical URL retrieval.

Public PHP integration functions are:

- `supc_workflow_schema( $adapter_key )`;
- `supc_workflow_create_draft( $adapter_key, $native_reference, $payload )`;
- `supc_workflow_validate( $adapter_key, $payload )`;
- `supc_workflow_preview( $adapter_key, $payload )`;
- `supc_workflow_submit( $adapter_key, $idempotency_key, $payload )`;
- `supc_workflow_status( $adapter_key, $native_reference )`;
- `supc_workflow_canonical_url( $adapter_key, $native_reference )`;
- `supc_generate_idempotency_key()`.

The interactive functions accept no user ID. They bind to `get_current_user_id()`. A future HTTP, REST, AJAX, form, service, or background controller requires a separate nonce, CSRF, request-method, rate-limit, authenticated-subject, capability, audit, and content-security boundary.

## Registration-time contract snapshot

Each direct workflow adapter declares:

```text
workflow_api_version() === SUPC_WORKFLOW_API_VERSION
```

The current frozen workflow API version is `1.0.0`. At registration, File 22 captures the workflow API version, required capability, and native-draft support declaration. This lets central eligibility and capability gates run without invoking native runtime methods for an ineligible subject.

## Authorization order

Every interactive operation follows this order:

1. File 22/File 20 Safe Mode;
2. positive current authenticated user and canonical adapter key;
3. Membership Core account eligibility;
4. registered workflow adapter and captured workflow contract;
5. exact workflow API version;
6. central required capability;
7. adapter-specific authorization;
8. native availability;
9. requested native operation.

A suspended, rejected, expired-document, or otherwise ineligible account is denied before native adapter authorization, availability, schema, status, or URL methods run. An adapter may narrow central permission but cannot broaden it.

## Ownership and IDOR boundary

Native references are opaque identifiers, not proof of ownership. Status and canonical URL operations pass the authenticated subject to the native adapter.

The canonical URL contract is:

```php
canonical_url( int $user_id, string $native_reference ): string
```

The native module must verify that the subject owns or may view the referenced object. On denial it returns no URL; File 22 fails closed. Cross-user reference guessing must never reveal a canonical URL.

## Payload and size boundaries

File 22 accepts only arrays containing strings, integers, finite floats, booleans, `null`, and bounded nested arrays.

Objects, resources, closures, non-finite numbers, excessive nesting, and encoded payloads larger than 1 MiB are rejected before native invocation. Protected files, patient evidence, identity documents, and consent evidence stay in native storage and are represented only by opaque references.

Limits:

- request payload: 1 MiB encoded;
- schema: 256 KiB encoded;
- native result: 1 MiB encoded;
- schema fields: 100;
- select/multiselect choices per field: 100.

## Strict schema vocabulary

A schema contains `version` and `fields`. The version must equal `schema_version()`.

Allowed field types:

- `text`, `textarea`, `select`, `multiselect`, `checkbox`;
- `number`, `date`, `datetime`, `url`, `email`;
- `opaque_reference`.

Allowed field properties:

- `type`;
- `label_code`;
- `description_code`;
- `required`;
- `privacy_class`;
- `minimum`;
- `maximum`;
- `choices`.

Privacy is limited to `public`, `private`, or `sensitive`. Labels, descriptions, and choices use canonical codes, not arbitrary native prose. Unknown properties, HTML, data-bearing defaults, unsupported nested metadata, invalid numeric bounds, or excessive choices are rejected.

## Draft creation

Direct draft orchestration is available only when the captured native-draft declaration is true.

The result must explicitly contain:

- valid `native_reference`;
- `status` equal to `draft` or `pending_review`.

Missing status, `scheduled`, `published`, `rejected`, `failed`, or unknown status is invalid for draft creation. File 22 creates no shadow draft.

## Validation

The normalized validation result contains only:

- boolean `valid`;
- canonical `errors` codes;
- canonical `warnings` codes.

Free-form messages, patient narratives, and raw field values are rejected.

## Preview

Preview returns only:

- internal relative or same-origin HTTPS `preview_url`;
- integer `expires_at`, in the future and no more than 30 minutes away.

Raw preview HTML, external URLs, HTTP downgrade, and long-lived previews are rejected.

## Submission and status

Submission and status return only:

- valid `native_reference`;
- one of `draft`, `pending_review`, `scheduled`, `published`, `rejected`, or `failed`;
- optional internal same-origin `canonical_url`.

Final submission requires exactly two UUID-v4 values separated by a colon. The native owner remains responsible for durable idempotency reconciliation.

## Native errors and exception privacy

Native `WP_Error` objects are never returned unchanged. Only a fixed File 22 code vocabulary may leave the native boundary:

- `permission_denied`;
- `validation_failed`;
- `conflict`;
- `rate_limited`;
- `temporarily_unavailable`;
- `not_found`;
- `expired`;
- `invalid_reference`.

Every other native code becomes `native_error`. Native messages and data are discarded.

Exception class names are not emitted. Exception diagnostics contain only the canonical adapter key, controlled operation key, and fixed `native_exception` code. Payloads and raw exception messages are excluded.

## Administrator health

Static Adapter Health reports role-independent:

- base Adapter API compatibility;
- direct Workflow API compatibility;
- native-draft declaration;
- strict schema-contract health;
- native availability;
- privacy-safe diagnostic codes.

It does not replace runtime role-matrix testing on staging.

## Explicit non-goals

Phase 22E does not claim:

- public HTTP controllers;
- browser autosave;
- File 22-owned draft persistence;
- upload handling;
- background retries;
- privileged subject impersonation;
- durable idempotency storage;
- File 21 direct workflow implementation;
- staging or production approval.

Those require separate implementation, review, and acceptance.
