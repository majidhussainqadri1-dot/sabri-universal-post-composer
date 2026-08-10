# File 22 — New Plans — Two Fresh Review/Correction Closure — 2026-08-10

## Governing basis

This review applies only to repository/source work for File 22. It uses the newly rewritten File 22 plan and the consolidated central governing plan as scope. Live/staging truth remains separate.

Baseline main HEAD before this batch: `1274e380268c2ab235c66fd21906cf4b1bcadf9a`.

## Fresh review round 1 — defects found and corrected

The first independent review was performed after the initial new-plan implementation rather than relying on green historical tests. It found the following defects/gaps:

1. **Confused-deputy subject override** — the new PHP helpers initially accepted a caller-supplied user ID. Corrected so public governed APIs bind only to `get_current_user_id()`; runtime also rejects subject mismatch.
2. **REST contract drift** — the first implementation unnecessarily changed the existing File 22 REST API marker from `1.1.0` to `1.2.0`, causing the exact reconciliation workflow to fail. Corrected by preserving REST `1.1.0` and versioning the new governance/lifecycle contracts independently.
3. **Lifecycle/create capability coupling** — correction/edit orchestration initially inherited new-create authorization. Corrected so existing-object lifecycle authority is independently revalidated through File 00 edit capability plus the native object's lifecycle decision.
4. **Patient-case governance omission** — the core social governing gate did not require the plan's patient-case safety capability. Corrected.
5. **Weak governance-profile consistency** — notification/search declarations could contradict their policy metadata. Corrected with fail-closed consistency checks.
6. **Unverified native-module identifiers** — the initial optional-adapter catalog guessed plugin/module slugs. Corrected to use only plan-derived adapter keys and canonical owner file numbers; registration-time native contracts remain authoritative.
7. **REST abuse controls** — the new REST surface lacked the existing File 22 request/rate posture. Corrected with authentication, nonce, request-size header checks, per-user rate limiting, bounded command payloads, and rejection of unexpected top-level lifecycle fields.
8. **Regression coverage** — tests were expanded for current-subject binding, lifecycle/create separation, capability revocation, owner-file catalog truth, request/payload controls, patient-case coverage, and no duplicate File 22 content store.

After correction, the exact branch head `5c4beeaf357daa02ff3d9bdad4f44215a3c52eb9` passed both `File 22 CI` and `File 22 Reconciliation 0.3.0`, along with all historical review-evidence workflows triggered for that head.

## Fresh/adversarial review round 2 — corrected source only

A second review was then performed against the corrected source, not the initial patch. The review specifically challenged:

- authorization-subject spoofing;
- File 00 capability revocation between reads and protected actions;
- editor/corrector authority that differs from create authority;
- malformed adapter/reference/idempotency values;
- oversized/nested lifecycle payloads;
- notification/search governance contradictions;
- patient-case safety omission;
- native-reference substitution in lifecycle results;
- unavailable/optional adapters;
- accidental universal post type/table creation;
- File 22 takeover of native search, notification, media, moderation, or permanent content ownership;
- exact REST contract compatibility with the existing reconciliation gate.

No additional unresolved repository/source defect was identified in this changed scope after round-1 corrections. This conclusion is **not** a staging/live acceptance statement; it is a source-review result bounded by the executable repository tests and inspected paths.

## Repository Definition-of-Done status for this coding batch

The changed File 22 source now contains the missing orchestration contracts needed to represent the current plans without violating canonical ownership:

- governed authoring-feature declaration;
- native lifecycle orchestration for edit/revision/correction/scheduling operations;
- current-subject and File 00 reauthorization;
- bounded private REST surface;
- optional adapter coverage diagnostics;
- core social governing gate;
- plan-derived owner mapping;
- regression tests and exact-head reconciliation/package workflow compatibility.

## Explicitly separate gates

This repository closure must not be represented as any of the following without fresh environment evidence:

- Staging-Accepted;
- Live-Deployed;
- Operational;
- deployed-artifact parity confirmed;
- live DB/schema/migration state confirmed;
- optional native adapter packs certified on staging.

Those gates require the actual deployed package, installed companion versions, database/schema state, real-role workflows, browser/accessibility/weak-network checks, backup/restore and rollback rehearsal, and explicit deployment acceptance.
