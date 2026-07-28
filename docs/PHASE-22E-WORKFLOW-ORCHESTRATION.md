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

These are internal server-side APIs. A future HTTP, REST, AJAX, or form controller must add its own nonce, CSRF, request-method, rate-limit, and content-security boundary before calling them.

## Authorization order

Every operation resolves authorization in this order:

1. File 22 and File 20 Safe Mode;
2. canonical adapter key;
3. registered adapter existence;
4. `Workflow_Adapter` support;
5. native availability;
6. Membership Core account eligibility;
7. central required capability;
8. adapter-specific authorization.

An adapter may narrow central permission but cannot broaden it.

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

Final submission requires an immutable idempotency key. File 22 can generate a two-UUID key, but the native owner remains responsible for durable idempotency reconciliation. Repeating the same key must not create another native object.

## Result envelopes

### Schema

The schema must contain:

- `version`, exactly matching `schema_version()`;
- `fields`, as an array.

### Draft creation

The native result must contain a valid `native_reference`.

### Validation

The native result must contain a boolean `valid` field. Native modules may add privacy-safe field error codes.

### Preview

The native result must contain `preview_url`. The URL must be a relative internal route or an absolute same-origin HTTPS URL.

### Submission and status

The native result must contain:

- a valid `native_reference`;
- one controlled status: `draft`, `pending_review`, `scheduled`, `published`, `rejected`, or `failed`.

An optional `canonical_url` must satisfy the same internal HTTPS policy.

## Failure isolation and privacy

Native `Throwable` failures are converted into controlled `WP_Error` results. Diagnostic actions contain only:

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
