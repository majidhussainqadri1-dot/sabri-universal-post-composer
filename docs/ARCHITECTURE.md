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
| Reels | File 11 through the File 10 video contract |
| Secure PDF storage and document records | File 12 |
| Marketplace records and verified seller-contact policy | File 18 |
| Notification delivery | File 19 |
| Private clinical records | Future Clinical Records module |
| Patient consent evidence | Dedicated native privacy owner |

## Dependency model

File 00 is a mandatory hard dependency. Activation fails closed if the required Membership Core version or API is unavailable.

File 20 is a production integration, but File 22 remains safe when the shell is absent. File 21 and all other modules are adapter-specific dependencies. An unavailable adapter is hidden without disabling unrelated healthy adapters.

## Permission order

1. File 22 Safe Mode and File 20 Safe Mode.
2. Membership Core availability.
3. Account status and suspension decision.
4. Required central WordPress capability.
5. Adapter-specific restriction.

An adapter may restrict access; it may never expand or bypass the central decision.

## Canonical record law

One native record may be projected into Home, News, a profile timeline, search, and a module archive. File 22 must not create duplicate permanent records for those surfaces.

## Registration lifecycle

Native modules may call `supc_register_adapter()` after File 22 loads. The compatibility events `supc_register_adapters` and `supc_registry_ready` fire on `init`, but direct registration remains available to late-loading modules.

## State dimensions

- Composer session: `new`, `editing`, `autosaved`, `offline_pending`, `conflicted`, `abandoned`, `completed`.
- Review: `not_required`, `draft`, `submitted`, `under_review`, `changes_requested`, `approved`, `rejected`, `withdrawn`.
- Publication: `unpublished`, `scheduled`, `published`, `hidden`, `archived`, `deleted`.
- Safety hold: `clear`, `privacy_hold`, `medical_hold`, `copyright_hold`, `security_hold`, `suspended`.

Corrections and retractions are immutable editorial events, not overloaded composer states.

## Core release boundary

Core 1.0 requires File 00 permission integration, File 20 shell integration, File 21 social adapter, safe drafts, validation, preview routing, audit, accessibility, migration, rollback, and staging acceptance. Future adapters are certified independently when their native modules are ready.
