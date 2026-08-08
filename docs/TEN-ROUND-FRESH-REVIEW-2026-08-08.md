# File 22 — Ten-Round Fresh Review / Fix Record — 2026-08-08

## Governing basis

This review treats the current consolidated central master plan as the higher authority when an older File 22 numbering clause conflicts with it. The canonical boundary used here is: File 21 owns native social/news content and workflow truth; File 22 owns the single role-aware creation/orchestration surface; File 23 owns the Doctor/Founder operational publishing dashboard; File 24 owns cross-platform assurance/governance; File 25 owns public visual/profile projection. File 22 must not create a second canonical content, media, consent, moderation, review, AI or analytics database.

The review also keeps implementation lifecycle claims separate: source completion and automated QA do not imply package parity, staging acceptance, live deployment or operational acceptance.

## Round record

| Round | Focus | Defect / gap found | Immediate correction |
|---|---|---|---|
| 1 | Server trust boundary for AI/provider tools | Browser-controlled `sensitive` flag could be falsified; provider actions were not server allowlisted | Added native-schema/payload sensitivity inspection, action allowlists and fail-closed server policy |
| 2 | Provider-returned rich text/privacy | External image URLs could act as tracking pixels/resource disclosures in private composer sessions | Restricted provider-returned images to same-origin/blob-safe URLs; stripped unsafe sources; no-referrer policy |
| 3 | Encrypted offline recovery | Recovery scope was only user+adapter, had no strict 24h expiry, weak purge/account-switch behavior and could retain a low-risk cache after a draft became sensitive | Added per-draft/user/adapter scope, non-extractable AES-GCM IndexedDB v2, 24h retention, sensitive purge, logout/account cleanup and authenticated restore |
| 4 | Collaboration/conflict application | Remote multiselects were applied incorrectly; authority/sensitive/consent fields could be overwritten; stale remote/conflict state could survive failed refresh | Added protected-field boundary, correct multiselect handling and stale-state clearing |
| 5 | Reviewer annotation lifecycle | Inline annotations could be displayed but not explicitly resolved | Added field-addressable explicit resolve action tied to native review owner |
| 6 | Voice-to-Structured Composer | Dictation targeted rich text only and had no explicit sensitive/browser-speech privacy gate | Added active structured-field dictation, sensitive-default block, owner opt-in filter and vendor-processing disclosure |
| 7 | Media workbench | Browser `accept` alone did not bound decode/memory risk; oversized images and bitmap lifetime were insufficiently controlled | Added JPEG/PNG/WebP checks, 25MB local cap, 12,000px/40MP decode bounds, bitmap release and native-owner handoff |
| 8 | Capability discovery/performance | Base and advanced layers duplicated the same capability GET during bootstrap | Added five-second same-origin GET coalescing only for capability discovery; all mutating invocations remain uncached/re-authorized |
| 9 | Keyboard/accessibility | Command palette fallback lacked complete focus trap/restore/Escape behavior; annotation resolve target was not guaranteed 44px | Added modal labeling, deterministic focus, fallback trap/Escape/restore, visible focus and 44px action targets |
| 10 | Final adversarial ownership/privacy census | Template application could touch authority/sensitive fields; publication-impact action vocabulary could reject valid native adapter actions; legacy recovery could still initialize before v2; filter-only provider guard could theoretically be overwritten by a later provider filter | Added safe template application, native-semantic publication-action validation, disabled legacy recovery at localization, and made privacy/action preflight + final capability reduction direct/non-bypassable in the REST controller |

## Regression evidence added

`tests/TenRoundFreshReviewTest.php` covers all ten correction themes and asserts that the runtime system check includes every new hardening asset. Existing cumulative tests remain authoritative for the established File 22 core and integration contracts.

## Release boundary

This record documents a source-review/correction cycle only. The branch and PR must remain unmerged until explicit Founder authorization. Deterministic package/checksum, Hostinger staging real-role acceptance, deployment smoke tests and operational acceptance remain separate gates.
