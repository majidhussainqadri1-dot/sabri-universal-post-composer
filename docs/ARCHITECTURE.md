# Architecture

## Final role of File 22

Sabri Universal Post Composer is a role-aware, adapter-driven creation facade and workflow orchestrator. It is not a replacement publishing backend and does not own permanent content belonging to companion modules.

## Ownership matrix

| Responsibility | Canonical owner |
|---|---|
| Identity, verification, roles, capabilities, suspension | File 00 — Sabri Membership Core |
| Global header and Create placement | File 20 — Unified Application Shell |
| Social, Founder, doctor, News, Patient Case, Research Summary, and Poll publishing | File 21 — Complete Home and News Feed |
| Universal type selection, shared creation UX, guarded invocation, temporary orchestration boundaries, and adapter health | File 22 |
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

File 00 is a mandatory hard dependency. Activation fails closed unless the required Membership Core version and API are available and the `smc_user_status()` callback originates from the real path declared by `SMC_FILE` and `SMC_PATH`. A version constant plus a same-named foreign callback is not accepted as the authorization authority.

File 20 is a production integration, but File 22 remains safe when the shell is absent. File 21 and all other modules are adapter-specific dependencies. An unavailable adapter is hidden without disabling unrelated healthy adapters.

Dependency versions use strict Semantic Versioning. Prerelease and build metadata may appear together; malformed or leading-zero numeric identifiers are rejected. Build metadata is retained for diagnostics but ignored when compatibility precedence is compared.

## Permission order

1. File 22 Safe Mode and File 20 Safe Mode.
2. Membership Core availability and callback provenance.
3. Account status and suspension decision.
4. Required central WordPress capability.
5. Adapter-specific restriction.

An adapter may restrict access; it may never expand or bypass the central decision. Phase 22E repeats this permission order for every direct workflow operation and never trusts a prior page-render decision.

## Canonical record law

One native record may be projected into Home, News, a profile timeline, search, and a module archive. File 22 must not create duplicate permanent records for those surfaces.

A `Workflow_Adapter` creates, validates, previews, submits, and reports status through its native module. File 22 passes guarded server-side calls but does not persist the native payload, protected evidence, upload bytes, or final record.

## Registration lifecycle

Native modules may call `supc_register_adapter()` after File 22 loads. The compatibility events `supc_register_adapters` and `supc_registry_ready` fire on `init`, but direct registration remains available to late-loading modules.

## Workflow invocation boundary

Phase 22E adds an internal `Workflow_Coordinator` and public server-side PHP helper functions. It validates:

- adapter and workflow-contract availability;
- central and adapter-specific authorization;
- payload shape, nesting, finite values, and encoded size;
- opaque native references;
- immutable idempotency keys;
- schema and operation result envelopes;
- same-origin HTTPS preview and canonical URLs;
- controlled native statuses.

It does not expose a public HTTP controller. Any future REST, AJAX, or form layer must separately enforce nonce, CSRF, method, rate-limit, upload, and request-origin controls.

## Idempotency boundary

File 22 may generate a logical idempotency key and forwards it unchanged. The native owner must durably bind that key to the canonical native object and reconcile retries without duplication. File 22 does not claim durable idempotency merely because it generated the key.

## State dimensions

- Composer session: `new`, `editing`, `autosaved`, `offline_pending`, `conflicted`, `abandoned`, `completed`.
- Review: `not_required`, `draft`, `submitted`, `under_review`, `changes_requested`, `approved`, `rejected`, `withdrawn`.
- Publication: `unpublished`, `scheduled`, `published`, `hidden`, `archived`, `deleted`.
- Safety hold: `clear`, `privacy_hold`, `medical_hold`, `copyright_hold`, `security_hold`, `suspended`.

Corrections and retractions are immutable editorial events, not overloaded composer states.

Phase 22E exposes only controlled native operation statuses: `draft`, `pending_review`, `scheduled`, `published`, `rejected`, and `failed`. Richer review, publication, and safety dimensions remain native-module responsibilities until a separately versioned reconciliation contract is approved.

## Core release boundary

Core 1.0 requires File 00 permission integration, File 20 shell integration, File 21 social adapter, safe drafts, validation, preview routing, audit, accessibility, migration, rollback, and staging acceptance. Future adapters are certified independently when their native modules are ready.
