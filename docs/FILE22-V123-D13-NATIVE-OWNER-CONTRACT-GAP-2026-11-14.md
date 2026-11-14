# File 22 V12.3 D13 — Native media integration boundary and release-blocking contract gap

**Recorded:** 2026-11-14; **scope:** source evidence, *not* live deployment evidence.

## Exact source snapshots reviewed

| File | Source repository | Git tree SHA | Evidence |
| --- | --- | --- | --- |
| File 22 | `sabri-universal-post-composer` | baseline `ed672a0d271f10e2cf224951d9f3622a44a70152` | `includes/contracts/interface-upload-token-adapter.php`; `includes/presentation/class-workflow-surface.php`; `includes/core/class-workflow-coordinator.php` |
| File 00 | `00-sabri-membership-core` | `1fad051c86d530d558be3eb1890d3c6e67bce6e8` | `source/sabri-membership-core/sabri-membership-core.php`: source `1.2.44`, DB `1.4.5`, contract `1.2.3` |
| File 20 | `20-sabri-unified-application-shell` | `8a4dbcaf4fef8e926b9b834ecfde16c21a0f00ca` | `sabri-unified-application-shell/sabri-unified-application-shell.php`: source `1.4.17` |
| File 21 | `sabri-complete-home-news-feed` | `626b380328d4400014504e679032291cd023bbdb` | `sabri-complete-home-news-feed.php`: source `1.0.5` |
| File 17 | `17-sabri-network` | `8ae656e51796d1f05865d8be5dca2480443d79ca` | `sabri-network/includes/class-sn-file-transfer*.php` (resumable transfer owner, implementation requires dedicated verification) |
| File 24 | `24-sabri-platform-security-privacy-compliance-and-resilience-center` | `a5b8d49968a7a5a7d6f3f4655bea541bf38a9acb` | upload security and privacy assurance source; no live assertion |

These are repository-source snapshots. **Neither the activated plugin versions nor the deployed artifact checksums of any of these modules have been checked.**

## Critical native integration discovery

`File 22` only renders its upload UI when the selected **native adapter** implements `Sabri\\UniversalComposer\\Contracts\\Upload_Token_Adapter`. Its interface requires native `begin_upload`, `complete_upload` and `cancel_upload`; File 22 never owns media bytes or post/publication truth.

The current File 21 source `includes/class-universal-composer-subject-schema-adapter.php` declares `implements Lifecycle_Adapter, Diagnostic_Adapter`, **not** `Upload_Token_Adapter`. Its `UniversalComposerWorkflowAdapter` and `UniversalComposerStructuredWorkflowAdapter` likewise declare Lifecycle/Diagnostic without upload-token methods. The source `build_schema()` exposes content, feed, visibility, moderation and schedule fields, but **not** native `audio_duration`, `audio_transcript`, `image_alt_text`, `crop` or resize fields.

**Conclusion:** A correct audio control in File 22 is **not sufficient** for the File 21 social publishing path. No upload UI is correctly shown for that adapter until File 21 (or an explicitly authorized native media adapter) implements the required versioned contract. This source evidence establishes a **cross-file dependency gap**, not an incident in the deployed runtime.

## Proposed versioned native D13 contract (requires native-owner acceptance)

1. Native workflow itself must validate the authenticated File 00 subject and current capability on **each** begin/complete/cancel; link upload to an owned native draft, scoped media purpose, MIME/type, size, expiry and checksum. Refuse absent/expired/forged/stale references.
2. For image uploads, provide an owner-accepted, per-content-type schema for alt text, caption, crop, resize, aspect ratio, EXIF strip and preview. Secure transformations and persistent derivatives must be owned by CF 04 or the relevant native media owner; File 22 may only coordinate.
3. For audio uploads, provide native fields for transcript, duration, title/metadata, language and rights/consent. Accessibility authoring does not imply the audio file was scanned, transcribed or approved. Unreviewed machine transcripts must be visibly marked.
4. Native upload **complete** means bytes/transmission receipt, **not** publicly approved media. No public media URL before scan/quarantine, rights, consent and safety gates. Explicit status transitions: issued -> uploading -> uploaded -> pending_scan -> cleared/rejected -> publishable, owned by the native provider; File22's bounded upload token metadata must not masquerade as those states.
5. File 17 remains authoritative for resumable/chunked transfer. A 1GB-file entitlement does not authorize File 22 to use one giant in-browser `arrayBuffer` nor to duplicate storage. Require capability/size boundaries and interrupted-upload continuation token contract.
6. CF04/native owner strips EXIF/location metadata before shareable derivatives; enforces signed access and revoke; File 24 verifies privacy/egress and scanner freshness. Logs contain codes/opaque references, not patient media, captions or content.
7. Versioned integration tests must pass for File 21 social/publication, structured cases, authenticated media owner unavailable/deactivated, File 00 revocation, File 17 interrupted transfers, and File 24 scan fail-closed.
8. The D13 native handshake cannot expand permissions, roles, publication authority or public URL visibility; File 22 may display native capabilities only after current authorization.

## Additional File 17 source boundary checked

`17-sabri-network/sabri-network/includes/class-sn-file-transfer.php` declares an explicit **1 GiB per file** limit, encrypted private/resumable transfer and chunk sizes from **1 MiB to 16 MiB** (default **8 MiB**). `class-sn-file-transfer-part-1.php` registers a private transfer contract on `sn_network_file_transfer_contract_registered`, including `resumable=true`, `verified_users_only=true`, `scan_contract=sn_network_transfer_scan_result` and `binary_processing_owner=cf-04-after-activation`.

`class-sn-fourth-fresh-media-hardening.php` requires a separate approved media codec/container/duration validator for private audio/video; its voice-note transcripts are encrypted and source-labelled `user_supplied_unverified`. **These are private network/communications features. They are NOT proof of a File 21 public-post media-upload adapter, nor a license to move File 17 private media into the public feed.**

`File 22` still computes a whole-file SHA-256 with `file.arrayBuffer()` in its direct upload browser path. The unverified large-media/resumable design therefore needs a bounded-memory, versioned File 17/CF04 owner contract instead of assuming 1 GiB uploads can safely use the direct File 22 XHR route. This risk is **open**, and no unjustified capacity limit or private-to-public mapping has been added.

## Source changes proposed in File 22 PR #53

- Separate audio picker (behind native `Upload_Token_Adapter`) and common token-bound upload bridge.
- Media upload is scoped **inside** the media stage.
- Native schema keys including audio duration/transcript, image alt/crop/resize are grouped into the Media stage, while consent/rights and patient-safety keys take priority in Compliance.
- Missing upload provider shows a factual unavailable message; no fake upload success.
- Focused source regression tests for media picker, metadata grouping and provider-absent behavior.
- No media tables, generic database migration, new public routes, arbitrary upload URL allowlists or fictional storage/scan claims added.

## V12.3 acceptance conditions

- WP-23/24 **PARTIAL** until a real native image/audio provider is registered, independently tested and its rights/scan status verified.
- WP-25/26 **BLOCKED ON OWNER TESTS** until CF04/File17/File24 and real staging are available.
- T22-26..30 **UNVERIFIED**, not PASS.
- D12/CV-221/222 **DECISION BLOCKED** pending explicit Founder approval; must not be absorbed into D13.
- G10/G11 remain closed until exact deployed/staging reality and Founder release approval.

## P0 evidence still needed to unblock deployment

- Hostinger installed-file and plugin list, active runtime versions, relevant options/migrations/tables/logs, artifact checksum.
- Deployment parity across File 00/20/21/22/17/24.
- Read-only media provider capability contract from the actual active File 21/CF04 build; schema, accepted media MIME, size, metadata, scan and signed-URL lifecycle.
- Staging negative/positive role/media/scan tests and verified backups/recovery.
- Independent release review, exact-head green CI and controlled live re-test.

Repository HEAD (main baseline): `ed672a0d271f10e2cf224951d9f3622a44a70152`.
Candidate PR HEAD: record from GitHub at final freeze.
Deployed Version: UNVERIFIED.
DB Version: UNVERIFIED.
Migration State: UNVERIFIED.
Live Verification Status: NOT PERFORMED.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
