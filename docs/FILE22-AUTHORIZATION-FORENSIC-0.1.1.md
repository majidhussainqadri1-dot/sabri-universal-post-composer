# File 22 — Complete Create Authorization Forensic Correction 0.1.1

## Governing obstruction

The public `/create/` surface could continue returning `No creation permission is available for this account` after isolated Membership, capability, and role corrections because the authorization decision spans several independent modules and persisted runtime states.

## Complete gate inventory

Version 0.1.1 evaluates, in one read-only administrator report:

1. File 00 plugin, database-schema, and public-contract versions;
2. Reflection-owned `smc_membership_state()` availability;
3. explicit Membership state, institutional authority, and hard blocks;
4. File 21 `sabri_feed_create_posts` capability;
5. File 21 runtime/API version;
6. duplicate installed and active File 21 copies, including the legacy plugin name;
7. persisted File 21 General and public Composer settings;
8. File 21 Safe Mode and Emergency Disable;
9. `social_publication` adapter registration and immutable capability metadata;
10. native adapter availability and native `can_create()` policy;
11. Create page, public API, File 20 contract, and static adapter/workflow health.

No single failed gate stops the remaining independent diagnostic checks. The resulting `current_user_authorization` row reports every safe diagnostic code found in the same request.

## File 00 companion correction

File 00 version 1.2.2 and contract 1.1.1 resolve canonical Founder and WordPress Administrator identity before ordinary non-disciplinary legacy application state while preserving underlying `application_exists` and `application_status` evidence. `rejected`, `suspended`, `appeal_review`, `erasure_pending`, and invalid/corrupt application states remain fail-closed.

## Privacy boundary

The forensic row does not expose a user name, email, phone, application payload, identity document, post content, clinical content, URL, nonce, token, or raw exception detail.

## Acceptance rule

A deployment is not accepted merely because the plugin activates. On staging, `Tools > Composer Health` must show:

- `membership_core` — PASS;
- `create_page` — PASS;
- `public_api_contract` — PASS;
- `current_user_authorization` — PASS;
- `create_surface_diagnostics` — PASS;
- `social_publication_adapter` — PASS.

If `current_user_authorization` fails, all reported codes must be corrected together and the same matrix repeated. File 20 contract status remains separately observable and does not replace native File 21 authorization.

## Release identity

- File 22 package: `0.1.1`
- File 00 minimum plugin: `1.2.2`
- File 00 minimum database schema: `1.2.0`
- File 00 minimum public contract: `1.1.1`
- File 21 runtime/API minimum: `1.0.3`
- Recommended File 21 WordPress package: `1.0.3.1`

## Deployment boundary

Build evidence and automated tests do not authorize direct production deployment. Exact packages must first replace all older copies on staging, then undergo cache, session, mixed-role, Safe Mode, settings, duplicate-copy, rollback, and real Create-surface acceptance before explicit Founder approval.
