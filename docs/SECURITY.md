# Security and Privacy Baseline

File 22 fails closed for authorization, account suspension, invalid or colliding contracts, unsafe routing, ambiguous ownership, malformed payloads, weak idempotency, unsafe previews, invalid native results, and dependency mismatch.

## Required controls

- Membership Core enforcement at every interactive operation.
- Authenticated-subject binding, ownership, and IDOR protection.
- WordPress nonces, method checks, same-origin writes, and rate limits for every future HTTP controller.
- Sanitization before storage and context-appropriate escaping.
- Prepared SQL for future tables.
- MIME, signature, size, and ownership validation for future upload controllers.
- Short-lived private previews.
- Privacy-safe diagnostics without bodies, identities, or secrets.

## Public API ownership and collision safety

The File 22 `supc_*` API is valid only when its version, owner, function-ownership, and empty collision markers agree. Any pre-existing function or marker collision prevents the complete API family from being declared. File 22 never silently produces a partial mixed-version API.

Bootstrap applies the same fail-closed rule to every File 22 core constant, interface, and runtime class before loading source files. A preclaimed symbol cannot produce a mixed runtime or fatal redeclaration.

Interactive workflow functions bind to `get_current_user_id()`. A supplied compatibility user ID on the read-only adapter-availability helper is ignored and cannot be used to inspect another account.

## Account, availability, and authorization order

The coordinator checks Safe Mode, valid current subject, Membership Core eligibility, immutable workflow metadata, and the central capability before disclosing Workflow API compatibility or native health. Compatible workflows then evaluate native availability before adapter-specific authorization.

Every authorization and operational allow decision is re-evaluated on every call. File 22 does not cache an approved Membership state, capability, Safe Mode result, native availability, or adapter-specific permission across later same-request changes.

Pending, draft, rejected, suspended, expired-document, deleted, logged-out, unknown, and otherwise unapproved accounts are denied before native methods execute. Administrators and Founders cannot use WordPress role or `manage_options` privileges to expand a Membership Core denial and remain subject to emergency-disable controls.

Malformed Workflow API metadata is rejected atomically at registration. A syntactically valid but unsupported version remains available only for controlled compatibility diagnostics and is never invokable.

## Unified Shell Safe Mode ownership

A callable File 20 Safe Mode class is trusted only when the exact File 20 contract version, canonical owner, and function-ownership marker agree. An obsolete, incomplete, or colliding shell class fails closed and cannot clear File 22's emergency boundary.

## Create-surface privacy and cache boundary

Every WordPress object that actually renders `[sabri_universal_composer]` is treated as a private personalized Create surface, whether or not it is the canonical mapped page. It receives:

- no-cache headers;
- WordPress page/object/database no-cache constants;
- `Vary: Cookie` and the LiteSpeed no-cache signal;
- `X-Robots-Tag: noindex, nofollow, noarchive`;
- equivalent `wp_robots` directives.

This includes noncanonical and ambiguous shortcode pages. An unrelated public page retains its normal cache/indexing policy. Staging must prove that LiteSpeed, CDN, browser, sitemap, feed, and search layers never cache or expose one role's personalized Composer output to another user.

## Workflow payload and schema boundary

File 22 does not store workflow payloads. Payloads are limited to scalar values, `null`, and bounded nested arrays under 1 MiB. Objects, resources, closures, non-finite floats, excessive nesting, and undeclared fields are rejected.

Static `schema()` is role-neutral. When an adapter exposes `schema_for_user( int $user_id )`, interactive schema retrieval and payload validation use the authenticated subject's variant. Required fields, types, choices, numeric bounds, email, URL, date, datetime, checkbox, and opaque-reference formats are enforced before native mutation.

Founder/Administrator-only choices must never appear in a doctor's subject schema. Static health never treats the current administrator's schema as a global contract.

## Native reference and URL boundary

Native references are opaque identifiers, not authorization. Status and canonical URL operations pass the authenticated subject to the native owner, which must enforce ownership or visibility.

Start, preview, canonical, and Create-page URLs must be relative internal routes or absolute same-origin HTTPS URLs with matching effective port and no credentials, backslashes, protocol-relative form, control characters, or downgrade.

## Create-page repair boundary

Discovery narrows the WordPress query to likely shortcode-bearing pages, returns IDs only, and still revalidates every candidate. If a newly inserted File 22-managed page fails ownership, slug, type, content, publication, or URL validation, File 22 permanently deletes only that exact newly created object and records a privacy-safe rollback event. Pre-existing unrelated pages are never edited or deleted.

## Draft, preview, and submission boundary

Draft creation requires explicit native state `draft` or `pending_review`. Preview expiry must be in the future and no more than 30 minutes from the File 22 call; the native File 21 policy may be shorter.

Submission uses an immutable two-UUID-v4 idempotency key. The native owner must reconcile lost responses, concurrent retries, completion-write failure, and crash-left locks without duplicate native mutation.

## Native diagnostics

Only fixed File 22 native-error and System Check vocabularies are public. Arbitrary native codes become `native_error`; arbitrary System Check codes become `unrecognized_diagnostic`. Raw native message/data and exception class/message are discarded. Registry and System Check diagnostics contain controlled codes only.

No diagnostic may contain a user ID, identity/document evidence, URL, native reference, raw idempotency key, unpublished body, patient narrative, consent evidence, SQL, path, stack, token, nonce, credential, or secret.

## Native ownership

The native module remains responsible for durable drafts, protected evidence, uploads, moderation, publication, ownership checks, canonical records, personal-data export/erasure, retention, and cleanup. File 22 never creates a shadow permanent content model.

## Patient Case and secure media restrictions

A public Patient Case is an anonymized educational publication, not a private clinical record. Plaintext browser storage is prohibited. Consent evidence remains with a dedicated privacy owner and File 22 receives only an opaque reference.

PDF bytes remain with File 12, identity evidence with Membership Core/verification, and Marketplace verified contacts with the seller profile.

## Release gate

No release proceeds with unresolved critical or high-severity privilege escalation, confused-deputy substitution, CSRF, XSS, IDOR, cache leakage, indexing leakage, open redirect, SSRF, path traversal, SQL injection, MIME spoofing, duplicate submission, draft theft, preview leakage, payload leakage, diagnostic leakage, contract collision, unsafe result envelopes, or rollback failure.
