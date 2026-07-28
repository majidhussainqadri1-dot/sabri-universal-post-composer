# Decision Log

## 2026-07-28 — File 22 identity

**Decision:** File 22 is Sabri Universal Post Composer.

**Prior record:** The consolidated master plan previously assigned File 22 to Complete Public UI, Profile Timeline and Visual Experience.

**New record:** The prior visual-experience module moves to File 23. All future governing documents, roadmaps, code comments, test plans, and integration references must use the new numbering. Existing public hooks must receive compatibility aliases rather than silent breaking renames.

**Reason:** The platform requires a single role-aware Create gateway before final public UI harmonization.

**Migration impact:** Documentation and future integration references must be updated. Existing runtime data is not modified by this decision.

**Test impact:** Repository, package, branch, plugin slug, and release manifests must consistently identify File 22 as `sabri-universal-post-composer`.

## 2026-07-28 — Ownership boundary

**Decision:** File 22 is a facade and orchestrator, not a duplicate publishing backend.

**Reason:** File 21 and other companion modules already own native content models, moderation, storage, and canonical destinations.

**Migration impact:** Existing forms and shortcodes remain valid until an explicit reversible compatibility migration is accepted on staging.

**Test impact:** Tests must prove that one submission creates one native object and no duplicate permanent File 22 copy.

## 2026-07-28 — Dependencies

**Decision:** File 00 is the central permission authority. File 20 is a production shell integration. File 21 and other modules are adapter-specific dependencies.

**Reason:** One unavailable module must not disable unrelated healthy creation types.

## 2026-07-28 — Sensitive storage

**Decision:** File 22 will not own PDF bytes, patient-consent evidence, identity evidence, or private clinical records.

**Reason:** These data classes require dedicated native security boundaries and retention policies.

## 2026-07-28 — Development workflow

**Decision:** Development follows audit → branch → coding → automated checks → staging → Founder verification → PR review → merge. Direct experimental editing on the live website is prohibited.
