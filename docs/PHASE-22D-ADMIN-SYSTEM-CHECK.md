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

### Adapter Health

Displays only operational metadata:

- adapter key;
- native module key;
- adapter API version;
- declared minimum native version;
- group;
- privacy classification;
- status;
- diagnostic codes.

Adapter labels, descriptions, routes, exception messages, user data, content data, identity data, and clinical data are excluded.

Adapters implementing `Diagnostic_Adapter` may provide a health report. File 22 accepts only a controlled status and sanitized codes from that report.

## Repair controls

The Create-page repair form is separate from the read-only dashboard and requires:

- `manage_options`;
- WordPress nonce validation;
- an explicit `dry_run` or `repair` mode.

### Dry Run

Inspects the current state without writing options or creating posts.

Possible states:

- `ready` — the configured page is published and contains the shortcode;
- `repairable` — another published page contains the shortcode and can be mapped;
- `missing` — no valid page exists and a managed page would be required.

### Repair

The repair action may only:

1. retain a valid current mapping;
2. map the File 22 option to an existing published shortcode page;
3. create a new File 22-managed page on the first available approved slug.

It never edits or deletes an existing page. Occupied slugs are skipped. No native module records are changed.

## Approved managed-page slugs

1. `create`;
2. `create-content`;
3. `platform-create`;
4. `sabri-create`.

## Security and privacy requirements

- Administrator capability checked before render and repair.
- Nonce checked before repair.
- Redirect uses `wp_safe_redirect()`.
- Request mode is unslashed and sanitized.
- Health data is normalized before output.
- All administrator output is escaped.
- Raw adapter exceptions and health-report messages are not rendered.
- No patient, identity, content, or workflow URL is displayed.

## Acceptance criteria

- unauthorized users cannot open the dashboard or execute repair;
- dry run makes no changes;
- an existing valid mapping remains unchanged;
- an existing shortcode page is mapped without editing it;
- a missing mapping creates only a managed File 22 page;
- unrelated occupied pages remain byte-for-byte unchanged;
- diagnostic rows normalize invalid input safely;
- adapter health rows omit labels, descriptions, URLs, and raw messages;
- PHP 8.1–8.3 syntax, WordPress Coding Standards, PHPStan, PHPUnit, and repository contracts pass;
- a separate post-implementation review is completed before Phase 22D is considered code-complete.

## Release boundary

Phase 22D does not authorize merge, staging approval, package approval, or production deployment. File 20 producer completion and the Files 00/20/21/22 cross-plugin staging matrix remain required.
