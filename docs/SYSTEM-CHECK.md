# System Check

File 22 contributes privacy-safe rows through the `supc_system_check_report` filter.

Initial checks:

- Membership Core contract available;
- Create page mapped and published;
- adapter registration/runtime error count.

Future checks include adapter native versions, native routes, schema compatibility, background jobs, storage ownership, stale sessions, idempotency reconciliation, and File 20 visibility-hook availability.

System Check is read-only. Repair actions must be separate, capability-protected, nonce-protected, dry-run capable, and limited to File 22-owned settings or temporary orchestration data.
