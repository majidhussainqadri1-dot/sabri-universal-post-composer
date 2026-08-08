# File 22 — Third Independent Ten-Round Fresh Review

Date: 2026-08-09 (PKT)

This record documents a third independent ten-round adversarial review of the File-22-side Future Composer Intelligence layer. Every discovered defect was corrected in the same cycle before proceeding. File 22 remains an orchestration facade; canonical content, review, privacy, publication, moderation and media truth remain with their native owners.

## Round record

| Round | Focus | Finding | Immediate correction |
|---|---|---|---|
| 1 | Provider egress minimization and remote application | Defect found: collaboration/conflict/diff/impact browser payloads could include authority, consent, privacy, moderation, sensitive or opaque fields; remote collaboration application was too broad | Added a post-hardening layer that replaces the affected browser actions with minimized provider snapshots and bounded, allowlisted remote application. Commits `c87744aaee56c18d6e8b904efeea0368dc4ce280`, `a64aa60b301f3585634d652bada09f77904d9ecb`. |
| 2 | Encrypted offline recovery content eligibility | Defect found: low-risk eligibility was based mainly on schema/field shape, so PII typed into otherwise ordinary fields could enter encrypted IndexedDB recovery | Added content-level sensitive/PII detection before persistence, after decryption and before restore; ineligible recovery is purged and online authoritative save is required. Commit `247c518a5f142c885b48bdfc5f1a0c7f0c3907ed`. |
| 3 | Reviewer annotation resolution truth | Defect found: any HTTP 2xx response could remove the annotation marker and claim resolution without a positive native-owner confirmation | Require provider result to explicitly confirm `resolved=true` or `status=resolved` (and matching annotation id when supplied) before UI removal. Commit `78a59356b3c1f67a68ecc96cdf1b8d10e845d644`. |
| 4 | Voice privacy authorization freshness | Defect found: sensitive-workflow speech-service authorization was checked when dictation started but not revalidated before later transcript insertions | Revalidate immediately before every final transcript insertion; if the workflow has become sensitive without owner opt-in, stop dictation and discard that insertion. Commit `521b49cf6b10f4dbad4692cdad9e285226feb45d`. |
| 5 | Command palette fallback/re-entry | Defect found: the base command palette could call missing `dialog.close()` in fallback browsers and could throw on re-entrant native `showModal()` | Added safe close shim, guarded open, re-entry handling, captured Ctrl/Cmd+K and a replaced guarded launcher while preserving focus restoration/trap behavior. Commit `c40e14ead2e43282a759bf72397e9dd0550f8fe6`. |
| 6 | Sensitive reviewer-annotation provider boundary | Defect found: `review_annotations` was not included in the server-sensitive external-advisory reduction | Added reviewer annotations to the server-side sensitive provider gate so patient/sensitive workflows require exact governing-owner opt-in. Commit `b29df25789d24dfecceebfc620ec1db552325330`. |
| 7 | Failed autosave recovery retention | Defect found: `/saved|completed/i` also matched `Not saved`, so a failed autosave could purge the local encrypted recovery record | Changed the purge gate to exact normalized success states only: `Saved` or `Completed`. Commit `af05328f5886c77dd176f694a0aa91a62a301489`. |
| 8 | Legacy browser-recovery retirement | Defect found: the disabled v1 IndexedDB recovery database could remain on the device indefinitely after the v2 policy transition | Added explicit best-effort retirement of `supc-future-recovery-v1`, never reads/reuses it, reports blocked deletion without weakening v2, and added the retirement asset to runtime/system-check inventory. Commits `138839df8a2e229c30d38d5d998fdb5c1d85bf1b`, `761aa5154337384799f29679b6c4d46fdcc3e910`. |
| 9 | Human rich-text paste privacy | Defect found: the older normal paste sanitizer allowed external HTTP(S) image sources, which could load a tracking/resource URL inside a private Composer session | Added capture-phase rich-text paste sanitization that preserves HTTP(S) links but permits image resource loads only from same-origin/blob-safe sources before the older paste handler runs. Commit `6e61e8f2f25f8c9cdd553c8b84ed58f38f7f2eb3`. |
| 10 | Fresh regression census and exact-head automated QA | No new source defect found. The new regression census passed on its first exact-head run | Added `tests/ThirdTenRoundFreshReviewTest.php`. On exact head `ef4400bf30dc60ef9fd44b5d3eaba7d4132d4fe7`, File 22 CI run 519 passed with 224 PHPUnit tests / 1277 assertions, plus PHPCS, PHPStan, PHP 8.1/8.2/8.3 syntax, dependency lock, repository contract and all existing isolated contracts. All triggered evidence workflows also completed successfully. |

## Defect count

Defects were found in rounds **1, 2, 3, 4, 5, 6, 7, 8 and 9**.

No new source defect was found in round **10** after the prior corrections; its fresh regression census and exact-head automated QA were green.

## Completion boundary

This is source-side review and automated-QA evidence only. It does not claim deterministic production package parity, Hostinger staging acceptance, live deployment or operational acceptance. Those remain separate release gates.

PR #26 remains Draft/Open/Unmerged. Do not merge without explicit Founder authorization.
