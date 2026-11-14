# File 22 — V12.3 focused source corrections: adversarial source review and defect census

**Date:** 2026-11-14. **Scope:** Source-controlled Draft PR #53; no deployed runtime, DB or staging access. This review is an internal technical source review, **NOT** one of the 2 independent reviewer sign-offs required by WP-34 and WP-35.

## Review method and source evidence

- Compared Master Plan V12.3 §File 22, D12, D13 and accepted File 22 work package/test plan to the exact baseline GitHub source `ed672a0d271f10e2cf224951d9f3622a44a70152` and this PR's successive candidate diffs.
- Read `Workflow_Surface`, `Workflow_Coordinator`, `Plan_Completion_Runtime`, `System_Check_Page`, `assets/js/workflow-composer.js`; tracked the WordPress-native File 21 and File 17 repository structures separately.
- Added source-focused PHPUnit contracts and dependency-free Node tests; CI/PR checks run against exact SHA, not the live site.
- Did not merge another PR, change installed source, migrate database, expose upload bytes, create canonical media tables, change patient rights or assume the unapproved D12 feature exists.

## Distinct source issues (do not confuse source patch with live resolution)

| ID | Root-cause reproduction at initial source | Proposed correction in Draft PR #53 | State |
| --- | --- | --- | --- |
| D22-V123-SRC-01 | `upload_panel()` accepted image/video/PDF but no audio; user could not choose audio in File22 UI | Separate native-provider-gated audio picker; bind all pickers | SOURCE_PATCHED, current CI required |
| D22-V123-SRC-02 | Native provider schema has no committed image crop/resize/alt or audio transcript/duration; File22 cannot assert binary processing/scan/revoke | **Not changed**; needs explicit File21/CF04 native owner contract and executable integration tests | OPEN_OWNER |
| D22-V123-SRC-03 | Media picker markup outside `data-supc-step=media`, so Advanced `setStep` did not hide it with other stages | Place picker under Media fieldset; positive/negative unit test | SOURCE_PATCHED |
| D22-V123-SRC-04 | `field_group()` failed to classify audio_duration, alt_text, crop/resize and placed media rights with media fields | Sensitive consent/rights first; media metadata then publication; data-provider regression | SOURCE_PATCHED |
| D22-V123-SRC-05 | Upload coordinator accepted scheme='https' without requiring a hostname, and permitted fragment-bearing signed URL | Require nonempty host, prohibit fragments/userinfo, retain HTTPS and length bound; negative tests | SOURCE_PATCHED |
| D22-V123-SRC-06 | Missing Upload_Token_Adapter resulted in no explicit availability message in the media step | Show accurate provider-unavailable status; no false button or upload success | SOURCE_PATCHED |
| D22-V123-SRC-07 | JS validation grouping handled `required` before medical/privacy/rights; sensitive hold message assigned wrong group | Prioritize authorization, privacy, rights, references, medical and media; dynamic JS test | SOURCE_PATCHED |
| D22-V123-SRC-08 | Upload UI assigned first empty opaque-reference field, including consent/rights/profile | Tokenized exact native media field name+MIME matching; reject sensitive/foreign type; dynamic negative test | SOURCE_PATCHED |
| D22-V123-SRC-09 | Assigning hidden upload reference set `dirty=true` without emitting normal input event; future autosave could be skipped | Dispatch validated `input` after successful typed bind; assert event and no protected-field mutation | SOURCE_PATCHED |
| D22-V123-SRC-10 | Earlier generic binding returned true for unknown MIME if field name contained `media`; auto-link lacked type confidence | Return false for unknown/empty MIME, retain native owner object for explicit linking | SOURCE_PATCHED |
| D22-V123-SRC-11 | `media_upload_status` was classified as publication because generic `status` was evaluated before media | Keep media processing status under Media; publish-specific action remains Publish | SOURCE_PATCHED |
| D22-V123-SRC-12 | File21 `UniversalComposerSubjectSchemaAdapter`, WorkflowAdapter and StructuredAdapter do not implement File22 Upload_Token_Adapter; source social-post provider cannot expose that File22 upload surface | **No speculative File21 patch**; explicit versioned owner implementation and staging acceptance needed | OPEN_CROSS_FILE |

## Additional tracked concerns, not asserted as verified bugs

- **D22-V123-RISK-01:** Whole-file browser `file.arrayBuffer()` for SHA-256 uses memory proportional to file size. File17 separately owns private/encrypted 1GiB resumable transfers. This is an unverified large-file memory/architectural risk; it does not prove File22 itself must accept 1GiB public media. Require bounded-memory/native resumable contract; do not invent provider limits.
- **D22-V123-DOC-01:** Seven visible steps include type selection followed by six in-workspace steps; README clarified, no behavioral bug counted.
- **D22-V123-DOC-02:** The historical compatibility matrix claimed File20 v1.0.0 ZIP lacked the Create 1.0.1 contract and treated this as a standing blocker. The current File20 repository source is v1.4.17 with a source-declared 1.0.1 contract. Updated `docs/COMPATIBILITY-MATRIX.md` to distinguish historical ZIP truth, current repository source and still-unverified deployed contract; no live integration implied.
- **D22-V123-DEC-01:** D12 new private Notes/Documents canonical ownership explicitly requires Founder approval. No new table, migration, private sharing or export has been written.
- Previous self-authored test expected an obsolete `if ($uploads)` branch after stage correction and failed on historical SHA. It was corrected and rerun green at `a935d4549365aacc68cc5e2299e7a3459cae8f7f`; that earlier result is not transferable to newer HEAD.

## Count by evidence grade

| Classification | Number | Interpretation |
| --- | ---: | --- |
| Source issues enumerated | 12 | Eleven File22 authoring/security/UX areas plus the File21 provider dependency; includes native-owner gap |
| Source-level corrections proposed | 10 | SRC-01,03–11; current exact-head CI/independent review still required |
| Source issues not corrected | 2 | SRC-02 full native media and SRC-12 File21 cross-file contract |
| Unconfirmed architectural risk | 1 | RISK-01 bounded-memory transfer |
| Documentation clarifications | 2 | DOC-01 and DOC-02 (historical File20 contract identity) |
| Founder decisions | 1 | D12 |
| New CI tests/source contracts | Several | PHPUnit and JS test files; CI must establish exact latest SHA |
| E2E tests accepted in Staging | 0 | 50-case register exists, not executed |
| Live defects confirmed fixed | 0 | No live inspection or re-test |

## Independent review A — required, not yet performed

Assign independent reviewer identity and scope to exact post-correction SHA: architecture, File00 current subject, authorization/IDOR, REST/SSRF, consent/rights, browser upload target, package provenance, migrations, dynamic memory bounds. Record findings with ID and original evidence, correction SHA, repeated negative test, reviewer acceptance. **Do not count this internal source review as A.**

## Independent review B — required, not yet performed

Independent reviewer conducts browser/RTL/a11y/UX and native-provider failure checks at exact deployed staging candidate, including 320–1440px, 200/400% zoom, keyboard/screen reader, Urdu/Arabic mixed input, interrupted upload and recovery. **Do not count this internal source review as B.**

## Root cause and release integrity rule

- Current `main` source baseline `ed672a0d271f10e2cf224951d9f3622a44a70152`; PR candidate HEAD is whatever GitHub reports after last file update. Keep identities separate.
- No live/staging evidence proves these defects are running. GitHub QA can close only the source-test gate at the tested HEAD; cannot claim the plugin on www.sabrihomeopathy.com has changed.
- Never create a new live patch without exact deployed code/DB/migration parity. Preserve backups; external P0 blockers remain.

Deployed Version: UNVERIFIED. DB Version: UNVERIFIED. Migration State: UNVERIFIED. Live Verification Status: NOT PERFORMED.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**

**Release gate: NO-GO** until final-head CI green, 2 independently signed reviews, approved D12 status, native provider, 50 staging scenarios, backup+rollback, Founder written approval, controlled live deploy and live re-test.
