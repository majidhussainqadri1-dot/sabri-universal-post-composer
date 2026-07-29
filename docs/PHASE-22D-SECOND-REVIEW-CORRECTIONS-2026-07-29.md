# Phase 22D Second Review Corrections — 2026-07-29

## Review basis

A second independent review was performed after the first Phase 22D post-correction verification. The earlier conclusion that no code-level blocker remained was superseded by this later review.

## Findings corrected

### Mapping persistence

Repair success is no longer inferred from calling `update_option()`. File 22 now reads `supc_create_page_id` back from WordPress and reports `mapping_persistence_failed` when the expected ID was not persisted.

### Strict page validation

A configured Create surface must be a published WordPress `page`, contain the required shortcode, and have a usable permalink. A post or custom post type containing the shortcode is not accepted.

### Ambiguous discovery

Discovery now returns all valid candidate IDs. More than one candidate produces `ambiguous`; automatic mapping stops and an administrator must explicitly select the canonical page.

### Concurrency and orphan prevention

A short-lived atomic option lock prevents concurrent File 22 repair actions. Managed-page creation makes one insertion attempt only. The inserted object must retain its exact approved slug, page type, publication status, shortcode, permalink, and File 22 ownership metadata. Validation failure stops the request instead of trying additional slugs.

### Inspection performance

Create-page inspection is memoized per request so the dashboard and System Check filter do not repeat the complete published-page scan.

### Health scope

The dashboard now separates role-independent Static Adapter Health from current-administrator Create-surface invocation diagnostics. The UI and documentation state that current-user diagnostics do not replace the staging role matrix.

### Accessibility and notices

Administrator tables now include screen-reader captions and scoped column headers. Repair outcomes use matching success, information, warning, and error notice severity.

## Regression coverage added

Tests now cover:

- failed option persistence;
- configured non-page objects;
- multiple shortcode candidates and explicit selection;
- concurrent repair lock;
- slug uniquification;
- removed ownership metadata;
- inserted non-page objects;
- one-attempt orphan prevention;
- per-request inspection memoization;
- role-independent static adapter metadata validation;
- table captions and scoped headers;
- result-specific notice severity.

## Release boundary

These corrections do not authorize merge, staging, packaging, or production deployment. Fresh CI and a separate post-correction verification remain mandatory.
