# Adapter Contract — API 1.0.0

Every content type is owned by a native module. File 22 coordinates discovery and creation without taking permanent ownership.

## Bootstrap ownership

File 22 owns its `SUPC_*` runtime path, URL, version, schema, adapter, workflow, subject-schema, and Membership Core minimum-version constants. If any core constant, interface, or runtime class already exists before bootstrap, File 22 must not consume the foreign value, load through a foreign path, or continue partially. It remains inert, registers an administrator notice, and schedules its own deactivation.

## Base Adapter

The base adapter supplies exact API version, canonical key, label, description, group, icon, deterministic priority, native owner and minimum version, central capability, privacy class, native availability, adapter-specific authorization, and a safe native start route.

Registration constraints:

- key: `^[a-z][a-z0-9_]{2,63}$`;
- central capability: nonempty canonical WordPress capability;
- native module: canonical lowercase hyphenated slug;
- minimum native version: strict bounded Semantic Versioning;
- privacy: exactly `public`, `private`, or `sensitive`;
- group: canonical lowercase key `^[a-z][a-z0-9_]{0,63}$`;
- priority: integer `-10000..10000`;
- registry capacity: at most 100 adapters.

Dynamic display constraints are applied before rendering: label at most 160 bytes, description 1000 bytes, icon 64 bytes, and start route 2048 bytes. Empty values, control characters, unsafe routes, invalid metadata, and duplicate keys fail closed. Registry diagnostics are bounded to 200 entries plus one controlled limit marker.

## Authorization and availability order

For an eligible authenticated subject, File 22 resolves an adapter in this order:

1. File 22 and trusted File 20 Create-contract Safe Mode;
2. valid subject and canonical key;
3. Membership Core eligibility;
4. immutable adapter/workflow metadata;
5. central WordPress capability from the registration snapshot;
6. compatible workflow API;
7. native `is_available()`;
8. adapter-specific `can_create()`;
9. requested native operation.

A capability denial is final and precedes workflow-compatibility or native-health disclosure. Native unavailability is not permission denial. Adapter policy may narrow permission but cannot expand a central denial. Authorization, Safe Mode, capability, native availability, and adapter policy are re-evaluated on every call.

## Workflow Adapter

A full workflow adapter additionally supplies:

- exact Workflow API version;
- strict schema version and native-draft declaration;
- role-neutral static schema;
- optional subject-aware `schema_for_user( int $user_id )`;
- create/resume draft;
- validation;
- private preview;
- idempotent submission;
- native status;
- subject-aware canonical URL.

`workflow_api_version()` must equal `SUPC_WORKFLOW_API_VERSION` (`1.0.0`). Workflow API, central capability, and native-draft support are captured at registration. A non-workflow adapter is `not_applicable` in workflow health. A Workflow Adapter without its immutable workflow snapshot is a failure. An incompatible workflow API is reported without invoking schema or native methods.

Phase 22E exposes guarded server-side PHP functions only; it has no REST, AJAX, or browser write controller.

## Public PHP API ownership

The complete `supc_*` family is owned only when version, owner, true ownership, and empty collision markers agree and Reflection resolves every function to File 22's exact `includes/core/functions.php` file. Any pre-existing public function or marker prevents the whole family from being declared and forces the internal Composer into Safe Mode. Partial mixed APIs are prohibited.

Interactive functions bind to `get_current_user_id()`. The compatibility subject parameter on `supc_adapter_available()` is ignored.

## File 20 package and Create contract

The distributed File 20 version 1.0.0 base package and the later File 20 Create contract are separate contracts.

A canonical legacy File 20 package is identified by its directory, bootstrap filename, slug, real paths, and valid package version. It consumes `sabri_shell_create_url`, so File 22 may supply the canonical Create URL. It does not expose the later Create contract markers/functions and therefore is not trusted for role-aware visibility or emergency-state decisions. Its valid base constants alone must not disable File 22.

The later Create contract is atomic. Once any marker or producer function is claimed, all of these must agree:

- exact Create contract version `1.0.1`;
- owner `sabri-unified-application-shell`;
- true function-ownership marker;
- both required producer functions;
- package-owned Reflection sources;
- preloaded package-owned Safe Mode class;
- public static package-owned `disabled()` method.

Marker-only, function-only, partial, foreign, inherited, autoload-only, or colliding claims fail closed. The `sabri_shell_can_show_create` bridge is registered only when the complete contract is owned.

## Create-page and route trust

Native start routes, previews, canonical URLs, and Create-page permalinks must be at most 2048 bytes and be relative internal routes or absolute same-origin HTTPS URLs without credentials, control characters, backslashes, protocol-relative form, downgrade, or port mismatch.

Create-page discovery is finite: it queries a bounded IDs-only candidate window and revalidates every result. Multiple valid candidates require explicit administrator selection.

Malformed, expired, invalid-token, or implausibly future-dated repair locks are discarded. A new lock is persisted only after the generated token passes the UUID-v4 contract. Failed mapping restores absence or the exact semantic WordPress option value, including serialized arrays/objects. Cleanup may affect only the exact newly inserted File 22-managed object.

## Private-response boundary

A direct template, widget, or programmatic shortcode invocation must establish no-cache and noindex headers before evaluating account or adapter state. When output has begun, File 22 returns a generic data-free notice. Secure late rendering may print the scoped stylesheet once after `wp_head`.

System Check never reports Create-surface PASS when Safe Mode, a missing subject, central denial, or native unavailability prevented evaluation.

## Schema envelope

A normalized schema contains only `version` and `fields`, is at most 256 KiB, contains at most 100 fields, and uses canonical field keys. Native top-level metadata outside the envelope is discarded. Unknown field-definition properties are rejected.

Allowed types:

- `text`, `textarea`;
- `select`, `multiselect`;
- `checkbox`, `number`;
- `date`, `datetime`;
- `url`, `email`;
- `opaque_reference`.

Allowed properties are `type`, `label_code`, optional `description_code`, boolean `required`, `privacy_class`, numeric `minimum`/`maximum`, and `choices` only for select/multiselect. Choice fields require 1–100 canonical choices. Other types may not declare choices.

## Schema-bound payload enforcement

Before native mutation, File 22 enforces the authenticated subject's schema:

- undeclared fields are rejected;
- required fields are enforced for validate, preview, and submit;
- arrays are limited to 1000 elements per node, depth 12, and 1 MiB encoded payload size;
- select/multiselect values must be declared; multiselect is list-shaped, unique, and limited to 100 values;
- numeric bounds and email/URL/date/datetime/checkbox/reference formats are enforced;
- objects, resources, closures, non-finite floats, excessive depth/count, and unsafe values are rejected.

`create_draft()` may accept a partial payload, but every supplied field remains declared and type-valid. Files and protected evidence remain in native storage and are represented only by opaque references or independently reviewed secure upload tokens.

## Native result envelopes

Draft result contains a valid reference and explicit `draft` or `pending_review` state. Validation contains boolean `valid` and bounded canonical errors/warnings, at most 100 entries per collection depth. Preview contains an internal URL and an expiry no more than 30 minutes away. Submit/status contains a valid reference, one controlled status, and an optional internal canonical URL.

File 22 returns only whitelisted fields. Native payloads, arbitrary metadata, preview HTML, raw errors, and unsafe URLs are discarded or rejected.

## Idempotency, errors, and ownership

Final submission uses two validated UUID-v4 values separated by a colon. Malformed platform UUID generation returns no key. The native owner must durably reconcile retries and prevent duplicate mutation.

Allowed native public codes are limited to `permission_denied`, `validation_failed`, `conflict`, `rate_limited`, `temporarily_unavailable`, `not_found`, `expired`, and `invalid_reference`. Other codes become `native_error`; raw messages/data and exceptions are never exposed.

`supc_adapter_matches()` is current-subject bound: owner match is true only when the adapter is currently available and authorized. File 22 never grants permission independently, duplicates native drafts/posts/media/moderation records, stores protected evidence, exposes another subject's reference, or claims publication before a durable native result.

## File 21 release-critical contract

The `social_publication` adapter is release-ready only when it provides exact owner/version/capability/group/privacy metadata, `Diagnostic_Adapter`, full `Workflow_Adapter`, native drafts, valid role-neutral schema, subject-aware schema, working availability/route, and privacy-safe health. A route-only or incomplete adapter is a release failure.
