# Phase 22D — Administrator System Check and Repair Controls

## Purpose

Phase 22D gives authorized administrators a privacy-safe operational view of File 22 without exposing user content or allowing broad mutation of native modules.

## Administrator location

`Tools → Composer Health`

The page requires the WordPress `manage_options` capability.

## Dashboard sections

### System Check

Normalizes and displays rows contributed through `supc_system_check_report`:

- check key;
- `pass`, `warning`, or `fail` status;
- count;
- privacy-safe diagnostic codes.

Unknown statuses fail conservatively to `warning`. Invalid rows and empty keys are omitted.

Create-surface invocation diagnostics are limited to the currently signed-in administrator. They do not represent every doctor, student, patient, suspended account, or other role. The cross-plugin staging role matrix remains mandatory.

### Static Adapter Health

This role-independent section validates operational metadata without calling a user-specific creation route:

- adapter key;
- native module key;
- adapter API version;
- declared minimum native version;
- required capability shape;
- controlled group;
- controlled privacy classification;
- native availability;
- diagnostic status and codes.

Adapter labels, descriptions, routes, exception messages, user data, content data, identity data, and clinical data are excluded.

Adapters implementing `Diagnostic_Adapter` may provide a health report. File 22 accepts only a controlled status and sanitized codes. A passing report cannot downgrade a native-unavailable warning or a static contract failure.

## Create-page inspection

Inspection is memoized once per request and is read-only. It validates that a configured object is:

- a WordPress `page`;
- published;
- contains `[sabri_universal_composer]`;
- has a usable permalink.

Possible states:

- `ready` — the configured page is valid;
- `repairable` — exactly one other valid published shortcode page exists;
- `ambiguous` — multiple valid shortcode pages exist and an administrator must explicitly select the canonical page;
- `missing` — no valid page exists and a managed page would be required.

## Repair controls

The repair form is separate from the read-only dashboard and requires:

- `manage_options`;
- WordPress nonce validation;
- an explicit `dry_run` or `repair` mode;
- explicit candidate selection when the state is `ambiguous`.

### Dry Run

Dry Run reports the state without writing options, creating posts, or acquiring a mutation lock.

### Repair

The repair action may only:

1. retain a valid current mapping;
2. map File 22 to the single valid candidate;
3. map File 22 to an explicitly selected valid candidate when multiple candidates exist;
4. create one new File 22-managed page on the first available approved slug.

Every mapping write is read back from WordPress before success is reported. A failed option write returns `mapping_persistence_failed` rather than a false success.

## Concurrency and managed-page validation

A short-lived atomic repair lock prevents simultaneous File 22 repair requests. Stale locks may be recovered after the defined timeout.

Managed-page creation performs one insertion attempt only. The inserted object is accepted only when it has:

- post type `page`;
- published status;
- the exact selected approved slug;
- the required shortcode;
- a usable permalink;
- `_supc_managed_page = 1` ownership metadata.

If hooks or a concurrent external write alter the inserted object, validation fails and File 22 does not attempt additional slugs in the same repair request. This prevents repeated orphan creation.

## Approved managed-page slugs

1. `create`;
2. `create-content`;
3. `platform-create`;
4. `sabri-create`.

Occupied slugs are skipped before insertion. WordPress slug uniquification after insertion fails exact validation and is not accepted as the canonical mapping.

## Security, privacy, and accessibility requirements

- Administrator capability checked before render and repair.
- Nonce checked before repair.
- Redirect uses `wp_safe_redirect()` and failure is handled explicitly.
- Request values are unslashed and sanitized.
- Health data is normalized before output.
- All administrator output is escaped.
- Raw adapter exceptions and health-report messages are not rendered.
- No patient, identity, content, or workflow URL is displayed.
- Administrator tables use captions and scoped column headers.
- Success, information, warning, and failure notices use matching WordPress notice severity.

## Acceptance criteria

- unauthorized users cannot open the dashboard or execute repair;
- dry run makes no changes;
- inspection scans published pages at most once per request;
- a configured non-page object is never treated as ready;
- one candidate may be mapped without editing it;
- multiple candidates require explicit selection;
- failed option persistence is reported as failure;
- a concurrent repair request is rejected safely;
- managed-page creation makes at most one insertion attempt;
- slug, post type, ownership metadata, publication, shortcode, and permalink are validated after insertion;
- unrelated occupied pages remain unchanged;
- static adapter health is role-independent;
- current-user invocation diagnostics are labeled as role-limited;
- diagnostic rows normalize invalid input safely;
- adapter health rows omit labels, descriptions, URLs, and raw messages;
- PHP 8.1–8.3 syntax, WordPress Coding Standards, PHPStan, PHPUnit, and repository contracts pass;
- a separate post-correction verification is completed before Phase 22D is considered code-complete.

## Release boundary

Phase 22D does not authorize merge, staging approval, package approval, or production deployment. File 20 producer completion and the Files 00/20/21/22 cross-plugin staging matrix remain required.
