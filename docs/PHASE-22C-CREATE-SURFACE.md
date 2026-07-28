# Phase 22C — Universal Create Gateway Surface

## Purpose

Phase 22C turns the File 22 shortcode into a usable, responsive, accessible content-type gateway while preserving native module ownership.

The surface does not create, autosave, validate, moderate, schedule, or publish permanent content. Selecting a content type opens the authorized native module route supplied by its adapter.

## User states

The surface explicitly handles:

1. File 22 Safe Mode or emergency disable;
2. logged-out visitor;
3. logged-in account with no authorized and available adapter;
4. authorized account with one or more safe native routes;
5. adapter metadata or route failure without breaking other healthy adapters.

## Content grouping

Adapters are displayed in deterministic registry order and grouped into:

- `publishing`;
- `knowledge`;
- `media`;
- `commerce`;
- `other` for compatible custom groups.

Unknown group keys are not rendered as arbitrary headings. They are mapped to the controlled `other` group.

## Privacy indicators

Each card displays one controlled privacy label:

- `public` → Public content;
- `private` → Restricted content;
- `sensitive` → Sensitive workflow.

Unknown privacy classifications fail conservatively to `private` presentation. The native module remains responsible for the real data classification and lifecycle.

## Route safety

Every adapter route is passed through WordPress redirect validation before output. An invalid or unauthorized external route is omitted and emits `supc_adapter_invalid_start_url` without disabling unrelated adapters.

## Accessibility baseline

- semantic sections and list structure;
- unique heading IDs;
- keyboard-accessible native links;
- visible focus indicators;
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
- unsafe routes are absent from HTML;
- adapter labels and descriptions are escaped;
- one broken adapter does not break the gateway;
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
