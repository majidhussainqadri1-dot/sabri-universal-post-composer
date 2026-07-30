# Phase 22C — Universal Create Gateway Surface

## Purpose

Phase 22C turns the File 22 shortcode into a usable, responsive, accessible content-type gateway while preserving native module ownership.

The surface does not create, autosave, validate, moderate, schedule, or publish permanent content. Selecting a content type opens the authorized native module route supplied by its adapter.

## User states

The surface explicitly handles:

1. File 22 Safe Mode or emergency disable;
2. logged-out visitor;
3. logged-in account without central creation permission;
4. authorized account whose native module or route is unavailable;
5. authorized account with one or more safe native routes;
6. adapter metadata or route failure without breaking healthy adapters.

Permission denial and integration failure are separate user states and must not be reported as the same condition.

## Content grouping

Adapters are displayed in deterministic registry order and grouped into:

- `publishing`;
- `knowledge`;
- `media`;
- `commerce`;
- `other` for compatible custom groups.

Unknown group keys are not rendered as arbitrary headings. They are mapped to the controlled `other` group and recorded as warning-level diagnostics.

## Privacy indicators

Each valid card displays one controlled privacy label:

- `public` → Public content;
- `private` → Restricted content;
- `sensitive` → Sensitive workflow.

Unknown privacy classifications fail closed. The invalid adapter is omitted, a privacy-safe error diagnostic is recorded, and unrelated healthy adapters remain available. File 22 never relabels unknown native privacy behavior as if it were known.

## Route safety

WordPress redirect validation is the first validation layer, not the complete route policy.

File 22 accepts only:

- a relative internal path beginning with one `/`;
- or an absolute HTTPS URL whose host and effective port exactly match the current WordPress home origin.

File 22 rejects:

- protocol-relative routes;
- external hosts, including hosts allowed by another plugin filter;
- HTTP downgrade routes;
- mismatched ports;
- URL credentials;
- backslashes;
- control characters.

An invalid route is omitted, produces an `invalid_route` System Check diagnostic, and does not disable unrelated adapters.

## System Check

Create-surface diagnostics are included in the built-in `supc_system_check_report` output.

The report contains only:

- pass, warning, or fail status;
- total count;
- error count;
- warning count;
- non-sensitive diagnostic codes.

No user, content, URL, label, patient data, or adapter payload is included.

## Accessibility baseline

- semantic sections and list structure;
- unique heading IDs;
- keyboard-accessible native links;
- visible focus indicators;
- Sign In action contrast of at least 4.5:1 for normal text;
- explicit visited-state color control;
- no JavaScript dependency for core navigation;
- responsive one-, two-, and three-column layouts;
- reduced-motion support;
- forced-colors support;
- logical CSS properties for RTL/LTR compatibility;
- descriptive labels, privacy state, and Continue action text.

## Asset policy

The Create surface loads its CSS only on the resolved Create page or a page containing `[sabri_universal_composer]`. It uses WordPress-bundled Dashicons and no remote font, CDN, tracker, or third-party runtime asset.

## Ownership statement

The user-facing surface displays:

> One gateway, one native record.

File 22 routes the user to a native workflow and does not create a duplicate permanent content record.

## Acceptance

- Founder, Administrator, and authorized doctor see only adapters they may use;
- suspended, rejected, expired-document, patient, student, unauthorized, and logged-out states remain denied according to central policy;
- unavailable native modules are not presented as permission denial;
- unsafe routes are absent from HTML;
- invalid privacy adapters are absent from HTML;
- adapter labels and descriptions are escaped;
- one broken adapter does not break the gateway;
- System Check reports presentation diagnostics without sensitive data;
- all core navigation works without JavaScript;
- 200% and 400% zoom, keyboard, forced colors, reduced motion, RTL, mobile, tablet, and desktop are verified on staging;
- selection opens the native owner route and creates no File 22 content copy.

## Deferred work

The following are not part of Phase 22C:

- direct File 22 forms;
- direct autosave or draft storage;
- upload orchestration;
- preview sessions;
- submission idempotency persistence;
- moderation or scheduling;
- native adapter workflow execution.

These require separately reviewed future phases and versioned workflow contracts.
