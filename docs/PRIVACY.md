# Privacy

File 22 applies data minimization. It stores only orchestration data necessary to route an authorized creation workflow.

## Data not owned by File 22

- identity and professional evidence;
- patient-consent evidence;
- private clinical records;
- secure PDF bytes;
- Marketplace verified contact identity;
- native moderation notes and permanent content records.

File 22 may retain only opaque native references, adapter keys, status, timestamps, and privacy-safe audit metadata when required.

## Sensitive drafts

Patient Case and other sensitive drafts are server-side by default. Plaintext `localStorage` is prohibited. Any future offline support requires encrypted, short-lived, device-bound storage, logout purge, inactivity expiry, conflict detection, and a shared-device warning.

## Public/private separation

The Create page, drafts, previews, adapter diagnostics, and status endpoints are noindex, noarchive, no-cache, and excluded from public search. Public canonical content remains the responsibility of its native owner.

## Retention

Retention is content-class specific. Sensitive abandoned drafts require shorter retention than ordinary drafts. Orphan temporary references are cleaned in bounded background jobs. Legal or security audit retention is documented separately from user-content retention.

## WordPress privacy integration

Before Core 1.0, File 22-owned records must implement personal-data export and erasure callbacks, with documented exceptions for legitimate security and audit retention.
