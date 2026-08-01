# File 22 Post-Merge Independent Review and Correction — 30 July 2026

## Scope

This review examined canonical `main` after merged PR #6 and compared the actual runtime, tests, public API, repository status, and review-evidence automation against the File 22 ownership and fail-closed architecture.

Reviewed areas:

- adapter registration and rollback behavior;
- authorization and native-owner integrity;
- current-subject public API behavior;
- empty and unavailable integration states;
- merged-source versus staging/release status documentation;
- historical versus current review-evidence boundaries;
- regression coverage for the corrected boundaries.

## Findings

### F22-PM-01 — Non-atomic workflow-adapter registration

**Severity:** High

`Registry::register()` inserted the adapter into the live registry before invoking all required Workflow Adapter metadata methods. If `workflow_api_version()` or `supports_native_drafts()` threw an exception, registration returned an error but the partially accepted adapter could remain registered without a complete workflow contract.

**Correction:** All base and workflow metadata is now collected and validated before any registry mutation. The final adapter, base contract, and workflow contract are committed together. The exception path defensively removes every possible partial entry and flushes request caches.

### F22-PM-02 — Authorization-critical metadata was mutable after registration

**Severity:** High

Base-adapter availability checks re-read `required_capability()` at invocation time, while exact-owner matching re-read `native_module()`. A buggy or hostile adapter could declare a restrictive capability or canonical owner at registration and later return a weaker capability or different owner.

**Correction:** File 22 now records an immutable base registration contract containing:

- Adapter API version;
- required central capability;
- canonical native-module owner;
- minimum native version;
- privacy classification.

The registry uses the captured capability for Create-surface authorization. `supc_adapter_matches()` uses the captured owner. Dynamic native health and adapter-specific `can_create()` may still narrow access, but they cannot expand the central permission boundary or change ownership.

### F22-PM-03 — Empty adapter registry was mislabeled as permission denial

**Severity:** Medium

An approved account encountering no registered adapter received the `denied` state, causing the Create surface to report that the account lacked permission. The actual condition was a missing integration/service registration.

**Correction:** An eligible subject with an empty registry now receives `unavailable`. Logged-out, suspended, rejected, and otherwise ineligible accounts remain `denied`; adapter-specific denial also remains `denied`.

### F22-PM-04 — Repository status contradicted merged GitHub state

**Severity:** Medium

The repository contained wording that all phases and PR #6 remained unmerged Draft work. Canonical GitHub history showed PR #6 merged. A subsequently added `REPOSITORY-STATUS.md` also incorrectly stated that the repository had contained no commits or source files.

**Correction:** The false status file is removed. `README.md` and `MANIFEST.md` now distinguish:

- merged source baseline;
- development version `0.1.0-dev`;
- no controlled staging acceptance;
- no approved package or production release;
- no live deployment or completion claim.

### F22-PM-05 — Historical Phase 22E evidence workflow rejected every later runtime review

**Severity:** Medium

The Phase 22E evidence workflow granted supersession only to the old cumulative correction branch name. Any later independent branch based on the already merged cumulative baseline failed merely because current runtime files differed from the historical Phase 22E implementation SHA. This produced a false red check even when the historical evidence remained intact and the newer cumulative evidence check passed.

**Correction:** The workflow now uses the immutable merged PR #6 commit as its supersession boundary. Descendants of that merged cumulative baseline preserve and verify the historical Phase 22E records, then exit successfully without treating later independently reviewed runtime changes as corruption. Pre-cumulative branches still retain the original historical file-freeze enforcement.

## Regression tests and automation checks added

- workflow metadata exception leaves no adapter, base contract, or workflow contract;
- post-registration capability downgrade cannot broaden access;
- post-registration owner mutation cannot change `supc_adapter_matches()`;
- eligible account with no adapters receives service-unavailable classification;
- merged cumulative descendants no longer receive a false Phase 22E evidence failure.

## Files corrected

- `includes/core/class-registry.php`
- `includes/core/functions.php`
- `tests/RegistryTest.php`
- `tests/PublicApiSubjectBindingTest.php`
- `.github/workflows/phase22e-review-evidence.yml`
- `README.md`
- `MANIFEST.md`
- removal of `REPOSITORY-STATUS.md`

## Acceptance boundary

This correction is a source-level audit and repair. It does not authorize a production package or deployment. Required next evidence remains:

- exact-head GitHub Actions success;
- controlled Files 00/20/21/22 staging installation;
- Founder, trusted doctor, verified doctor, patient, student, suspended, and pending-account matrix;
- IDOR and ownership tests against real native references;
- cache/CDN/noindex validation;
- browser, mobile, keyboard, reduced-motion, contrast, and Urdu RTL acceptance;
- backup restoration and rollback proof;
- explicit Founder approval before merge/deployment.
