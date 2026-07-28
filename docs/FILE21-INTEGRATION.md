# File 21 Social Publication Integration

## Contract identity

File 21 registers the release-critical `social_publication` adapter against File 22 Adapter API `1.0.0`.

| Contract field | Required value |
|---|---|
| Adapter key | `social_publication` |
| Native owner | `sabri-complete-home-news-feed` |
| Minimum native version | `1.0.3` |
| Group | `publishing` |
| Privacy classification | `public` |
| Native start route | `/create-post/` |

## Ownership boundary

File 22 discovers, authorizes centrally, orders, and presents the adapter. File 21 owns the native Composer, post records, drafts, validation, media, moderation, review, scheduling, publication, and canonical content URLs.

A File 22 selection opens File 21's native route. File 22 does not create a shadow post or copy the native payload.

## Permission composition

File 22 first enforces Membership Core status and the adapter's central capability. File 21 then applies `ComposerPermissions::user_can_create()`.

Both decisions must allow access. File 21 may narrow the central decision; it cannot grant access that Membership Core denied.

## Gateway and fallback behavior

The global Create page is used only when:

- File 22 Adapter API `1.0.0` is active;
- the File 22 Create page is ready;
- File 22 Safe Mode is clear;
- File 20 version `1.0.1` or later exposes the official `sabri_shell_can_show_create` producer contract.

Until all four conditions are true, File 21 retains its existing `/create-post/` Home/News fallback. This prevents an approved doctor from losing the only visible posting path during a partial rollout or rollback.

## System Check

`Core_Adapter_Requirements` adds `social_publication_adapter` to the File 22 System Check report.

- `pass`: adapter registered, minimum native version contract is at least `1.0.3`, and the adapter is available;
- `warning`: adapter is registered but temporarily unavailable, or declares an older native-version contract;
- `fail`: adapter is not registered or diagnostics throw an exception.

A missing adapter blocks File 22 Core 1.0 release acceptance. It does not fatal public reading or unrelated optional adapters.

## Current phase boundary

The first integration is a route-only base adapter. File 22's optional `Workflow_Adapter` is intentionally deferred. Autosave, preview, validation, submission, scheduling, idempotency persistence, and moderation continue inside File 21 until a separately versioned workflow integration is approved.

## Staging acceptance

- Founder and Administrator see **Social Post** and reach `/create-post/`;
- verified and policy-permitted unverified doctors follow File 21 review rules;
- patient, student, suspended, rejected, expired-document, and logged-out accounts are denied as specified;
- File 20 desktop and mobile Create entries resolve to the File 22 Create page;
- File 21's duplicate fallback CTA is absent only when the complete gateway is operational;
- disabling File 22 restores File 21 fallback without data migration;
- one native submission creates one File 21 object and no File 22 duplicate;
- Safe Mode hides creation without affecting public reading.
