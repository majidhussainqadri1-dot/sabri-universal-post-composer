# Architecture

## Final role of File 22

Sabri Universal Post Composer is a role-aware, adapter-driven creation facade and workflow orchestrator. It is not a replacement publishing backend and does not own permanent content belonging to companion modules.

## Ownership matrix

| Responsibility | Canonical owner |
|---|---|
| Identity, verification, roles, capabilities, suspension | File 00 — Sabri Membership Core |
| Global header and Create placement | File 20 — Unified Application Shell |
| Social, Founder, doctor, News, Patient Case, Research Summary, and Poll publishing | File 21 — Complete Home and News Feed |
| Universal type selection, shared creation UX, orchestration, and adapter health | File 22 |
| Final public profile and timeline visual experience | File 23 |
| Learning lessons | File 05 |
| Encyclopedia entries | File 06 |
| Video records | File 10 |
| Reels | File 11 using the File 10 video object contract |
| Secure PDF storage and document records | File 12 |
| Marketplace records and seller-contact policy | File 18 |
| Notification delivery | File 19 |
| Private clinical records | Future Clinical Records module |
| Patient consent evidence | Dedicated native privacy owner; never the generic composer upload table |

## Dependency model

### Hard dependency

File 00 is the central identity and permission authority. A companion plugin must not bypass it.

### Production integration

File 20 is expected for the global Create entry point. The composer must continue to fail safely if the shell is unavailable.

### Adapter-specific dependencies

Each adapter declares its own native module and minimum supported version. An unavailable adapter is hidden without disabling other healthy content types.

## Canonical record law

One native record may be projected into Home, News, a profile timeline, search, and a module archive. File 22 must not create duplicate permanent records for these surfaces.

## Runtime lifecycle

1. WordPress loads File 22 safely.
2. Native modules register adapters on `supc_register_adapters`.
3. The registry exposes only available adapters authorized for the current user.
4. File 20 resolves the Create URL and visibility through official filters.
5. File 22 delegates draft creation, validation, submission, storage, moderation, and canonical routing to the native owner.
6. Notifications are emitted through native or File 19 integration after an idempotent native result exists.

## State dimensions

A future implementation must keep these dimensions separate:

- Composer session: `new`, `editing`, `autosaved`, `offline_pending`, `conflicted`, `abandoned`, `completed`.
- Review: `not_required`, `draft`, `submitted`, `under_review`, `changes_requested`, `approved`, `rejected`, `withdrawn`.
- Publication: `unpublished`, `scheduled`, `published`, `hidden`, `archived`, `deleted`.
- Safety hold: `clear`, `privacy_hold`, `medical_hold`, `copyright_hold`, `security_hold`, `suspended`.

Corrections and retractions are immutable editorial events, not overloaded composer states.

## Release model

Core 1.0 is not blocked by unavailable future modules. Core release requires the registry, File 00 permission integration, File 20 shell integration, File 21 social adapter, safe drafts, validation, preview routing, audit, accessibility, migration, and rollback. Other adapters are certified independently when their native modules are ready.
