# File 22 — 13 November 2026: upload-token expiry must be checked at use time

## Exact repository evidence
Source baseline: `e8da36355f4cf94f49c960ee4f8330ca1e313722`, GitHub-only File22 candidate `1.0.0-rc.3`. Governing V12.1 WP-041, WP-043, WP-044 (upload token, native media reference, revocation/expiry), CV-056, staging T-31–T-35.

## D22-SRC-12 — source-proven expiry bypass if cron is delayed
`Upload_Token_Store::get_owned()` selected metadata only by UUID+user ID, without checking `expires_at`; `get_by_native_reference()` used by `register()` also accepted existing expired metadata. An upload whose 24-hour active TTL (or 30-day terminal retention TTL) had elapsed could remain usable until `cleanup_expired()` happened to execute. Delayed WordPress cron must not silently extend an upload authorization window.

This is evidence of a source validation gap, **not** evidence of live exploitation or proof that native storage URLs themselves have expired.

## Correction
- A single `active_record()` validates the exact UTC `YYYY-MM-DD HH:MM:SS` database expiry and requires `expires_at > now`.
- Both `get_owned()` and `get_by_native_reference()` call the same validator; no special bypass for retries.
- Expired/malformed rows return a controlled `supc_upload_expired` WP_Error.
- `transition()` already passes through `get_owned()` and now cannot modify an expired record.
- Retain native file-byte ownership, no new schema, no media data copying, no rollback of the native provider's independent authorization/expiry.

## New regression
`tests/UploadTokenExpiryAtUseTest.php` executes real store methods with a synthetic WPDB row: expired owned lookup denied, expired completion transition denied without SQL write, register retry cannot revive expired native ID, valid issued/completed rows remain readable during TTL, malformed expiry fails closed. These are unit-level source tests, not real MySQL/WordPress/staging tests.

## Follow-on acceptance
Confirm with File21 and CF04 native media contract whether a URL can be signed, revoked or served after metadata expiry. Test single session, second account, late cron, UTC clock drift, repeated upload, provider failure, quarantine, CDN cache purge and rollback on staging. No automatic promotion to published content. An expired File22 token alone does not revoke a native signed URL; the native owner is required to enforce that invariant.

Repository HEAD baseline: `e8da36355f4cf94f49c960ee4f8330ca1e313722`.
Deployed Version: UNVERIFIED.
DB Version: UNVERIFIED.
Migration State: UNVERIFIED.
Live Verification Status: NOT PERFORMED.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
