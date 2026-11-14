# File 22 — V12.3: 50-case execution ledger and evidence contract

**Recorded:** 2026-11-14. **Source of exact IDs and objectives:** Founder-supplied `Sabri-File22-V12.3-Complete-Implementation-Plan-2026-11-14.docx`, §6. This register is an execution aid; it does not substitute for V12.3 native QA/UX IDs.

## Immutable audit rule

Do not mark a case PASS unless a real environment, exact deployed plugin/build checksum, role, steps, expected and actual results, screenshots/log receipts, tester, UTC date, and a clear PASS/FAIL disposition were recorded. A green source PHPUnit is not a staging or live E2E PASS. No credentials, patient data or raw secret-bearing logs in receipts.

**Current staging accepted results: 0. Current live accepted results: 0.** All 50 cases currently have no execution receipts. 41–43 are decision-blocked while D12 is unapproved. Case 26–30 additionally need File 21/17/CF04 native media contracts and scan/rights evidence.

| Case | Required observable outcome | Primary execution owner | Gate | Status |
| --- | --- | --- | --- | --- |
| T22-01 | Founder can Create and publish only when currently eligible | File 00 + File 21/22 + File 24 | G10 | STAGING_UNVERIFIED |
| T22-02 | Ordinary administrator without explicit delegation cannot directly publish | File 00 + File 21/22 + File 24 | G10 | STAGING_UNVERIFIED |
| T22-03 | Verified active doctor publishes within current scope | File 00 + File 21/22 + File 24 | G10 | STAGING_UNVERIFIED |
| T22-04 | Expired doctor verification blocks publish immediately | File 00 + File 21/22 + File 24 | G10 | STAGING_UNVERIFIED |
| T22-05 | Delegated editor loses action after expiration or revocation | File 00 + File 21/22 + File 24 | G10 | STAGING_UNVERIFIED |
| T22-06 | Student/patient may submit only when allowed; cannot bypass review | File 00 + File 21/22 + File 24 | G10 | STAGING_UNVERIFIED |
| T22-07 | Suspended File 00 subject cannot reuse prior session | File 00 + File 21/22 + File 24 | G10 | STAGING_UNVERIFIED |
| T22-08 | Foreign user/subject ID adapter action denied | File 00 + File 21/22 + File 24 | G10 | STAGING_UNVERIFIED |
| T22-09 | Account context switch clears stale private previews | File 00 + File 21/22 + File 24 | G10 | STAGING_UNVERIFIED |
| T22-10 | Role/capability change is rechecked server-side | File 00 + File 21/22 + File 24 | G10 | STAGING_UNVERIFIED |
| T22-11 | Native draft creation and owned resume | File 21/22 + File 24 | G10 | STAGING_UNVERIFIED |
| T22-12 | Autosave never claims success before native ACK | File 21/22 + File 24 | G10 | STAGING_UNVERIFIED |
| T22-13 | Only permitted nonsensitive drafts have encrypted offline recovery | File 21/22 + File 24 | G10 | STAGING_UNVERIFIED |
| T22-14 | Sensitive drafts do not persist in browser | File 21/22 + File 24 | G10 | STAGING_UNVERIFIED |
| T22-15 | Stale edit conflict preserves original and local text | File 21/22 + File 24 | G10 | STAGING_UNVERIFIED |
| T22-16 | Double click/idempotent key causes one native mutation | File 21/22 + File 24 | G10 | STAGING_UNVERIFIED |
| T22-17 | Replayed key with changed body is rejected | File 21/22 + File 24 | G10 | STAGING_UNVERIFIED |
| T22-18 | Concurrent publish/retry has no duplicate native post | File 21/22 + File 24 | G10 | STAGING_UNVERIFIED |
| T22-19 | Unknown native outcome leads to safe reconciliation | File 21/22 + File 24 | G10 | STAGING_UNVERIFIED |
| T22-20 | Abandoned/expired sessions honor bounded retention | File 21/22 + File 24 | G10 | STAGING_UNVERIFIED |
| T22-21 | Private preview token is signed and bound to current subject | File 21/22 + File 24 | G10 | STAGING_UNVERIFIED |
| T22-22 | Forged, expired, cross-host preview link denied | File 21/22 + File 24 | G10 | STAGING_UNVERIFIED |
| T22-23 | Pending-review user gets Submit, not direct Publish | File 21/22 + File 24 | G10 | STAGING_UNVERIFIED |
| T22-24 | Scheduled execution rechecks revoked authority and active hold | File 21/22 + File 24 | G10 | STAGING_UNVERIFIED |
| T22-25 | Correction/retraction reflected by native canonical owner | File 21/22 + File 24 | G10 | STAGING_UNVERIFIED |
| T22-26 | Image upload, alt, crop, resize and safe native preview | File 17/21/22/24 + CF04 | G10 | STAGING_UNVERIFIED |
| T22-27 | Audio duration/transcript/rights fields are validated by owner | File 17/21/22/24 + CF04 | G10 | STAGING_UNVERIFIED |
| T22-28 | No public URL before quarantine/scan clearance | File 17/21/22/24 + CF04 | G10 | STAGING_UNVERIFIED |
| T22-29 | Interrupted/resumable transfer is recoverable without duplicate | File 17/21/22/24 + CF04 | G10 | STAGING_UNVERIFIED |
| T22-30 | Revoke/delete prevents signed derivative and cache reuse | File 17/21/22/24 + CF04 | G10 | STAGING_UNVERIFIED |
| T22-31 | Patient Case consent, identifiers and anonymization enforced | File 16/21/22/24/26 | G10 | STAGING_UNVERIFIED |
| T22-32 | Unsafe medical citations/schemes rejected; evidence provenance checked | File 16/21/22/24/26 | G10 | STAGING_UNVERIFIED |
| T22-33 | Copyright expiry and review hold block Publish | File 16/21/22/24/26 | G10 | STAGING_UNVERIFIED |
| T22-34 | Missing native provider is unavailable, not a false success | File 16/21/22/24/26 | G10 | STAGING_UNVERIFIED |
| T22-35 | Search/notification events contain authorized metadata only | File 16/21/22/24/26 | G10 | STAGING_UNVERIFIED |
| T22-36 | Urdu RTL editor, preview and ASCII digits stable | File 22/25/24 | G10 | STAGING_UNVERIFIED |
| T22-37 | Arabic RTL and English LTR keyboard focus and reading order | File 22/25/24 | G10 | STAGING_UNVERIFIED |
| T22-38 | 320/390/768/1024/1440 px plus 200/400% zoom/reflow | File 22/25/24 | G10 | STAGING_UNVERIFIED |
| T22-39 | Keyboard/screen-reader notices, labels, errors and focus | File 22/25/24 | G10 | STAGING_UNVERIFIED |
| T22-40 | Reduced motion, dark/forced colors, weak network | File 22/25/24 | G10 | STAGING_UNVERIFIED |
| T22-41 | D12: private note create/version/restore | Founder + File 22/23/24 | G6/G10 | DECISION_BLOCKED |
| T22-42 | D12: sharing, revocation and foreign-user isolation | Founder + File 22/23/24 | G6/G10 | DECISION_BLOCKED |
| T22-43 | D12: DOCX/PDF export privacy and content fidelity | Founder + File 22/23/24 | G6/G10 | DECISION_BLOCKED |
| T22-44 | Backup restoration returns DB/options/files to verified snapshot | Operations + File01B/00/20/21/22/24 | G10 | STAGING_UNVERIFIED |
| T22-45 | Upgrade/migration rerun idempotent and rollback viable | Operations + File01B/00/20/21/22/24 | G10 | STAGING_UNVERIFIED |
| T22-46 | Exact source, package and deployed SHA/checksum parity | Operations + File01B/00/20/21/22/24 | G10 | DEPLOY_PARITY_UNVERIFIED |
| T22-47 | File 20 create/auto/doctors routes agree with final policy | Operations + File01B/00/20/21/22/24 | G10 | STAGING_UNVERIFIED |
| T22-48 | File 21 real native publishing workflow with actual roles | Operations + File01B/00/20/21/22/24 | G10 | STAGING_UNVERIFIED |
| T22-49 | Live limited rollout smoke, errors and alerts | Founder + Operations + File 24 | G11/G12 | LIVE_UNVERIFIED |
| T22-50 | Zero known active defects, optional module failure safe | Founder + Operations + File 24 | G11/G12 | LIVE_UNVERIFIED |

## Required tester evidence per case

1. Exact plan requirement ID and selected native adapter contract version; source SHA, ZIP SHA-256, staging or live deployed checksum; active File00/20/21/22 dependency versions and DB/migration fingerprint.
2. Persona: only non-production synthetic user IDs/roles and relevant effective capabilities (Founder/verified doctor/delegated editor/student/patient/unauthorized); no personal identity document export.
3. Test setup and numbered actions, including adverse path, two concurrent tabs/sessions, network type, devices, viewport, language, private/public content class and toggles where relevant.
4. Expected result; actual result; non-sensitive screenshot, redacted log or coded response; error/hold codes; evidence artifact owner and reviewer.
5. PASS/FAIL/BLOCKED/N-A disposition. N-A allowed only under a signed Founder determination tied to exact ID and scope, never as implicit PASS.
6. For a FAIL: defect ID, affected exact build, native-owner source of truth, reproduction, root cause, patch SHA, negative regression, CI, staging re-run, and eventual live re-test.

## Cross-file prerequisites

- File 00: live eligibility/verification/delegation assertions and current-subject binding.
- File 20: shell Create routing ownership and compatible Create contract.
- File 21: native social-publication owner; File 21 source currently does not declare File 22 Upload_Token_Adapter, so media T22-26–30 cannot be accepted from File 22 chooser alone.
- File 17 + CF04: resumable transport, media object/scanning, rights, derivatives and revoke, once explicitly active and contracted.
- File 24: assurance, independent privacy/security review, backup/rollback receipts.
- Founder: D12 explicit Approve/Defer; final production authorization.

## Outcome counts (at creation)

- STAGING_UNVERIFIED: **44**
- DECISION_BLOCKED: **3**
- DEPLOY_PARITY_UNVERIFIED: **1**
- LIVE_UNVERIFIED: **2**
- SOURCE_ONLY_TESTS: kept separately; do not sum into E2E PASS.

## Release matrix

- Repository baseline: `ed672a0d271f10e2cf224951d9f3622a44a70152`; PR HEAD: inspect GitHub live when running.
- Deployed Version: UNVERIFIED.
- DB Version: UNVERIFIED.
- Migration State: UNVERIFIED.
- Live Verification Status: NOT PERFORMED.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
