# Phase 22C Second Review Corrections — 2026-07-29

## Purpose

A second independent review was performed after the first Phase 22C review. The second review overruled the earlier statement that no known code-level blocker remained and identified additional accessibility, route-security, privacy, state-diagnosis, and System Check defects.

No next phase, merge, package, staging approval, or production approval is authorized by this correction record.

## Corrections

### 1. Sign-in action contrast

The original action used white text on `#f58220`, which did not provide sufficient contrast for normal text.

The action now uses `#b94d00` with white text and `#8a3600` for hover/active state. Visited-link styling is explicitly controlled so a theme cannot replace the button text color.

### 2. Strict internal route policy

`wp_validate_redirect()` remains the first WordPress validation layer, but it is no longer the only policy.

File 22 now accepts only:

- a relative internal path beginning with one `/`;
- or an absolute HTTPS URL whose host and effective port exactly match the current WordPress home origin.

The following are rejected:

- protocol-relative routes;
- external hosts, even when another plugin allow-lists them;
- HTTP downgrade routes;
- mismatched ports;
- credentials in URLs;
- backslashes and control characters.

### 3. Invalid privacy metadata fails closed

An unknown privacy classification is no longer relabeled as restricted content while the native workflow continues unchanged.

The invalid adapter is omitted, a privacy-safe `invalid_privacy` diagnostic is recorded, and healthy adapters remain available.

### 4. Permission and integration states are distinct

The Create surface now distinguishes:

- central account or capability denial;
- an authorized account whose native adapter, route, or module is unavailable or misconfigured.

An unavailable native module is not presented as a user permission failure.

### 5. Built-in System Check diagnostics

Presentation-layer diagnostics are now part of the built-in `supc_system_check_report` output.

The report exposes only:

- status;
- total count;
- error count;
- warning count;
- non-sensitive diagnostic codes.

It does not expose users, content, URLs, labels, patient data, or adapter payloads.

## Regression coverage

Focused tests now verify:

- same-origin HTTPS and relative routes are accepted;
- allow-listed external hosts are rejected;
- HTTP downgrade is rejected;
- invalid privacy hides only the invalid adapter;
- invalid privacy fails System Check;
- permission and integration failures render different states;
- unavailable native modules are not reported as permission denial;
- group fallbacks remain warning-level diagnostics;
- existing escaping, ownership, Dashicon, ordering, and suspension protections remain intact.

## Remaining gates

These corrections still require fresh CI and controlled staging verification with Files 00, 20, 21, and 22. Browser-based contrast, keyboard, zoom, screen-reader, Urdu RTL, forced-colors, reduced-motion, role-matrix, backup, and rollback acceptance remain mandatory.
