# System Check

File 22 contributes privacy-safe rows through the `supc_system_check_report` filter and exposes them to authorized administrators at:

`Tools → Composer Health`

## Core checks

- Membership Core contract availability;
- Create page mapping, ambiguity, and publication status;
- adapter registration/runtime error count;
- current-administrator Create-surface route, privacy, group, and render diagnostics;
- role-independent static adapter metadata and native availability;
- native adapter health reports when an adapter implements `Diagnostic_Adapter`.

## Scope boundary

Static Adapter Health does not call `can_create()` or `start_url()`. It validates adapter metadata and native availability independently of the currently signed-in role.

Create-surface invocation diagnostics are intentionally limited to the current administrator because native routes may be role-specific. They do not replace the Founder, Administrator, verified doctor, permitted doctor, patient, student, suspended, rejected, and logged-out staging matrix.

## Privacy boundary

The administrator dashboard may display:

- canonical adapter key;
- native module key;
- adapter API version;
- declared minimum native version;
- controlled group and privacy classification;
- `pass`, `warning`, or `fail` status;
- privacy-safe diagnostic codes;
- Create-page candidate IDs when explicit administrator selection is required.

It does not display user IDs, names, email addresses, phone numbers, identity evidence, patient data, content titles, content bodies, draft payloads, native workflow URLs, exception messages, or clinical information.

## Create-page states

- `ready` — the configured object is a valid published WordPress page with the shortcode and usable permalink;
- `repairable` — exactly one valid candidate exists;
- `ambiguous` — multiple valid candidates exist and automatic repair is prohibited;
- `missing` — no valid candidate exists.

Inspection is memoized once per request and performs no write.

## Repair boundary

System Check remains read-only. The only Phase 22D mutation is a separate capability-protected and nonce-protected Create-page mapping repair.

The operation supports:

1. **Dry Run** — reports the current state and changes nothing.
2. **Explicit Candidate Mapping** — maps one valid candidate; an ambiguous state requires administrator selection.
3. **Managed Page Creation** — creates one File 22-managed page on the first unoccupied approved slug when no candidate exists.

A mutation lock prevents concurrent File 22 repairs. Mapping persistence is read back before success is reported. A managed page must retain exact post type, slug, publication status, shortcode, permalink, and ownership metadata after insertion.

The repair operation never edits, overwrites, trashes, or deletes an unrelated page. It never repairs another module's tables, posts, media, settings, permissions, or content.

## Accessibility

System and adapter tables include screen-reader captions and scoped column headers. Repair outcomes use success, information, warning, or error notice severity according to the result.

## Future checks

Future separately reviewed phases may add native schema compatibility, background jobs, temporary orchestration storage, stale sessions, idempotency reconciliation, and File 20 visibility-contract verification. Those checks are not claimed by Phase 22D.
