# File 22 Cumulative Post-Implementation Review

reviewed_implementation_sha: `5a313d97a5ee54870eb7405fd788addfacb9799c`
review_date: `2026-07-29`
disposition: `REQUEST CHANGES`

## Scope

Independent review of the cumulative main-target File 22 corrective implementation, including runtime, public API ownership, Create-surface privacy, registry state classification, Workflow Coordinator, subject-aware schema extension, File 20/File 21 diagnostics, System Check, tests, CI, dependency lock, documentation, manifest, migration, staging, and rollback boundaries.

Automated success was treated as evidence, not acceptance.

## Finding 1 — High: direct/programmatic shortcode rendering is not guaranteed to receive the privacy headers

`template_redirect` protects the canonical page and a global post whose stored content contains `[sabri_universal_composer]`. WordPress or a theme can also invoke the registered shortcode directly from a template, widget, block callback, or `do_shortcode()` while the global post does not contain the shortcode.

In that case `render_shortcode()` returns the personalized Composer but does not itself call `nocache_headers()` or send `X-Robots-Tag`. The prior correction therefore protects every detected shortcode post, not every actual shortcode rendering.

### Required correction

- enforce no-cache and `X-Robots-Tag: noindex, nofollow, noarchive` again at the shortcode render boundary;
- keep the earlier pre-routing protection for normal pages;
- add a regression test that invokes `render_shortcode()` with no canonical mapping and no shortcode in the global post, and proves the private response boundary executes.

## Finding 2 — High: date and datetime validation accepts impossible calendar values

The schema-bound payload validator uses regular expressions only. Values such as `2026-99-99`, an hour between 24 and 29, or an impossible calendar day can match the patterns and reach the native adapter.

### Required correction

- validate `date` with an exact calendar parse and round trip;
- validate `datetime` with an exact accepted format set and round trip;
- reject impossible dates/times and malformed or excessive timezone offsets;
- add valid leap-day and invalid calendar/time regression tests.

## Finding 3 — High: File 21 static schema output is normalized, but static inspection still executes current-user schema logic

`UniversalComposerSubjectSchemaAdapter::schema()` calls the delegated adapter's role-dependent `schema()` and then replaces only the `feed_type` choices. The returned output is role-neutral today, but static health still executes current-user identity logic. A future role-dependent field or exception would reintroduce the same defect.

### Required correction

- construct the complete role-neutral schema without invoking the delegated current-user schema;
- construct the subject-aware schema from the same explicit builder;
- keep native workflow operations delegated;
- update the real cross-repository test to prove static health performs no subject-schema call and interactive calls do.

## Finding 4 — Major: source manifest lists a deleted privileged workflow

The temporary write-enabled `.github/workflows/corrective-lock-sync.yml` was correctly deleted after `composer.lock` was committed, but `MANIFEST.md` still lists it.

### Required correction

- remove the deleted workflow from the manifest;
- retain `composer.lock` and read-only CI verification;
- add the cumulative review and later verification/evidence files to the manifest and repository contract.

## Additional evidence corrections required

- record the final committed `composer.lock` SHA-256 from the final exact-head CI artifact;
- update File 22 PR #6 and File 21 PR #21 descriptions to final heads and current cross-repository pins;
- preserve Phase 22E evidence as historical/superseded rather than using it to approve the changed cumulative runtime;
- create a new exact-head cumulative review-evidence workflow after all findings are corrected.

## Decision

File 22 remains Draft, unmerged, unstaged, and unreleased. Correct all findings, run fresh exact-head File 22 and File 21/File 22 contract checks, perform post-correction verification, and only then reassess code-level readiness. Hostinger staging, browser/accessibility/RTL, backup restoration, rollback, and explicit Founder authorization remain separate mandatory gates.
