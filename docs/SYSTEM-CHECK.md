# System Check

File 22 contributes privacy-safe rows through the `supc_system_check_report` filter and exposes them to authorized administrators at:

`Tools → Composer Health`

## Core checks

- Membership Core contract availability;
- Create page mapping and publication status;
- adapter registration/runtime error count;
- Create-surface route, privacy, group, and render diagnostics;
- native adapter health reports when an adapter implements `Diagnostic_Adapter`.

## Privacy boundary

The administrator dashboard may display:

- canonical adapter key;
- native module key;
- adapter API version;
- declared minimum native version;
- controlled group and privacy classification;
- `pass`, `warning`, or `fail` status;
- privacy-safe diagnostic codes.

It does not display user IDs, names, email addresses, phone numbers, identity evidence, patient data, content titles, content bodies, draft payloads, native workflow URLs, exception messages, or clinical information.

## Repair boundary

System Check remains read-only. The only Phase 22D repair operation is a separate capability-protected and nonce-protected Create-page mapping repair.

The operation supports:

1. **Dry Run** — reports whether the mapping is valid, repairable from an existing published shortcode page, or missing. It changes nothing.
2. **Repair** — either remaps File 22 to an existing published page that already contains `[sabri_universal_composer]`, or creates a new File 22-managed Create page on the first unoccupied approved slug.

The repair operation never edits, overwrites, trashes, or deletes an unrelated page. It never repairs another module's tables, posts, media, settings, permissions, or content.

## Future checks

Future separately reviewed phases may add native schema compatibility, background jobs, temporary orchestration storage, stale sessions, idempotency reconciliation, and File 20 visibility-contract verification. Those checks are not claimed by Phase 22D.
