# File 22 — New Plans — Fresh Review/Correction Closure — 2026-08-10

## Governing basis

This review applies only to repository/source work for File 22. It uses the newly rewritten File 22 plan and the consolidated central governing plan as scope. Live/staging truth remains separate.

Baseline main HEAD before the governing-plan coding batch: `1274e380268c2ab235c66fd21906cf4b1bcadf9a`.

## Fresh review round 1 — defects found and corrected

The first independent review was performed after the initial new-plan implementation rather than relying on green historical tests. It found and corrected:

1. **Confused-deputy subject override** — public governed PHP helpers initially accepted a caller-supplied user ID. Public APIs now bind only to `get_current_user_id()` and runtime rejects subject mismatch.
2. **REST contract drift** — the first implementation unnecessarily changed the existing REST marker from `1.1.0` to `1.2.0`. Exact reconciliation QA caught it; REST `1.1.0` was preserved and the new governance/lifecycle contracts were independently versioned.
3. **Lifecycle/create capability coupling** — correction/edit initially inherited new-create authority. Existing-object lifecycle now revalidates File 00 edit capability plus the native owner's current lifecycle decision independently.
4. **Patient Case governance omission** — `patient_case_safety` was added to the core social governing gate.
5. **Weak governance-profile consistency** — contradictory notification/search declarations now fail closed.
6. **Unverified native-module identifiers** — guessed provider slugs were removed; the catalog uses plan-derived adapter keys and canonical owner file numbers while runtime registration remains authoritative.
7. **REST abuse controls** — authentication, nonce, request-size checks, per-user rate limiting, bounded command payloads, no-cache/noindex responses, and unexpected top-level-field rejection were added.
8. **Regression coverage** — tests were expanded for current-subject binding, lifecycle/create separation, capability revocation, owner-file catalog truth, request/payload controls, Patient Case coverage, and prohibition of duplicate File 22 permanent storage.

The corrected implementation head passed the full File 22 CI and exact reconciliation workflow before merge.

## Fresh/adversarial review round 2 — corrected source only

A separate review was performed against corrected source, specifically challenging subject spoofing, File 00 revocation, edit/create authority differences, malformed adapter/reference/idempotency values, oversized/nested payloads, notification/search contradictions, Patient Case omission, native-reference substitution, optional-provider failure, accidental universal post type/table creation, ownership takeover, and REST compatibility.

No additional unresolved behavioral/source defect was identified in that changed scope after round-1 corrections. That finding was bounded to repository source and automated evidence; it was not a staging/live statement.

## Post-merge exact-main review — release-integrity defect found and corrected

After PR #27 was merged, exact `main` was reviewed again rather than treating the successful merge and CI as terminal evidence. One additional **release-integrity defect class** was found:

- materially changed governing-plan source still declared software version `0.3.0`;
- historical and newly built `0.3.0` artifacts could therefore represent different source trees and checksums;
- README/readme/manifest still contained stale 0.3.0/Draft-PR wording;
- the deterministic package workflow itself still had a 0.3.0 identity.

This did not invalidate the governed runtime behavior already tested, but it violated exact-source/package traceability. The correction is a distinct pre-staging software candidate **`0.4.0`**, while database schema remains **`0.3.0`** because this batch introduces no new schema migration. The plan-reserved final `1.0.0` identity remains unused until full staging Definition of Done and release approval.

The release-integrity closure also updates README, WordPress readme, changelog, manifest, deterministic packaging workflow, and dedicated release-identity tests so version/package/documentation drift becomes executable QA rather than prose convention.

## Repository Definition-of-Done status for this coding batch

The File 22 source contains the missing orchestration contracts needed to represent the current plans without violating canonical ownership:

- governed authoring-feature declarations;
- native lifecycle orchestration for edit/revision/correction/scheduling operations;
- current-subject and File 00 reauthorization;
- bounded private REST surface;
- optional adapter coverage diagnostics;
- core social governing gate;
- plan-derived owner mapping;
- regression tests;
- unique candidate/version identity and exact-head deterministic package evidence.

## Explicitly separate gates

Repository closure must not be represented as Staging-Accepted, Live-Deployed, Operational, deployed-artifact parity confirmed, live DB/schema/migration confirmed, or optional native adapter packs staging-certified without fresh environment evidence.

Those gates require the actual deployed package, installed companion versions, database/schema state, real-role/native-domain workflows, browser/accessibility/weak-network checks, backup/restore and rollback rehearsal, and explicit deployment acceptance.
