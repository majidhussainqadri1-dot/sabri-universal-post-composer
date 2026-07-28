# Security and Privacy Baseline

## Security posture

File 22 fails closed for authorization, unsafe media, ambiguous ownership, expired sessions, invalid nonces, and unsupported native adapters.

## Required controls

- Sabri Membership Core capability and verification checks at open, autosave, upload, preview, submit, schedule, edit, and publish boundaries.
- WordPress nonces and same-origin write requests.
- Ownership and IDOR protection for sessions, drafts, previews, uploads, and native references.
- Sanitization before storage and context-appropriate escaping at output.
- Prepared SQL for future File 22 tables.
- Rate limits for session creation, autosave, upload, preview, submission, mention, and link-preview actions.
- Safe redirects and canonical native URLs.
- MIME, extension, signature, size, and ownership validation for media.
- Short-lived signed preview access; noindex, noarchive, and no-cache.
- Audit metadata without copying complete sensitive content into logs.

## Patient Case restrictions

A public Patient Case is an anonymized educational publication, not a private clinical record.

File 22 must not store Patient Case drafts in plaintext `localStorage`. The default for sensitive Patient Case drafts is server-side native storage. Any future offline support requires encrypted, short-lived, device-bound IndexedDB storage, automatic logout purge, inactivity expiry, and explicit shared-device warnings.

The system must warn about phone numbers, email addresses, identity numbers, passport-like patterns, exact addresses, medical-record numbers, GPS metadata, and other identifiers. Ignoring a warning requires a reason and enhanced review.

Consent evidence must be stored by a dedicated native privacy owner. File 22 stores only an opaque consent record reference.

## Secure media ownership

- PDF bytes go directly to File 12 secure storage.
- Identity and professional evidence remain with Membership Core or the doctor-verification owner.
- Patient consent evidence remains with the dedicated privacy owner.
- Marketplace contact information is sourced from the verified seller profile rather than arbitrary unverified numbers.
- File 22 may retain only opaque upload tokens, status, owner, adapter key, expiry, and native reference.

## Offline behavior

Offline mode must never claim publication success. Text recovery for non-sensitive content may be supported later with conflict detection and short retention. Sensitive content is server-only by default.

## Link preview and SSRF

Any future remote preview fetcher must restrict protocols, block loopback and private networks, limit redirects, response size, and timeouts, sanitize returned markup, and execute no remote scripts.

## Logging

Logs may contain actor ID, action, adapter key, session reference, native reference, result code, and timestamp. Logs must not contain full patient narratives, consent documents, identity evidence, access tokens, encryption keys, or complete unpublished bodies.

## Release gate

No release may proceed with unresolved critical or high-severity findings involving privilege escalation, CSRF, stored or reflected XSS, IDOR, MIME spoofing, path traversal, SSRF, open redirect, SQL injection, duplicate-submit races, draft theft, preview leakage, or upload ownership theft.
