# Phase 22E — Guarded Native Workflow Orchestration Foundation

## Purpose

Phase 22E introduces a server-side orchestration boundary for native modules that implement `Workflow_Adapter`. It does not add a public REST endpoint, browser form, autosave store, or File 22-owned permanent draft record.

The Phase 22E rule remains:

> One gateway, one native record.

## Supported operations

The coordinator provides guarded server-side access to:

- versioned native schema discovery;
- create or resume a native draft;
- side-effect-free validation;
- same-origin private preview;
- idempotent native submission;
- native status retrieval;
- same-origin canonical URL retrieval.

Public PHP integration functions:

- `supc_workflow_schema()`;
- `supc_workflow_create_draft()`;
- `supc_workflow_validate()`;
- `supc_workflow_preview()`;
- `supc_workflow_submit()`;
- `supc_workflow_status()`;
- `supc_workflow_canonical_url()`;
- `supc_generate_idempotency_key()`.

These are internal server-side APIs. A future HTTP, REST, AJAX, or form controller must add its own nonce, CSRF, request-method, rate-limit, authenticated-subject, and content-security boundary before calling them.

## Version negotiation

Every direct workflow adapter must declare:

```text
workflow_api_version() === SUPC_WORKFLOW_API_VERSION
```

The current frozen Phase 22E workflow API version is `1.0.0`. A base adapter API match does not substitute for the direct workflow handshake.

## Authorization order

Every operation resolves authorization in this order:

1. File 22 and File 20 Safe Mode;
2. canonical adapter key;
3. registered adapter existence;
4. `Workflow_Adapter` support;
5. exact workflow API version;
6. Membership Core account eligibility;
7. central required capability;
8. adapter-specific authorization;
9. native availability.

Permission is resolved before native availability is disclosed. An adapter may narrow central permission but cannot broaden it.

## Payload boundary

File 22 accepts only arrays containing:

- strings;
- integers;
- finite floats;
- booleans;
- `null`;
- nested arrays up to the controlled depth limit.

Objects, resources, closures, non-finite numbers, excessive nesting, and encoded payloads larger than 1 MiB are rejected before native invocation.

Files, patient evidence, identity documents, consent evidence, and other protected bytes must be represented by native-module-owned opaque references. They must not be copied into generic File 22 storage.

## Native reference and idempotency boundary

Native references are opaque identifiers limited to a conservative canonical character set and maximum length.

Final submission requires exactly two UUID-v4 values separated by a colon. File 22 can generate this key, but the native owner remains responsible for durable idempotency reconciliation. Repeating the same key must return the same canonical native result and must not create another object.

## Size boundaries

- request payload: maximum 1 MiB encoded;
- schema result: maximum 256 KiB encoded;
- native operation result: maximum 1 MiB encoded.

Type, nesting, and size checks are all required. Passing one check does not bypass the others.

## Result envelopes

The coordinator returns only approved keys. Additional native fields are discarded rather than propagated.

### Schema

The schema must contain:

- `version`, exactly matching `schema_version()`;
- `fields`, as an array whose field keys are canonical.

Only `version` and `fields` are returned.

### Draft creation

Direct draft orchestration is allowed only when `supports_native_drafts()` is true.

The returned envelope contains only:

- `native_reference`;
- one controlled status.

### Validation

The returned envelope contains only:

- boolean `valid`;
- canonical `errors` code collection;
- canonical `warnings` code collection.

Free-form validation messages, patient narratives, and raw field values are rejected.

### Preview

The returned envelope contains only:

- `preview_url`;
- integer `expires_at`.

The URL must be a relative internal route or an absolute same-origin HTTPS URL. Expiration must be in the future and no more than 30 minutes from the orchestration call.

### Submission and status

The returned envelope contains:

- a valid `native_reference`;
- one controlled status: `draft`, `pending_review`, `scheduled`, `published`, `rejected`, or `failed`;
- optional same-origin `canonical_url`.

## Native error normalization

Native `WP_Error` objects are never returned unchanged. File 22 returns a generic `supc_native_workflow_error` and retains only:

- canonical adapter key;
- controlled operation key;
- sanitized native error code.

Native messages and native error data are discarded.

## Failure isolation and privacy

Native `Throwable` failures are converted into controlled `WP_Error` results. Exception diagnostic actions contain only:

- adapter key;
- operation key;
- exception class.

Payloads, native exception messages, user data, patient narratives, identity data, secrets, and protected evidence are never written to the diagnostic action.

## Explicit non-goals

Phase 22E does not claim:

- public HTTP controllers;
- browser autosave;
- File 22-owned draft persistence;
- upload handling;
- background retries;
- durable idempotency storage;
- File 21 direct workflow implementation;
- staging or production approval.

Those require separate native-module implementation and separate review.
