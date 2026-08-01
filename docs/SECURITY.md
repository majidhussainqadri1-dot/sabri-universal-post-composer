# Security and Privacy Baseline

File 22 fails closed for authorization, account suspension, invalid or colliding contracts, unsafe routing, ambiguous ownership, malformed payloads, weak idempotency, unsafe previews, invalid native results, dependency mismatch, executable-source mismatch, and failed Create-page cleanup.

## Required controls

- Membership Core enforcement at every interactive operation.
- Authenticated-subject binding, ownership, and IDOR protection.
- WordPress nonces, method checks, same-origin writes, and rate limits for every future HTTP controller.
- Sanitization before storage and context-appropriate escaping.
- Prepared SQL for future tables.
- MIME, signature, size, and ownership validation for future upload controllers.
- Short-lived private previews.
- Privacy-safe, bounded diagnostics without bodies, identities, or secrets.

## Public API ownership and collision safety

The File 22 `supc_*` API is valid only when its version, owner, function-ownership, and empty collision markers agree **and** Reflection resolves every required public function to File 22's exact `includes/core/functions.php` source file. Any pre-existing function or marker collision prevents the complete API family from being declared and forces the internal Composer into Safe Mode. A coherent foreign function family with copied markers is never treated as owned execution authority.

Bootstrap applies the same fail-closed rule to every File 22 core constant, interface, and runtime class before loading source files. A preclaimed symbol cannot produce a mixed runtime or fatal redeclaration.

Interactive workflow functions bind to `get_current_user_id()`. A supplied compatibility user ID on the read-only adapter-availability helper is ignored and cannot be used to inspect another account.

## Membership Core authority and version boundary

File 00 is accepted only when all of the following agree:

- compatible `SMC_VERSION` and `SMC_DB_VERSION` values;
- strict bounded Semantic Versioning without whitespace, malformed identifiers, or leading-zero numeric identifiers;
- canonical package directory `sabri-membership-core`;
- canonical bootstrap file `sabri-membership-core.php`;
- coherent real paths declared by `SMC_FILE` and `SMC_PATH`;
- `smc_user_status()` originating from that canonical package directory.

A foreign component cannot become the authorization authority merely by copying constants, using a high version string, and defining a same-named callback inside its own coherent directory.

Semantic Versioning precedence is implemented independently of PHP `version_compare()`: numeric identifiers are compared numerically without integer conversion, numeric prerelease identifiers rank below nonnumeric identifiers, shorter equal prerelease prefixes rank lower, stable releases outrank prereleases, and build metadata does not affect precedence.

## Account, availability, and authorization order

The coordinator checks Safe Mode, valid current subject, Membership Core eligibility, immutable workflow metadata, and the central capability before disclosing Workflow API compatibility or native health. Compatible workflows then evaluate native availability before adapter-specific authorization.

Every authorization and operational allow decision is re-evaluated on every call. File 22 does not cache an approved Membership state, capability, Safe Mode result, native availability, or adapter-specific permission across later same-request changes.

Pending, draft, rejected, suspended, expired-document, deleted, logged-out, unknown, and otherwise unapproved accounts are denied before native methods execute. Administrators and Founders cannot use WordPress role or `manage_options` privileges to expand a Membership Core denial and remain subject to emergency-disable controls.

Malformed Workflow API metadata is rejected atomically at registration. A syntactically valid but unsupported version remains available only for controlled compatibility diagnostics and is never invokable.

## Unified Shell package and Create-contract ownership

The distributed File 20 version 1.0.0 base package and the later File 20 Create contract are separate trust domains.

A base File 20 package is canonical only when the package directory, bootstrap file, slug, real paths, and bounded Semantic Version agree. The distributed version 1.0.0 package consumes the `sabri_shell_create_url` filter but does not expose `SABRI_SHELL_CREATE_CONTRACT_*`, `sabri_shell_create_contract_available()`, or `sabri_shell_create_visible_for_current_user()`. Its valid base constants alone must not disable File 22 and must not be treated as role-aware visibility or emergency-state authority.

Once any later Create marker or producer function is claimed, the complete family is required:

- exact Create contract version and owner;
- true function-ownership marker;
- both producer functions;
- canonical File 20 package identity;
- Reflection source ownership for both producer functions;
- a preloaded canonical `Sabri\UnifiedShell\SafeMode` class;
- a public static `disabled()` method declared inside that package.

Marker-only, function-only, partial, obsolete, colliding, inherited, autoload-only, or coherent foreign-source Create claims fail closed. The role-aware `sabri_shell_can_show_create` bridge is registered only after this complete contract is proven.

## Bounded adapter and diagnostic surface

File 22 accepts at most 100 adapters. Adapter keys, native owners, capabilities, groups, priorities, privacy classes, versions, labels, descriptions, icons, and routes are validated and bounded. Registry diagnostics are limited to 200 entries plus one controlled limit marker. System Check renders at most 100 normalized rows, caps reported counts at 1000, and accepts only fixed keys/codes.

A native adapter cannot allocate an unbounded registry, diagnostic, card, or Administrator-health surface through integration hooks.

## Create-surface privacy and cache boundary

Every WordPress object that actually renders `[sabri_universal_composer]` is treated as a private personalized Create surface, whether or not it is the canonical mapped page. It receives:

- no-cache headers;
- WordPress page/object/database no-cache constants;
- `Vary: Cookie` and the LiteSpeed no-cache signal;
- `X-Robots-Tag: noindex, nofollow, noarchive`;
- equivalent `wp_robots` directives.

This includes noncanonical and ambiguous shortcode pages. An unrelated public page retains its normal cache/indexing policy. Staging must prove that LiteSpeed, CDN, browser, sitemap, feed, and search layers never cache or expose one role's personalized Composer output to another user.

Create-surface System Check never reports PASS when Safe Mode, a missing subject, central denial, or native unavailability prevented actual evaluation.

## Workflow payload and schema boundary

File 22 does not store workflow payloads. Payloads are limited to scalar values, `null`, and bounded nested arrays under 1 MiB, depth 12, and 1000 elements per array node. Objects, resources, closures, non-finite floats, excessive nesting, excessive elements, and undeclared fields are rejected.

Static `schema()` is role-neutral. When an adapter exposes `schema_for_user( int $user_id )`, interactive schema retrieval and payload validation use the authenticated subject's variant. Schema versions use the central strict Semantic Versioning validator. Required fields, types, unique bounded choices, numeric bounds, email, URL, date, datetime, checkbox, and opaque-reference formats are enforced before native mutation.

Multiselect payloads are list-shaped, unique, declared, and limited to the schema choice ceiling. Native validation-code collections are bounded to 100 items at each supported depth. Founder/Administrator-only choices must never appear in a doctor's subject schema. Static health never treats the current administrator's schema as a global contract.

## Native reference, URL, and idempotency boundary

Native references are opaque identifiers, not authorization. Status and canonical URL operations pass the authenticated subject to the native owner, which must enforce ownership or visibility.

Start, preview, canonical, schema URL, and Create-page routes are bounded to 2048 bytes and must be relative internal routes or absolute same-origin HTTPS URLs with matching effective port and no credentials, backslashes, protocol-relative form, control characters, or downgrade.

Submission uses an immutable two-UUID-v4 idempotency key. File 22 validates generated platform UUID output before returning the key; malformed generator output fails closed. The native owner must reconcile lost responses, concurrent retries, completion-write failure, and crash-left locks without duplicate native mutation.

## Create-page repair boundary

Discovery narrows the WordPress query to likely shortcode-bearing pages, returns IDs only, and is limited to a finite candidate window. Every candidate is still revalidated. Existing candidate or unrelated pages are never edited or deleted by failed-repair cleanup.

Repair locks accept only a valid UUID-v4 generated before the atomic option write. If a newly inserted File 22-managed object fails ownership, slug, type, content, publication, URL, or mapping-persistence validation, cleanup is limited to that exact inserted ID:

1. attempt permanent deletion;
2. verify that it is no longer a public shortcode surface;
3. if deletion fails, convert it to an empty nonpublic draft and verify quarantine;
4. if both operations fail while it remains public, set `supc_emergency_disabled` and emit `supc_created_page_cleanup_failed`.

Mapping rollback snapshots existence separately from value, so absence, literal strings, `null`, arrays, and serialized objects are restored semantically. No magic value or object-identity change may be mistaken for a missing or failed restoration.

## Draft, preview, and native diagnostics

Draft creation requires explicit native state `draft` or `pending_review`. Preview expiry must be in the future and no more than 30 minutes from the File 22 call; the native File 21 policy may be shorter.

Only fixed File 22 native-error and System Check vocabularies are public. Arbitrary native codes become `native_error`; arbitrary System Check codes become `unrecognized_diagnostic`. Raw native message/data and exception class/message are discarded. No diagnostic may contain a user ID, identity/document evidence, URL, native reference, raw idempotency key, unpublished body, patient narrative, consent evidence, SQL, path, stack, token, nonce, credential, or secret.

## Native ownership and patient/media restrictions

The native module remains responsible for durable drafts, protected evidence, uploads, moderation, publication, ownership checks, canonical records, personal-data export/erasure, retention, and cleanup. File 22 never creates a shadow permanent content model.

A public Patient Case is an anonymized educational publication, not a private clinical record. Plaintext browser storage is prohibited. Consent evidence remains with a dedicated privacy owner and File 22 receives only an opaque reference. PDF bytes remain with File 12, identity evidence with Membership Core/verification, and Marketplace verified contacts with the seller profile.

## Release gate

No release proceeds with unresolved critical or high-severity privilege escalation, confused-deputy substitution, CSRF, XSS, IDOR, cache leakage, indexing leakage, open redirect, SSRF, path traversal, SQL injection, MIME spoofing, duplicate submission, draft theft, preview leakage, payload leakage, diagnostic leakage, contract collision, executable-source spoofing, unsafe result envelopes, unbounded integration surfaces, or rollback failure.
