# Privacy

File 22 applies data minimization. It stores only orchestration data necessary to route an authorized creation workflow.

## Data not owned by File 22

- identity and professional evidence;
- patient-consent evidence;
- private clinical records;
- secure PDF bytes;
- Marketplace verified contact identity;
- native moderation notes and permanent content records.

File 22 may retain only opaque native references, adapter keys, status, timestamps, and privacy-safe audit metadata when required.

## Sensitive drafts

Patient Case and other sensitive drafts remain native-owner/server authoritative. Plaintext `localStorage` is prohibited. File 22 now provides bounded crash recovery through IndexedDB when Web Crypto is available: draft form payloads are AES-GCM encrypted with a non-exportable browser-profile key, scoped to a pseudonymous account token and adapter, expire after two hours, and never include media bytes. A recovery record is removed after a confirmed native save/submission, when it is stale, when another account scope is detected, and when the standard site logout control is used from the Composer. A session mismatch or newer server draft blocks automatic overwrite. Browsers without IndexedDB/Web Crypto continue with server autosave only and File 22 does not claim durable offline recovery. Shared-device users are warned to sign out.

## Public/private separation

The Create page, drafts, previews, adapter diagnostics, and status endpoints are noindex, noarchive, no-cache, and excluded from public search. The direct shortcode render boundary sets WordPress page/object/database no-cache constants, standard no-cache headers, `Vary: Cookie`, and the LiteSpeed no-cache signal. Public canonical content remains the responsibility of its native owner.

A template, widget, or programmatic shortcode invocation that occurs after response output has begun cannot safely establish that private response boundary. File 22 therefore fails closed with a generic, data-free notice and does not evaluate account status, adapter availability, drafts, or publication state. When a secure direct render occurs after `wp_head`, File 22 prints its already registered/enqueued stylesheet once so the protected surface does not silently render without its accessibility and state styling.

## Create-page URL trust

A published page is accepted as the canonical Create surface only when it contains the File 22 shortcode and its resolved permalink is an internal relative route or an absolute same-origin HTTPS URL without credentials or a port mismatch. A filtered external, downgraded, protocol-relative, credential-bearing, or malformed permalink is not a Create-page candidate and is never returned to File 20, login redirects, or users.

## Retention

Retention is content-class specific. Sensitive abandoned drafts require shorter retention than ordinary drafts. Orphan temporary references are cleaned in bounded background jobs. Legal or security audit retention is documented separately from user-content retention.

## WordPress privacy integration

File 22 registers WordPress personal-data export and erasure callbacks for its orchestration sessions, submission/reconciliation metadata, upload-token metadata, and privacy-safe audit records. Erasure deletes disposable File 22 metadata and de-identifies audit rows. Records that are still queued, reconciling, or retained for dead-letter investigation may be held temporarily so an uncertain native write is not duplicated or abandoned; the eraser reports that exception explicitly. Native content, media, consent evidence, moderation records, and permanent publication records remain the responsibility of their canonical owners.
