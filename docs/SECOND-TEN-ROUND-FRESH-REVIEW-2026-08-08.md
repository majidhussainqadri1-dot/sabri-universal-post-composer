# File 22 — Second Independent Ten-Round Fresh Review

Date: 2026-08-08

This record documents a second, independent ten-round adversarial review of the File-22-side Future Composer Intelligence layer. It follows the governing File 22 rule that a discovered defect is not closed until corrected and covered by regression evidence. File 22 remains an orchestration facade; native owners retain canonical content, media, review, privacy, publication and moderation truth.

## Round record

| Round | Focus | Finding | Immediate correction |
|---|---|---|---|
| 1 | Encrypted recovery isolation and restore ordering | Defects found: recovery key scope could change after the native session/reference appeared; authority/opaque fields could enter local recovery; restored rich text could be rehydrated after the sanitizer event | Frozen page recovery scope, protected authority/opaque/consent-like fields from recovery, and routed restored rich text through the existing safety sanitizer before stable input handling. Commit `caf3a992d5c711f0e76e733910bea9a36bcfab39`. |
| 2 | Stateful provider IDOR/session boundary | Defect found: collaboration/review/diff/conflict calls could omit a session and therefore skip owned-session matching | Added mandatory session binding for stateful capabilities and retained owned-user/adapter mismatch rejection. Commit `cc36c36216d3048a5a33c202974f87f6cfe46484`. |
| 3 | Sensitive provider egress | Defect found: several content-bearing provider capabilities were outside the sensitive-workflow default block | Expanded server-side sensitive default-block/owner-opt-in enforcement to collaboration, semantic diff, conflict merge, templates and publication impact in addition to AI/terminology/derivatives. Commit `0cba922c94d5d1f64572def270c779d679c5bcfc`. |
| 4 | Publication-impact action authorization | Defect found: a broad action-name substring check could accept provider-impact verbs not actually owned by the current native schema | Replaced semantic-substring authorization with exact current native `publication_action` schema-choice authorization. Commit `edadc42d5fcf64d2fff504d1420dd0cba2d28a1b`. |
| 5 | Provider response resource bounds | Defect found: failed JSON encoding could cast to an empty string and nested response structure lacked explicit depth/item/string bounds | Added encoding failure rejection plus bounded response recursion and strengthened bounded request encoding checks. Commit `7338c3ebdedcf0a40b10eaeab22fc6a1a0b8782e`. |
| 6 | Rate-limit availability | Defect found: read-only capability discovery consumed the same mutation invocation budget | Kept authentication/nonce/eligibility on discovery but charged the mutation rate budget only to `/future/invoke`. Commit `dd3c7c024b6f8df1e5610d39bb989d99fd6d0bbc`. |
| 7 | Capability discovery cache authorization context | Defect found: the short browser coalescing cache was URL-only and could retain non-success authorization/provider results | Bound cache keys to URL + REST nonce + credentials mode, bypassed cache when nonce is absent, and evicted non-OK responses. Commit `4c133d04d9ff903b2773d821d98225644d950dc2`. |
| 8 | Accessibility and keyboard/focus behavior | No new source defect found | Existing modal labeling, focus trap/restore, Escape handling, 44px controls, visible focus, reduced-motion and forced-colors protections retained. |
| 9 | Canonical ownership and duplicate-persistence boundary | No new source defect found | No File-22-side competing content/review/media domain store was introduced; the future bridge remains ephemeral and native owners remain authoritative. |
| 10 | Regression census and exact-head automated QA | Regression coverage added; exact-head CI outcome is the release evidence, not this document | Added `tests/SecondTenRoundFreshReviewTest.php` covering all second-cycle findings and retained the rule that any exact-head CI failure must be corrected before this cycle can be called green. |

## Completion boundary

This review record is source-side evidence only. It does not claim deterministic package parity, Hostinger staging acceptance, live deployment or operational acceptance. Those are separate release gates.

PR #26 remains Draft/Open/Unmerged. Do not merge without explicit Founder authorization.
