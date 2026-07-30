# File 21 Social Publication Integration

## Contract identity

File 21 registers the release-critical `social_publication` adapter against File 22 Adapter API `1.0.0`.

| Contract field | Required value |
|---|---|
| Adapter key | `social_publication` |
| Native owner | `sabri-complete-home-news-feed` |
| Minimum and actual native version | `1.0.3` or later |
| Central create capability | `sabri_feed_create_posts` |
| Group | `publishing` |
| Privacy classification | `public` |
| Native start route | `/create-post/` |
| Diagnostic contract | `Diagnostic_Adapter` |

## Ownership boundary

File 22 discovers, authorizes centrally, orders, and presents the adapter. File 21 owns the native Composer, post records, drafts, validation, media, moderation, review, scheduling, publication, and canonical content URLs.

A File 22 selection opens File 21's native route. File 22 does not create a shadow post or copy the native payload.

## Permission composition

File 22 first enforces Membership Core status and the canonical `sabri_feed_create_posts` capability. File 21 then applies `ComposerPermissions::user_can_create()`.

Both decisions must allow access. File 21 may narrow the central decision; it cannot grant access that Membership Core denied. Patient, student, suspended, rejected, expired-document, logged-out, and other unauthorized accounts must not receive a Social Post choice.

## Gateway and fallback behavior

The global Create page replaces File 21's fallback CTA only when all of the following are true:

- File 22 Adapter API `1.0.0` is active;
- the exact File 21 adapter was registered successfully and `supc_adapter_matches()` confirms its owner and availability;
- the File 22 Create page is ready;
- File 22 Safe Mode is clear;
- File 20 is version `1.0.1` or later;
- File 20 declares the exact `SABRI_SHELL_CREATE_CONTRACT_VERSION` `1.0.1`, canonical owner, and function-ownership marker;
- `sabri_shell_create_contract_available()` confirms the producer contract is genuinely active.

A version number alone is not sufficient evidence of the File 20 producer hook. Until every condition is true, File 21 retains its existing `/create-post/` Home/News fallback. This prevents an approved doctor from losing the only visible posting path during a partial rollout, incompatible upgrade, duplicate-key collision, Safe Mode event, or rollback.

A duplicate `social_publication` adapter key is not treated as successful File 21 registration. The fallback remains visible and the collision is reported.

## System Check

`Core_Adapter_Requirements` adds `social_publication_adapter` to the File 22 System Check report.

- `pass`: exact owner, key, group, privacy class, central capability, declared minimum version, actual runtime version, diagnostic contract, and current availability all pass;
- `warning`: the exact compatible adapter is registered but the native Composer is temporarily unavailable or disabled;
- `fail`: adapter missing, duplicate/foreign owner, wrong capability, wrong group/privacy class, declared or actual File 21 version below `1.0.3`, unreported runtime version, or diagnostic exception.

A missing or incompatible adapter blocks File 22 Core 1.0 release acceptance. It does not fatal public reading or unrelated optional adapters.

## Current phase boundary

The cumulative Draft integration requires File 21's complete base, diagnostic, workflow, native-draft, role-neutral schema, and subject-aware schema contracts. File 22 exposes guarded server-side orchestration only; File 21 continues to own durable autosave, preview authorization, validation policy, idempotency persistence and reconciliation, scheduling, moderation, publication, and canonical records. Neither Draft PR is staging-, merge-, or production-approved.

## Staging acceptance

- Founder and Administrator see **Social Post** and reach `/create-post/`;
- verified and policy-permitted unverified doctors follow File 21 review rules;
- patient, student, suspended, rejected, expired-document, and logged-out accounts are denied as specified;
- File 20 desktop and mobile Create entries resolve to the File 22 Create page;
- File 21's duplicate fallback CTA is absent only when the complete gateway is operational;
- a false File 20 version declaration without the producer contract does not remove the fallback;
- a duplicate or foreign `social_publication` adapter does not remove the fallback;
- disabling File 22 restores File 21 fallback without data migration;
- one native submission creates one File 21 object and no File 22 duplicate;
- Safe Mode hides creation without affecting public reading.
