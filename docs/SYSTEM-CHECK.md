# System Check

File 22 contributes privacy-safe rows through `supc_system_check_report` and displays them to authorized administrators at:

`Tools → Composer Health`

## Core checks

System Check reports controlled codes for:

- Membership Core version and status API availability;
- canonical Create-page mapping, ambiguity, and publication state;
- adapter registration/runtime errors;
- File 22 public PHP API version, owner, complete function ownership, and collision state;
- File 20 Create contract version, owner, function ownership, required functions, and readiness;
- release-critical File 21 `social_publication` owner, version, capability, group, privacy, Diagnostic Adapter, full Workflow Adapter, native drafts, role-neutral schema, and subject-aware schema extension;
- current-administrator Create-surface presentation diagnostics.

A failed row must include at least one controlled code. Any unrecognized row identifier is reduced to `unrecognized_check`, and any unrecognized diagnostic identifier is reduced to `unrecognized_diagnostic`. Raw `reason`, exception objects, native messages, and arbitrary adapter fields are not displayed.

## Static adapter and workflow contract health

Static health is role-independent. It validates:

- canonical adapter metadata;
- native availability;
- privacy-safe Diagnostic Adapter codes;
- exact Workflow API;
- native-draft declaration;
- role-neutral base `schema()`;
- presence of the optional subject-aware schema extension.

Static health does not call `can_create()`, `start_url()`, or `schema_for_user()`. It never uses the current administrator's role-specific schema as a global health result.

The table separately displays:

- base Adapter API;
- Workflow API;
- native-draft support;
- subject-schema extension support;
- minimum native version;
- group, privacy, final status, and controlled codes.

## Current-user presentation diagnostics

Create-surface diagnostics are intentionally limited to the signed-in administrator because routes and cards may be role-specific. They do not replace staging tests for Founder, Administrator, verified doctor, permitted unverified doctor, student, patient, editorial-only, roleless, logged-out, pending, rejected, suspended, and expired-document accounts.

## Privacy boundary

The dashboard may display only canonical machine identifiers, versions, controlled status values, controlled codes, and Create-page candidate IDs needed for explicit repair selection.

It must not display or expose through the report filter:

- user ID, name, email, phone, role list, identity evidence, or detailed document state;
- content title/body, post ID, URL, native reference, raw idempotency key, payload, or moderation notes;
- patient/clinical data or consent evidence;
- native message/data, exception class/message, path, SQL, stack, token, nonce, credential, or secret.

## Create-page states

- `ready` — configured object is a valid published page containing the shortcode and a usable permalink;
- `repairable` — exactly one valid candidate exists;
- `ambiguous` — multiple valid candidates exist and automatic repair is prohibited;
- `missing` — no valid candidate exists.

Inspection is memoized once per request and performs no write.

## Repair boundary

The dashboard is read-only except for the separate capability- and nonce-protected mapping repair:

1. **Dry Run** — reports state without writing.
2. **Explicit Candidate Mapping** — maps one validated published candidate; ambiguity requires administrator selection.
3. **Managed Page Creation** — inserts one File 22-owned page on the first free approved slug when no candidate exists.

A short atomic lock prevents concurrent repair. Mapping persistence is read back before success. A managed page must preserve exact page type, slug, publication, shortcode, permalink, and File 22 ownership metadata.

The repair never edits, overwrites, trashes, or deletes unrelated pages and never changes another module's posts, tables, media, settings, permissions, or content.

## Accessibility

System and adapter tables have screen-reader captions and scoped headers. The subject-schema support column is explicit. Repair controls have labels, and result notices use success, information, warning, or error severity according to the outcome.

## Acceptance boundary

System Check is operational evidence, not release approval. Complete Files 00/20/21/22 staging, role/IDOR/cache/browser/accessibility/RTL, backup restoration, rollback, independent review, and Founder authorization remain mandatory.
