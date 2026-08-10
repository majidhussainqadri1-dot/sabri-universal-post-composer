# File 22 — New Governing Plans Coding Closure — 2026-08-10

## Scope freeze

Repository: `majidhussainqadri1-dot/sabri-universal-post-composer`

Baseline `main` HEAD: `1274e380268c2ab235c66fd21906cf4b1bcadf9a`

This coding batch reconciles the File 22 repository with the newly rewritten File 22 plan and the consolidated governing central plan. It is repository/source completion work only. It does **not** claim Hostinger staging acceptance, deployed-artifact parity, live database parity, or production operational acceptance.

## Canonical ownership preserved

File 22 remains a role-aware creation and command-orchestration facade. Native owners retain permanent content, moderation state, media, patient consent/evidence, search truth, notification delivery truth, and canonical URLs. No new File 22 post type, universal content database, generic media vault, or duplicate moderation backend is introduced.

## Coding closure delivered

1. Added `Governed_Workflow_Adapter`, an additive contract for the governing-plan metadata that was not represented in the earlier 0.3.0 adapter surface: authoring feature declarations, media rules, edit capability, cleanup policy, native search/indexing policy, and native notification event declarations.
2. Added `Lifecycle_Adapter`, an additive native-owner contract for edit, revision, correction, scheduling, withdrawal, archive, and restore orchestration without moving lifecycle ownership into File 22.
3. Added `Governing_Plan_Runtime` with current File 00 reauthorization, fail-closed capability checks, API-version checks, bounded command payloads, idempotency validation, native-reference binding, controlled errors, no-cache REST responses, and System Check evidence.
4. Added private REST endpoints under `sabri-composer/v1` for governance metadata and lifecycle commands. They require authentication and a valid WordPress REST nonce; native owners remain responsible for object/state/ownership validation.
5. Added public-safe PHP integration helpers: `supc_adapter_governance()`, `supc_lifecycle_capabilities()`, and `supc_execute_lifecycle()`.
6. Added an approved adapter coverage catalog for File 21, Learning, Encyclopedia, Video, Reels, PDF Library, and Marketplace. Missing optional native modules are reported as warnings rather than replaced by fake File 22 backends.
7. Added a production-gate System Check for the `social_publication` adapter. The core publication adapter must explicitly declare the central cross-cutting authoring features required by the current plans and implement the lifecycle contract before File 22 can be treated as fully integrated.
8. Added regression tests for governed metadata, correction lifecycle, capability revocation, native ownership, and prohibition of duplicate File 22 content storage.
9. Extended bootstrap collision protection so another component cannot preclaim the new contracts or public governed API functions silently.

## Cross-cutting authoring features represented by the contract

The governed adapter profile can certify these plan requirements without File 22 becoming their native owner:

- rights and license controls;
- accessibility authoring;
- translation provenance/review;
- corrections;
- revision history;
- scheduling;
- patient-case safety;
- medical safety;
- source/evidence handling;
- preview matrix;
- canonical search projection;
- native notification events.

The native adapter schema and native validation remain responsible for the concrete fields and domain rules. File 22 deliberately does not duplicate those rules into a universal content model.

## Dependency truth after this batch

- File 00 is the central identity/capability authority and remains fail-closed.
- File 20 owns the application shell and global Create placement.
- File 21 owns social/news entities and publication workflow.
- File 19 owns notification projection/delivery.
- Search/discovery remains owned by its canonical platform owner.
- File 23 owns the federated publishing dashboard/projections.
- Optional content domains must register their own certified adapters; absence does not authorize File 22 to create substitute backends.

## Release truth

The repository can become **coded/automated-QA candidate** only after the fresh branch tests are green. The following remain separate gates and cannot be inferred from source code:

- deployed plugin version and checksum;
- live/staging database and schema state;
- companion-adapter versions actually installed on staging;
- real Founder/doctor/suspended-role workflows;
- real media, patient-case, translation, rights, correction and scheduling flows;
- browser/device/RTL/LTR/400% zoom/no-JS/weak-network acceptance;
- backup/restore and rollback rehearsal;
- live smoke test and Founder approval.

No live deployment is authorized by this document.
