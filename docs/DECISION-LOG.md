# Decision Log

## 2026-08-06 — Current numbered-file ownership

**Decision:** File 22 is Sabri Universal Post Composer; File 23 is Doctor and Founder Publishing Dashboard; File 24 is the Security, Privacy, Compliance and Resilience Center; File 25 is Complete Public UI, Profile Timeline and Visual Experience; File 26 owns Search, Discovery and Ranking projections. This later Founder-approved numbering supersedes conflicting earlier entries below.

## 2026-07-28 — File 22 identity

**Historical decision:** File 22 was assigned Sabri Universal Post Composer and the prior public UI module was temporarily mapped to File 23. **Current status:** superseded by the 2026-08-06 numbering above; the public UI/profile/timeline owner is File 25 and File 23 is the publishing dashboard.

**Migration impact:** Documentation and future integration references change; runtime content does not. Existing hook names require compatibility aliases before any rename.

## 2026-07-28 — Ownership boundary

**Decision:** File 22 is a facade and orchestrator, not a duplicate publishing backend.

**Test impact:** One submission must create one native object and no duplicate permanent File 22 copy.

## 2026-07-28 — Mandatory permission authority

**Decision:** Sabri Membership Core 1.0.1 or later is a hard dependency. File 22 checks account status and central capability before any adapter-specific decision.

**Security impact:** Rejected, suspended, and expired-document accounts are denied. Adapters may restrict but never expand access.

## 2026-07-28 — File 20 integration

**Decision:** File 20 must expose an official `sabri_shell_can_show_create` filter after login and Safe Mode checks. File 22 supplies final adapter-aware visibility and the Create URL.

**Compatibility impact:** Older File 20 versions continue to accept the URL filter but cannot expose Create to roles outside their hard-coded list; staging must use the companion File 20 contract update.

## 2026-07-28 — Adapter registration

**Decision:** Direct `supc_register_adapter()` registration is supported after File 22 loads. Registration is not limited to a one-shot action.

## 2026-07-28 — Sensitive storage

**Decision:** File 22 will not own PDF bytes, patient-consent evidence, identity evidence, or private clinical records.

## 2026-07-28 — Safe Create page

**Decision:** Activation resolves or creates a dedicated Create page without overwriting an unrelated page. The private creation surface is noindex, noarchive, and no-cache.

## 2026-07-28 — Development workflow

**Decision:** Audit → branch → coding → short automated checks → staging → Founder verification → PR review → merge. Direct experimental live editing is prohibited.
