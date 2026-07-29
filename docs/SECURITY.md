# Security and Privacy Baseline

File 22 fails closed for authorization, suspension, invalid adapter contracts, unsafe routing, ambiguous ownership, expired sessions, invalid nonces, unsupported native modules, malformed workflow payloads, weak idempotency keys, unsafe preview URLs, and invalid native results.

## Required controls

- Membership Core checks at open, autosave, upload, preview, submit, schedule, edit, and publish boundaries.
- WordPress nonces and same-origin writes at every future HTTP controller.
- Authenticated-subject binding, ownership, and IDOR protection.
- Sanitization before storage and context-appropriate escaping.
- Prepared SQL for future tables.
- Bounded rate limits.
- Safe redirects.
- MIME, signature, size, and ownership validation.
- Short-lived private previews.
- Audit metadata without full sensitive bodies.

## Phase 22E server-side workflow boundary

The Phase 22E coordinator is an internal PHP service, not a public REST, AJAX, or form endpoint. Interactive public PHP functions bind to `get_current_user_id()` and do not accept a caller-supplied subject ID.

The coordinator checks Safe Mode, Membership Core eligibility, captured workflow contract, central capability, adapter-specific authorization, and native availability on every operation. Ineligible accounts are denied before native runtime methods execute.

Workflow payloads are not stored by File 22. They are limited to scalar values, `null`, and bounded nested arrays. Objects, resources, excessive nesting, non-finite floats, and encoded payloads over 1 MiB are rejected before native invocation.

Native references are opaque identifiers, not ownership proof. Status and canonical URL operations pass the authenticated subject to the native owner. The native module must deny a reference that the subject does not own or may not view.

Preview and canonical URLs must be relative internal routes or absolute same-origin HTTPS URLs. Draft creation requires an explicit draft-specific state. Schemas use a fixed field/property vocabulary and cannot carry data-bearing defaults or arbitrary rendering metadata.

Native error diagnostics use only a fixed File 22 allowlist. Arbitrary native-controlled codes become `native_error`. Exception class names are not emitted; exception diagnostics use only adapter key, controlled operation key, and the fixed `native_exception` code. Payloads, native messages, native data, and raw exception messages are excluded.

The native owner remains responsible for durable idempotency reconciliation, secure draft storage, protected evidence, uploads, moderation, publication, ownership checks, and canonical records.

## Patient Case restrictions

A public Patient Case is an anonymized educational publication, not a private clinical record. Plaintext browser storage is prohibited. Consent evidence belongs to a dedicated native privacy owner, and File 22 stores only an opaque consent reference.

## Secure media ownership

PDF bytes go directly to File 12 secure storage. Identity evidence stays with Membership Core or doctor verification. Marketplace contacts come from the verified seller profile. File 22 retains only opaque references and status metadata where needed.

## Adapter isolation

Every adapter call that can execute native code is isolated. A thrown `Throwable` disables only that adapter operation for the request and records fixed privacy-safe diagnostics.

## Account enforcement

Rejected, suspended, expired-document, and otherwise ineligible accounts are denied centrally before native adapter runtime methods. Administrators remain subject to explicit suspension and emergency disable controls.

## Release gate

No release proceeds with unresolved critical or high-severity privilege escalation, confused-deputy subject substitution, CSRF, XSS, IDOR, MIME spoofing, path traversal, SSRF, open redirect, SQL injection, duplicate-submit races, draft theft, preview leakage, payload leakage, diagnostic leakage, weak idempotency, unsafe native result envelopes, or upload ownership theft.
