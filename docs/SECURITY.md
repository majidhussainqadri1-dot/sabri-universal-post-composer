# Security and Privacy Baseline

File 22 fails closed for authorization, suspension, invalid adapter contracts, unsafe routing, ambiguous ownership, expired sessions, invalid nonces, and unsupported native modules.

## Required controls

- Membership Core checks at open, autosave, upload, preview, submit, schedule, edit, and publish boundaries.
- WordPress nonces and same-origin writes.
- Ownership and IDOR protection.
- Sanitization before storage and context-appropriate escaping.
- Prepared SQL for future tables.
- Bounded rate limits.
- Safe redirects.
- MIME, signature, size, and ownership validation.
- Short-lived private previews.
- Audit metadata without full sensitive bodies.

## Patient Case restrictions

A public Patient Case is an anonymized educational publication, not a private clinical record. Plaintext browser storage is prohibited. Consent evidence belongs to a dedicated native privacy owner, and File 22 stores only an opaque consent reference.

## Secure media ownership

PDF bytes go directly to File 12 secure storage. Identity evidence stays with Membership Core or doctor verification. Marketplace contacts come from the verified seller profile. File 22 retains only opaque references and status metadata where needed.

## Adapter isolation

Every adapter call that can execute native code is isolated. A thrown `Throwable` disables only that adapter for the request and records privacy-safe diagnostics.

## Account enforcement

Rejected, suspended, and expired-document accounts are denied centrally before adapter checks. Administrators remain subject to explicit suspension and emergency disable controls.

## Release gate

No release proceeds with unresolved critical or high-severity privilege escalation, CSRF, XSS, IDOR, MIME spoofing, path traversal, SSRF, open redirect, SQL injection, duplicate-submit races, draft theft, preview leakage, or upload ownership theft.
