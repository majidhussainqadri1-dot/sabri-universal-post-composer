# System Check

File 22 contributes privacy-safe rows through `supc_system_check_report` and displays them to authorized administrators at:

`Tools → Composer Health`

## Core checks

System Check reports controlled codes for:

- Membership Core version, package provenance, and status API availability;
- canonical Create-page mapping, ambiguity, and publication state;
- bounded adapter registration/runtime errors;
- File 22 public PHP API version, owner, complete Reflection-owned functions, and collision state;
- File 20 base-package identity separately from the later File 20 Create contract;
- release-critical File 21 `social_publication` owner, version, capability, group, privacy, Diagnostic Adapter, full Workflow Adapter, native drafts, role-neutral schema, and subject-aware schema extension;
- current-administrator Create-surface presentation diagnostics.

The distributed File 20 version 1.0.0 package is reported as a canonical legacy shell with a missing later visibility/health contract, not as a collision and not as a healthy complete Create contract.

A failed row must include at least one controlled code. An unrecognized row becomes `unrecognized_check`; an unrecognized code becomes `unrecognized_diagnostic`. Raw reasons, exceptions, native messages, and arbitrary adapter fields are never displayed.

## Bounded normalization

Administrator health is intentionally bounded:

- at most 100 System Check rows;
- at most 20 normalized codes per row;
- reported count capped at 1000 and never lower than the normalized code count;
- at most 100 registered adapter rows because the registry itself is bounded;
- fixed machine row keys and diagnostic vocabularies only.

A filter producer cannot allocate an unbounded Administrator table or display an extreme raw count.

## Static adapter and workflow contract health

Static health is role-independent. It validates canonical immutable adapter metadata, native availability, privacy-safe Diagnostic Adapter codes, exact Workflow API, native-draft declaration, strict role-neutral base schema, and the optional subject-aware schema extension.

Static health does not call `can_create()`, `start_url()`, or `schema_for_user()`. It never uses the current administrator's role-specific schema as a global health result. Minimum native and schema versions use the same central strict Semantic Versioning utility as runtime compatibility decisions.

The table separately displays base API, Workflow API, native-draft support, subject-schema support, minimum native version, group, privacy, final status, and controlled codes.

## Current-user presentation diagnostics

Create-surface diagnostics are limited to the signed-in administrator because routes and cards may be role-specific. They do not replace staging tests for Founder, Administrator, verified doctor, permitted unverified doctor, student, patient, editorial-only, roleless, logged-out, pending, rejected, suspended, and expired-document accounts.

System Check must not report PASS when Safe Mode, a missing authenticated subject, central authorization denial, or native unavailability prevented an actual Create-surface evaluation. These states are controlled warnings.

## Privacy boundary

The dashboard may display only canonical machine identifiers, bounded versions, controlled statuses/codes, and Create-page candidate IDs needed for explicit repair selection. It must not display user identity, roles, documents, content, URLs, native references, idempotency keys, payloads, moderation notes, patient/clinical data, consent evidence, native messages/data, exceptions, paths, SQL, stacks, tokens, credentials, or secrets.

## Create-page states

- `ready` — configured object is a valid published page containing the shortcode and a usable internal permalink;
- `repairable` — exactly one valid candidate exists;
- `ambiguous` — multiple valid candidates exist and automatic repair is prohibited;
- `missing` — no valid candidate exists.

Inspection is memoized once per request, performs no write, and uses a finite IDs-only discovery window.

## Repair boundary

The dashboard is read-only except for capability- and nonce-protected mapping repair:

1. **Dry Run** — reports state without writing.
2. **Explicit Candidate Mapping** — maps one validated published candidate; ambiguity requires selection.
3. **Managed Page Creation** — inserts one File 22-owned page on the first free approved slug when no candidate exists.

A valid UUID-v4 atomic lock prevents concurrent repair. Mapping persistence is read back before success. Failed writes restore the previous WordPress option semantically, including serialized values. A managed page must preserve exact type, slug, publication, shortcode, permalink, and ownership metadata. Failed cleanup affects only the exact newly inserted File 22 object and escalates to emergency disable if it cannot be removed or quarantined.

The repair never edits, deletes, or overwrites unrelated pages or another module's posts, tables, media, settings, permissions, or content.

## Accessibility and acceptance

Tables have screen-reader captions and scoped headers. Repair controls have labels and result-specific notice severity.

System Check is operational evidence, not release approval. Complete Files 00/20/21/22 staging, the actual File 20 role-aware visibility upgrade, role/IDOR/cache/browser/accessibility/RTL testing, backup restoration, rollback, independent review, and Founder authorization remain mandatory.
