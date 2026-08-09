# File 22 — Future Composer Intelligence Superset (18 Enhancements)

Status: File-22-side source implementation candidate under exact-head automated QA. This document records the approved future additions without changing canonical ownership in the governing central plan or the File 22 harmonized plan.

## Governing law

File 22 remains the one role-aware create/edit/draft/validate/preview/submit/revision orchestration surface. Native modules remain permanent owners of canonical content, media bytes, moderation truth, patient-consent evidence, annotations, AI domain truth, distribution truth and publication state. The Future Composer Intelligence layer is advisory or pass-through orchestration only.

## Implemented enhancements

1. **AI Composer Copilot Bridge** — explicit human-triggered outline/rewrite/summary/title/citation assistance through an authorized provider; no silent insertion or auto-publication. Oversized provider payloads fail closed instead of being silently truncated.
2. **Evidence Graph & Citation Heatmap** — local claim/reference analysis with uncited-claim sampling.
3. **Medical Terminology Intelligence** — authorized-provider terminology guidance; not diagnosis, prescription, potency, dosage or emergency authority; oversized advisory payloads fail closed.
4. **Patient Privacy De-identification Assistant** — local advisory detection for email/phone, CNIC/national-ID, passport-like values, medical-record identifiers, DOB shapes, GPS coordinate shapes and explicit-address labels without sending the draft to a third party. Native Patient Case privacy/consent remains authoritative.
5. **Voice-to-Structured Composer** — browser speech recognition into the selected permitted text field or rich editor. Target type, field length and current sensitivity are rechecked immediately before insertion; no partial over-limit transcript is inserted.
6. **Real-Time Collaborative Drafting** — provider-backed presence/cursor metadata and collaboration bridge; File 22 does not create a competing draft body store. Provider join requires explicit user opening/intent; remote snapshots are complete-or-rejected rather than silently truncated.
7. **Inline Reviewer Annotation Layer** — provider-owned annotation/read/change-request bridge with exact annotation-identity confirmation before a marker is removed.
8. **Semantic Revision Diff** — provider-owned meaning-level comparison of current and authoritative revisions using a complete bounded snapshot; oversized drafts are not partially compared.
9. **Conflict Merge Studio** — provider bridge for conflict inspection/resolution instead of blind overwrite. Resolution requires a bounded conflict token and the browser keeps the conflict open unless the native owner positively confirms the exact token/resolution.
10. **Governed Template & Block Library** — provider-backed approved templates; application requires an explicit human action, protects authority/privacy/identity fields, uses a complete bounded current snapshot and applies only after whole-envelope validation.
11. **Command Palette / Slash Commands** — Ctrl/Cmd+K keyboard palette plus literal slash-command access for save, validation, preview, navigation and focus actions.
12. **Adaptive Composer Mode** — risk/device-aware Quick-vs-Advanced mode selection, with full controls forced for risk/compliance-shaped drafts.
13. **Accessibility Coach** — local checks for missing alt text, vague links, heading skips/empties and tables without headers; command-dialog focus/re-entry/fallback behavior is hardened separately.
14. **Advanced Media Workbench Bridge** — bounded local JPEG/PNG/WebP rotate/center-square crop, then direct hand-off into the native upload-token workflow; File 22 never becomes the media vault.
15. **Cross-Format Derivative Studio** — explicit provider-backed summary/Reel/video/lesson/social derivative generation; never auto-published and never silently generated from a truncated source body.
16. **Explainable Content Readiness Score** — advisory score from completeness, evidence, privacy, accessibility, safety and metadata; not a ranking, moderation or publication permission decision.
17. **Publication Impact Simulator** — authoritative-provider bridge for audience/destination/notification/search impact. The proposed action must exist in the current native publication-action schema and no distribution truth is written by File 22.
18. **Encrypted Offline Recovery for Low-Risk Drafts** — active v3 IndexedDB AES-GCM recovery with a non-extractable CryptoKey, 24-hour ceiling, user/adapter/tab-session lineage, session aliasing, current-schema fingerprint, PII/content screening and atomic two-phase restore. Sensitive/patient/consent/identity-shaped workflows remain ineligible. Plaintext draft bodies are not written to localStorage or sessionStorage. Retired v1/v2 recovery databases are never imported or reused.

## Capability contract

`Future_Capability_Adapter` exposes provider capabilities and bounded invocations. Cross-file owners such as File 16, File 23 or native content modules can also register through:

- `supc_future_capabilities`
- `supc_future_capability_result`
- `supc_future_sensitive_capability_allowed`
- `supc_future_encrypted_recovery_allowed`

The bridge allowlist is fixed to ten provider capabilities. Eight further capabilities are local-only, producing an exact 18-feature superset.

Stateful provider capabilities receive a server-owned `_supc_context` containing bounded orchestration pointers. Client payloads may not supply native/session/lock/correlation/author/sensitivity authority fields outside that context. Collaboration provider join additionally requires an explicit user-intent marker. Providers remain responsible for their canonical native authorization and mutation semantics.

## Security, privacy and abuse-prevention invariants

- REST requests require a logged-in eligible File 00 subject, a valid WordPress REST nonce and a currently creatable/available registered adapter.
- Request and response bodies are bounded and are not persisted by File 22.
- Sensitive external/provider capabilities are reduced per capability and require the governing owner’s exact opt-in when the authoritative adapter/request is sensitive; the browser’s own hint is never treated as authority.
- Stateful provider context is reconstructed from the current owned File 22 session immediately before provider dispatch; client authority-key spoofing is rejected.
- A second contextual mutation budget includes user, privacy-minimized hashed server-observed IP, adapter, capability and time window. Its option mutex has bounded expiry/stale takeover and token-matched release; storage uncertainty fails closed.
- Local privacy/evidence/accessibility/readiness checks remain in-browser.
- Active encrypted recovery is v3. It rejects sensitive content, over-bound envelopes, schema drift and partial/truncated restore. Account-switch/logout cleanup is best-effort and foreign-user records are purged when the Composer opens.
- Provider-returned rich text and restored/pasted rich text pass browser sanitization; remote tracking images are not loaded into a private Composer session.
- File 22 does not create a new media store, AI backend, annotation database, collaboration content store, moderation backend or publication backend.

## Human-action guarantees

AI/derivative insertion, remote collaboration application, template application and conflict resolution remain explicit human actions. An AI/derivative result can be inserted only once; status/error/progress text is not re-enabled as insertable content. Conflict state is not cleared on a generic HTTP success—positive native-owner confirmation of the exact token/resolution is required.

## Release boundary

Source implementation and automated QA do not equal deterministic production-package parity, Hostinger staging acceptance, live deployment or operational acceptance. Those remain separate lifecycle gates under the governing plans. PR merge also remains a separate explicit-authorization step.
