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
| Final public profile and timeline visual experience | File 25 — Complete Public UI, Profile Timeline and Visual Experience |
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

File 00 is a mandatory hard dependency. Activation fails closed unless the required Membership Core runtime and database versions are compatible, `SMC_FILE` and `SMC_PATH` resolve coherently to the canonical `sabri-membership-core/sabri-membership-core.php` package, and `smc_user_status()` originates from that package directory. A coherent foreign directory with copied constants and a same-named callback is not accepted as the authorization authority.

File 20 is optional when genuinely absent, so File 22 remains safe without the shell. Once any File 20 runtime or contract symbol is claimed, however, the claim must resolve to the canonical `sabri-unified-application-shell/sabri-unified-application-shell.php` package, canonical slug, coherent real paths, valid runtime version, Reflection-owned Safe Mode class, and Reflection-owned Create contract functions. An incomplete, obsolete, colliding, or foreign-source claim fails closed and cannot clear Safe Mode or be invoked by Administrator health checks.

File 21 and all other content modules are adapter-specific dependencies. An unavailable adapter is hidden without disabling unrelated healthy adapters.

Dependency versions use bounded strict Semantic Versioning. Core numbers are compared without integer conversion; prerelease identifiers follow numeric-before-nonnumeric and left-to-right identifier rules; a stable release outranks its prerelease; build metadata is retained for diagnostics but ignored for precedence. Malformed, whitespace-padded, leading-zero, or oversized values fail closed.

## Runtime ownership model

Marker constants establish declared contract identity but do not prove executable ownership. File 22 reflects every public `supc_*` function and requires the declaration source to be exactly `includes/core/functions.php`. File 20 functions and the Safe Mode class must originate inside the verified canonical File 20 package directory. Function names, class names, version strings, and owner markers copied by another plugin never become trusted execution authority.

## Permission order

1. File 22 Safe Mode and any verified File 20 Safe Mode.
2. Canonical Membership Core package, runtime/database compatibility, and callback provenance.
3. Account status and suspension decision.
4. Required central WordPress capability.
5. Adapter-specific restriction.

An adapter may restrict access; it may never expand or bypass the central decision. Every direct workflow and REST mutation repeats this permission order and never trusts a prior page-render decision.

## Canonical record law

One native record may be projected into Home, News, a profile timeline, search, and a module archive. File 22 must not create duplicate permanent records for those surfaces.

A `Workflow_Adapter` creates, validates, previews, submits, and reports status through its native module. File 22 passes guarded server-side calls but does not persist the native payload, protected evidence, upload bytes, or final record.

## Create-page transaction boundary

Create-page mapping repair snapshots the exact prior option state without a magic sentinel. If a new File 22-managed page cannot be mapped, File 22 first attempts permanent deletion and then quarantines the exact inserted record as an empty nonpublic draft. If a published shortcode record cannot be removed or quarantined, File 22 sets its emergency-disable boundary and emits controlled rollback evidence. Existing candidate pages are never deleted or rewritten by this cleanup path.

## Registration lifecycle

Native modules may call `supc_register_adapter()` after File 22 loads. The compatibility events `supc_register_adapters` and `supc_registry_ready` fire on `init`, but direct registration remains available to late-loading modules.

## Workflow and private REST invocation boundary

The current RC3 runtime contains the internal `Workflow_Coordinator`, public server-side PHP helpers, and authenticated private REST controllers. They validate:

- adapter and workflow-contract availability;
- current-subject identity and central plus adapter-specific authorization;
- REST nonce and method-specific permission checks;
- bounded request size and rate limits;
- payload shape, nesting, finite values, and encoded size;
- opaque native references and upload tokens;
- immutable idempotency keys;
- schema and operation result envelopes;
- same-origin HTTPS preview and canonical URLs;
- controlled native statuses;
- private no-store/noindex response boundaries.

The REST layer is not a public-content API: draft/session/reviewer/consent data remains private and every mutating request is reauthorized server-side. Any future AJAX or form compatibility layer must preserve the same CSRF, authorization, rate-limit, upload and origin boundaries.

## Idempotency boundary

File 22 may generate a logical idempotency key and forwards it unchanged. The native owner must durably bind that key to the canonical native object and reconcile retries without duplication. File 22 does not claim durable idempotency merely because it generated the key.

## State dimensions

- Composer session: `new`, `editing`, `autosaved`, `offline_pending`, `conflicted`, `abandoned`, `completed`.
- Review: `not_required`, `draft`, `submitted`, `under_review`, `changes_requested`, `approved`, `rejected`, `withdrawn`.
- Publication: `unpublished`, `scheduled`, `published`, `hidden`, `archived`, `deleted`.
- Safety hold: `clear`, `privacy_hold`, `medical_hold`, `copyright_hold`, `security_hold`, `suspended`.

Corrections and retractions are immutable editorial events, not overloaded composer states.

The governance/lifecycle contracts expose normalized native operation statuses while richer review, publication and safety truth remains with the native domain owner.

## Core release boundary

Core 1.0 requires File 00 permission integration, File 20 shell integration, File 21 social adapter, safe drafts, validation, preview routing, audit, accessibility, migration, rollback, and staging acceptance. Future adapters are certified independently when their native modules are ready.
