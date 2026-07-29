# Adapter Contract — API 1.0.0

Every content type is owned by a native module. File 22 coordinates discovery and creation without taking permanent ownership.

## Base Adapter

The base adapter supplies exact API version, canonical key, label, description, group, icon, deterministic priority, native owner and minimum version, central capability, privacy class, native availability, adapter-specific authorization, and a safe native start route.

The canonical key must match `^[a-z][a-z0-9_]{2,63}$`. Invalid and duplicate keys fail closed.

## Authorization and availability separation

For an eligible authenticated subject, File 22 resolves an adapter in this order:

1. File 22/File 20 Safe Mode;
2. valid subject and canonical key;
3. Membership Core eligibility;
4. registered adapter/workflow metadata;
5. exact API version;
6. central WordPress capability;
7. native `is_available()`;
8. adapter-specific `can_create()`;
9. requested native operation.

A native service that is offline, disabled, missing a required class, or missing its route is `unavailable`, not `permission denied`. Adapter policy may narrow permission but cannot expand a central denial. Rejected, suspended, expired-document, roleless, logged-out, or otherwise ineligible subjects do not execute native adapter methods.

## Workflow Adapter

A full workflow adapter additionally supplies:

- exact Workflow API version;
- schema version and native-draft declaration;
- role-neutral static schema;
- create/resume draft;
- validation;
- private preview;
- idempotent submission;
- native status;
- subject-aware canonical URL.

`workflow_api_version()` must equal `SUPC_WORKFLOW_API_VERSION` (`1.0.0`). Workflow API, central capability, and native-draft support are captured at registration.

Phase 22E exposes only guarded server-side PHP functions. It does not expose REST, AJAX, or a browser write controller.

## Public PHP API ownership

The complete `supc_*` function family is owned only when all of these markers agree:

- `SUPC_PUBLIC_API_VERSION = 1.0.0`;
- `SUPC_PUBLIC_API_OWNER = sabri-universal-post-composer`;
- `SUPC_PUBLIC_API_FUNCTIONS_OWNED = true`.

Any pre-existing function or marker collision prevents the entire File 22 public function family from being declared. A partial mixed-version API is prohibited. Interactive functions bind to `get_current_user_id()`. The backward-compatible subject parameter on `supc_adapter_available()` is ignored and cannot query another account.

## Authenticated-subject boundary

Public workflow functions accept no user ID. A future service/background API must be separate, capability-protected, auditable, and independently reviewed.

`canonical_url()` receives `( int $user_id, string $native_reference )`. The native owner must enforce ownership or visibility. An opaque reference is never authorization proof.

## Static and subject-aware schema contract

`schema()` is a role-neutral, data-free static contract. Static System Check always validates this base declaration and never borrows the current administrator as a representative subject.

A release-critical role-dependent adapter may additionally expose:

```php
schema_for_user( int $user_id ): array
```

File 22 advertises this optional extension through `SUPC_SUBJECT_SCHEMA_API_VERSION = 1.0.0`. Interactive schema retrieval and payload validation use `schema_for_user()` when available. File 21 must use it so Founder/Administrator-only publication types never appear in a doctor's schema.

Both schema variants must use the same `schema_version()` and normalized field vocabulary.

## Schema envelope

A schema contains only `version` and `fields`, is at most 256 KiB, contains at most 100 fields, and uses canonical field keys.

Allowed types:

- `text`, `textarea`;
- `select`, `multiselect`;
- `checkbox`, `number`;
- `date`, `datetime`;
- `url`, `email`;
- `opaque_reference`.

Allowed properties:

- `type`;
- `label_code`, optional `description_code`;
- `required`;
- `privacy_class` (`public`, `private`, or `sensitive`);
- numeric `minimum`/`maximum`;
- bounded canonical `choices`.

Unknown properties, data-bearing defaults, raw HTML, arbitrary metadata, malformed codes, invalid bounds, unsupported nesting, more than 100 choices, and oversized schemas are rejected.

## Schema-bound payload enforcement

Before native mutation, File 22 enforces the authenticated subject's schema:

- undeclared fields are rejected;
- required fields are enforced for validate, preview, and submit;
- values must match their declared scalar/array type;
- select/multiselect values must exist in `choices`;
- numeric bounds are enforced;
- email, URL, date, datetime, checkbox, and opaque-reference formats are validated;
- objects, resources, closures, non-finite floats, excessive nesting, and encoded payloads over 1 MiB are rejected.

`create_draft()` may accept a partial payload, but every supplied field must still be declared and type-valid. Files and protected evidence remain in native storage and are represented only by opaque references or separately reviewed secure upload tokens.

## Draft, validation, preview, submission, and status envelopes

Draft result:

- valid `native_reference`;
- explicit status `draft` or `pending_review`.

Validation result:

- boolean `valid`;
- bounded canonical `errors` and `warnings` codes only.

Preview result:

- relative internal or absolute same-origin HTTPS URL;
- integer future expiry no more than 30 minutes from the call.

Submit/status result:

- valid native reference;
- status limited to `draft`, `pending_review`, `scheduled`, `published`, `rejected`, or `failed`;
- optional same-origin HTTPS canonical URL.

File 22 returns only whitelisted envelope fields. Native payloads, arbitrary result metadata, preview HTML, and unsafe URLs are discarded or rejected.

## Native errors and diagnostics

Allowed native public codes are limited to:

`permission_denied`, `validation_failed`, `conflict`, `rate_limited`, `temporarily_unavailable`, `not_found`, `expired`, and `invalid_reference`.

Every other native-controlled code becomes `native_error`. Native messages/data, exception class/message, stack, path, SQL, secrets, identities, patient narratives, and full payloads are never emitted.

## File 21 release-critical contract

The `social_publication` adapter is release-ready only when it provides:

- exact File 21 owner, version, capability, group, and privacy class;
- `Diagnostic_Adapter` and full `Workflow_Adapter`;
- native draft support;
- valid role-neutral static schema;
- subject-aware schema extension;
- working native availability and route;
- privacy-safe health report.

A route-only or incomplete adapter is a release failure, not a healthy integration.

## Idempotency and ownership

Final submission uses two UUID-v4 values separated by a colon. The native owner provides durable reconciliation: same key and same payload returns the existing result; same key and conflicting payload fails; retries never create duplicate native records.

File 22 must never grant permission independently, duplicate native drafts/posts/media/moderation records, retain protected evidence, expose another subject's reference, or claim publication before a durable native result.
