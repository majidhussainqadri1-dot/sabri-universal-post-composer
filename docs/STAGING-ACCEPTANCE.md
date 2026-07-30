# File 22 Controlled Staging Acceptance

No merge, package promotion, production deployment, or completion claim is permitted until every applicable item below has dated evidence, tester identity, environment details, expected result, actual result, screenshots or logs where useful, and an explicit PASS/FAIL disposition.

## 1. Environment and provenance

- Record staging URL, WordPress version, PHP version, database version, active theme, cache stack, object cache, and server timezone.
- Record exact source commit, candidate ZIP SHA-256, installable file manifest, File 00 version, File 20 Create contract version/owner, File 21 version and commit, and File 22 API versions.
- Verify the candidate files match the reviewed GitHub head and contain no development dependencies, tests, logs, credentials, backups, uploads, patient data, or identity evidence.
- Capture a verified database and files backup before first install and before every upgrade rehearsal.

## 2. Installation, upgrade, activation, and load order

Test both a fresh WordPress clone and an upgrade from the last accepted File 22 state.

Activation/load-order permutations:

1. File 00 → File 20 → File 21 → File 22.
2. File 00 → File 21 → File 22 → File 20.
3. File 00 → File 22, then File 20 and File 21.
4. File 21 and File 20 active before File 00, then activate File 00 and File 22.
5. Deactivate/reactivate each dependency individually.
6. Simulate duplicate/stale File 22 public API functions and File 20 contract functions; File 22 must fail closed without a mixed contract or fatal public site.

Verify:

- File 22 refuses activation without compatible File 00;
- no duplicate page, role, option, capability, cron event, content record, or adapter registration is created;
- existing native File 21 drafts, posts, URLs, moderation state, and fallback route remain intact;
- a failed activation or upgrade leaves public reading operational;
- `main`/candidate topology and migration records match the reviewed cumulative PR.

## 3. Create page and cache/indexing privacy

Test the canonical mapped page, one noncanonical page containing `[sabri_universal_composer]`, multiple published shortcode pages, a missing mapping, a stale mapping, and an unrelated public page.

Every page that actually renders the Composer must:

- send no-cache headers;
- define WordPress page/object/database no-cache constants and emit `Vary: Cookie`;
- emit the LiteSpeed no-cache signal where LiteSpeed Cache is active;
- send `X-Robots-Tag: noindex, nofollow, noarchive`;
- produce equivalent `wp_robots` directives;
- remain excluded from LiteSpeed page cache, CDN cache, browser shared cache, search, sitemap, archive, and public feed output;
- never serve one signed-in role's personalized cards or notices to another user or to a logged-out browser.

The unrelated public page must retain its normal cache/indexing policy.

Repair tests:

- dry run writes nothing;
- one valid candidate maps safely;
- multiple candidates require explicit selection;
- invalid/non-page/private/draft/trash candidates fail closed;
- concurrent repair requests produce one bounded lock and no duplicate managed page;
- persistence failure is reported and does not claim success;
- unrelated pages are never edited, overwritten, trashed, or deleted.

## 4. Complete account, role, status, and document matrix

Test separate real accounts for:

- Founder;
- Administrator who is not the Founder;
- approved verified doctor;
- approved permitted unverified doctor;
- approved doctor lacking `sabri_feed_create_posts`;
- student;
- patient;
- editorial-only user;
- roleless user;
- logged-out visitor;
- pending/unapproved account;
- rejected account;
- suspended Founder/Administrator/doctor;
- expired-document Founder/Administrator/doctor;
- deleted or invalid user ID.

For every account, verify desktop header, mobile navigation, File 22 page, direct PHP workflow calls, File 21 native route, System Check wording, and cache isolation agree with the central policy. No role, File 20 setting, or native adapter may expand a denial from File 00.

Pass a different numeric subject ID through the File 20 presentation filter in both directions. The result must remain bound to `get_current_user_id()` and must never disclose or borrow another account's availability.

## 5. File 20 Create producer parity

Test File 20 modes `create`, `auto`, and `doctors` with:

- Header enabled/disabled;
- Header Create enabled/disabled;
- File 20 Safe Mode and Emergency Disable;
- File 22 absent, incompatible, public-API collision, Safe Mode, missing page, ambiguous page, and no available adapter;
- one authorized and one unauthorized user in desktop and mobile layouts.

Desktop and mobile must use the same final decision. File 22 System Check must report File 20 contract version, owner, function ownership, functions, and readiness with controlled codes.

## 6. File 21 route and direct workflow contract

Verify the release-critical `social_publication` adapter has:

- exact key and native owner;
- compatible Adapter and Workflow API versions;
- `Diagnostic_Adapter` and full `Workflow_Adapter` support;
- subject-aware schema extension;
- native draft support;
- exact central capability, group, privacy class, minimum and actual native version;
- working native route and permanent File 21 ownership.

State classification tests:

- File 21 unavailable, Safe Mode, Composer disabled, missing class, or missing route is `unavailable`, never `permission denied`;
- adapter-specific denial is `denied`, never `unavailable`;
- one broken adapter does not break healthy adapters, Header, public feed, or native fallback;
- route-only or incomplete File 21 adapter is a release failure, not a PASS.

## 7. Subject-aware schema and payload enforcement

For Founder/Administrator, institutional choices may appear only when native policy permits them. For doctors and every noninstitutional role, `Founder Update` and `Platform News` must be absent.

Test all declared field types and boundaries:

- unknown field;
- missing required field;
- wrong scalar/array type;
- invalid select and multiselect choice;
- number below/above bounds;
- invalid email, non-HTTP scheme, credential-bearing URL, impossible calendar date, hour `24`, minute/second overflow, timezone beyond `±14:00`, and opaque reference;
- object, resource, closure, nonfinite float, excessive nesting, and payload over 1 MiB;
- schema over 256 KiB, over 100 fields, over 100 choices, unknown schema property, data-bearing default, malformed code, invalid privacy class.

All invalid requests must fail before native mutation. Static health must validate only the role-neutral base schema; interactive calls must validate the authenticated subject schema.

## 8. Draft, preview, submission, status, and IDOR matrix

For at least two authorized users and one unauthorized user:

- create a new native draft;
- resume own draft;
- deny another user's draft reference;
- deny malformed, missing, foreign-module, deleted, pending, scheduled, published, rejected, and trashed references in mutable operations;
- validate and preview only the authenticated subject's draft;
- enforce signed preview subject, signature, absolute expiry, ten-minute native policy, and File 22 maximum thirty-minute envelope;
- reject expired, forged, cross-user, external-host, HTTP, credential-bearing, protocol-relative, wrong-port, and control-character URLs;
- submit only after an opaque native draft reference exists;
- replay same key/same payload without duplicate mutation;
- return conflict for same key/different payload;
- test concurrent submission, native mutation followed by completion-write failure, retry reconciliation, stale processing lease, stale execution lock, and retention cleanup;
- ensure status and canonical URL enforce native ownership/visibility for private and public posts;
- verify no raw key, post body, patient narrative, native error message/data, exception class/message, or stack trace enters File 22 output or diagnostics.

## 9. Browser, responsive, accessibility, and RTL acceptance

Test current stable Chrome, Edge, Firefox, Safari where available, Android Chrome, and iOS Safari.

Required checks:

- keyboard-only navigation, visible focus, logical focus order, and no keyboard trap;
- screen-reader names, headings, landmarks, list/card semantics, table captions, scoped headers, notices, and repair form labels;
- 200% and 400% zoom/reflow without horizontal page scrolling or lost controls;
- narrow mobile, tablet, desktop, and large desktop widths;
- Windows forced-colors/high-contrast mode;
- `prefers-reduced-motion`;
- light and dark surrounding themes;
- complete Urdu RTL direction, reading order, icon/arrow direction, focus, truncation, and mixed English code labels;
- WCAG AA contrast for text, controls, links, focus indicators, notices, and visited states.

## 10. System Check and privacy-safe operations

Verify `Tools → Composer Health` reports actionable controlled codes for:

- Membership Core;
- Create page mapping;
- registry errors;
- File 22 public API version/owner/function ownership;
- File 20 Create contract;
- File 21 complete workflow contract and subject schema extension;
- native availability;
- current-user Create-surface presentation diagnostics.

It must not display or expose through `supc_system_check_report`:

- user ID, name, email, role list, identity/document state details;
- post ID, URL, native reference, raw idempotency key;
- patient/clinical content, consent evidence, payload, native message/data;
- exception class/message, path, stack trace, SQL, token, nonce, secret, or credential.

## 11. Backup, rollback, and recovery proof

Perform both:

1. plugin-deactivation rollback; and
2. full verified backup restoration.

Prove that rollback:

- restores File 20/File 21 fallback Create behavior;
- leaves native posts, drafts, media, moderation, consent, identity, and clinical records untouched;
- does not delete unrelated pages;
- preserves or explicitly unpublishes the managed Create page only after confirmation;
- restores prior File 22 options and mappings from the verified snapshot when required;
- removes no capability that existed before the candidate installation;
- recovers from interrupted repair, stale lock, interrupted submission, and failed upgrade.

Record restoration duration, commands/UI actions, checksums, database/file validation, and final smoke tests.

## 12. Final acceptance evidence

Acceptance requires:

- all automated exact-head checks green on the final reviewed commit;
- dependency lock and candidate manifest/checksum tied to that commit;
- all manual cases above PASS or an explicitly approved nonapplicable disposition;
- independent post-implementation review;
- correction and post-correction verification of every finding;
- Founder review and explicit written authorization.

Automated checks, a mergeable PR, or a generated ZIP alone do not authorize merge or live deployment.
