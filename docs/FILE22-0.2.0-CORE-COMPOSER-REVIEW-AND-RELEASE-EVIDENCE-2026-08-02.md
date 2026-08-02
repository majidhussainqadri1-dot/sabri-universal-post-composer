# File 22 — Core Composer 0.2.0 Review and Release Evidence

Date: 02 August 2026  
Branch: `feat/file22-core-composer-0.2.0-2026-08-02`  
Pull request: Draft PR #23  
Status: Coded, Packaged, Automated-QA Green candidate; not staging-accepted, live-deployed, or operational.

## Governing release truth

File 22 is the universal creation facade and orchestration layer. Canonical content, media, consent, moderation, publication state, durable native drafts, and canonical URLs remain with native owners. The seven completion statuses remain separate: Specified, Coded, Packaged, Automated-QA Green, Staging-Accepted, Live-Deployed, and Operational.

The File 21 integrated-staging gate is:

- package identity: `1.0.3.2` or later;
- stable runtime/API: `1.0.3`;
- the supplied `1.0.3.1 R2` artifact is not accepted as the final integrated-staging dependency.

The File 20 Create contract compatibility anchor remains `1.0.1`. File 00 minimums for this candidate are plugin `1.2.3`, database schema `1.2.0`, and contract `1.1.2`.

## Implemented scope

- schema-driven accessible browser Composer;
- private REST namespace `sabri-composer/v1`;
- native-owner draft creation and autosave;
- validation, private preview, idempotent submit, and native status retrieval;
- metadata-only `wp_supc_sessions` orchestration table;
- compare-and-swap lock versions and bounded session leases;
- per-session operation lock;
- idempotency identity persisted before native submission;
- no File 22 draft bodies, patient consent, identity evidence, or media bytes in session storage;
- no browser `localStorage`, `sessionStorage`, or `IndexedDB` use;
- no-cache, noindex, nonce, ownership, rate-limit, and request-size boundaries;
- deterministic exact-head package with embedded and external SHA-256 manifests.

## Review round 1 — implementation and correction

The first implementation review found and corrected:

1. stale File 00 dependency assertions;
2. unsafe dynamic SQL table identifiers under WordPress Coding Standards;
3. stale `0.1.1` release workflow identity;
4. absent browser workflow controller despite the server coordinator;
5. missing metadata-only session persistence and concurrency control;
6. missing exact-head deterministic package evidence.

All corrections were retested through PHPUnit, PHPStan, WordPress Coding Standards, PHP 8.1/8.2/8.3 syntax, repository contracts, JavaScript syntax, manifest checks, and archive integrity checks.

## Review round 2 — fresh adversarial review and correction

A separate fresh review tested negative and degraded paths rather than repeating the first checklist. It found and corrected:

1. private workflow rendering after response headers had already been sent;
2. database installation attempts on ordinary public requests;
3. wildcard ambiguity in table-existence discovery;
4. adapter schema/version drift during an active session;
5. native `WP_Error` detail loss caused by reading a noncanonical property;
6. REST-root trailing-slash ambiguity;
7. debounce-only autosave that could postpone saves indefinitely during continuous typing;
8. popup-blocked private previews after asynchronous processing;
9. weak unload protection tied to a display string instead of explicit dirty/busy state.

Regression evidence was added for these corrections and the complete exact-head CI suite was rerun.

## Automated evidence

For every new source head, the exact-head workflow performs and records:

- source-head equality verification;
- cumulative PHPUnit suite;
- PHPStan;
- WordPress Coding Standards;
- PHP 8.1, 8.2, and 8.3 syntax;
- repository and dependency-lock contracts;
- JavaScript syntax;
- deterministic ZIP creation;
- embedded `MANIFEST.sha256` verification;
- external archive SHA-256 verification;
- ZIP CRC/integrity verification.

The authoritative source commit, archive checksum, embedded manifest, and test report are the files emitted together by the successful `File 22 Core Composer 0.2.0` GitHub Actions run. They must always be treated as one indivisible provenance set. This source document intentionally does not hard-code its own package checksum, because modifying an included source document necessarily changes the deterministic archive.

## Mandatory remaining acceptance gates

This candidate must not be called production-complete until all of the following pass on Hostinger staging with exact artifacts:

- File 21 package identity `1.0.3.2` and stable runtime/API `1.0.3` integration;
- File 20 current exact-head Create contract `1.0.1` integration;
- fresh install, upgrade, activation, deactivation, uninstall safety, and rollback;
- Founder, Administrator, trusted doctor, verified doctor, pending doctor, suspended user, and logged-out visitor workflows;
- desktop, tablet, mobile, RTL, keyboard, screen reader, 400% zoom, contrast, and WCAG 2.2 AA acceptance;
- active theme, LiteSpeed/Hostinger cache, object cache, and companion-plugin runtime tests;
- backup restoration and rollback rehearsal;
- approved production smoke test and post-deployment monitoring.

## Release decision

Known defects found in the two source-review rounds above were corrected and automated QA is green on the reviewed branch heads. This proves a controlled code/package candidate only. It does not prove staging acceptance, live deployment, operational readiness, or absolute defect-free status.
