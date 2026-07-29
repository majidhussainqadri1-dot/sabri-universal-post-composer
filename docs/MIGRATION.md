# Migration

## Existing forms

Existing native submission pages, shortcodes, pending records, drafts, and URLs remain valid. File 22 must not delete or bulk-convert them.

## Compatibility migration

A native module may register an adapter whose `start_url()` points to its existing form. A later full Workflow Adapter may replace that route only after fresh-install, upgrade, data-integrity, and rollback tests pass on staging.

## Create page

Activation resolves an already configured published page, then a published page containing `[sabri_universal_composer]`, and only then creates a new published page using the first free approved slug. Existing unrelated pages are never overwritten.

## Numbering migration

File 22 now denotes Universal Post Composer. The prior public-UI/profile-timeline module moves to File 23. Existing public hook names are not silently renamed; compatibility aliases require regression tests.
