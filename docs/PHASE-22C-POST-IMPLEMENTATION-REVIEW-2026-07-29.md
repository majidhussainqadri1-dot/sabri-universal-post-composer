# Phase 22C Post-Implementation Review — 2026-07-29

## Review rule

Phase 22C was not treated as complete after initial implementation. A separate review was performed before any next phase, covering code, integration contracts, accessibility, security, presentation consistency, tests, and release boundaries.

## Reviewed scope

- `includes/presentation/class-create-surface.php`;
- `assets/css/create-surface.css`;
- plugin bootstrap and runtime delegation;
- adapter metadata and native-route handling;
- PHPUnit and PHPStan coverage;
- Phase 22C documentation and CI contracts.

## Findings and corrections

### 1. Canonical group order was not guaranteed

**Finding:** Initial output order depended on whichever adapter priority first introduced a group. A media adapter with a lower numeric priority could make Media appear before Publishing.

**Correction:** Added a controlled group order:

1. Publishing;
2. Knowledge and Learning;
3. Media;
4. Commerce;
5. Other Authorized Content.

Adapter priority continues to determine card order inside each group.

### 2. Unknown group and privacy values had no diagnostic signal

**Finding:** Unknown groups were mapped to `other` and unknown privacy values to `private`, but no event exposed the contract mismatch.

**Correction:** Added privacy-safe diagnostic actions:

- `supc_adapter_group_fallback`;
- `supc_adapter_privacy_fallback`.

The interface remains fail-soft while System Check integrations can identify noncanonical adapters.

### 3. Prefixed Dashicon values could be double-prefixed

**Finding:** An adapter returning `dashicons-admin-post` would have produced `dashicons-dashicons-admin-post`.

**Correction:** The renderer now accepts either `admin-post` or `dashicons-admin-post` and emits one valid Dashicon class.

### 4. Create surface typography could conflict with File 20

**Finding:** The initial CSS forced Arial and could visually diverge from the Unified Application Shell.

**Correction:** Typography now inherits from the active platform shell or theme. No remote font or additional font dependency is introduced.

### 5. Directional arrow was not RTL-aware

**Finding:** A fixed right arrow remained right-facing in Urdu or another RTL interface.

**Correction:** The arrow is now a presentational element with logical spacing and RTL mirroring. It remains hidden from assistive technology.

### 6. Keyboard focus fallback was narrower than necessary

**Finding:** Focus styling relied on `:focus-visible` only.

**Correction:** Added visible `:focus` and `:focus-visible` treatment, including forced-colors support.

### 7. Empty-state wording was too publishing-specific

**Finding:** The message referred only to publishing permission although File 22 also routes learning, media, and commerce content.

**Correction:** Replaced it with the broader and accurate term “creation permission.”

### 8. Initial PHPStan scope included an unrelated resolver without WordPress stubs

**Finding:** CI run 80 failed because the Phase 22C PHPStan expansion analyzed the existing Page Resolver and encountered missing WordPress function symbols. The new presentation code and PHPUnit contracts were otherwise successful.

**Correction:** Kept the Page Resolver symbol available through the isolated bootstrap while limiting the Phase 22C PHPStan target to the presentation and already-supported core contracts. This does not suppress presentation errors or change production code.

## Verification added

Tests now prove:

- canonical group order is independent of cross-group adapter priority;
- unsafe external routes are omitted;
- unknown group and privacy values emit diagnostics;
- unknown privacy fails conservatively to restricted presentation;
- labels are escaped;
- prefixed Dashicons are not double-prefixed;
- suspended users receive no adapter choices;
- ownership language and accessible structure remain present.

## Remaining limitations

The following require controlled staging and are not claimed by automated tests:

- real File 20 desktop and mobile rendering;
- real File 21 route opening and permission recheck;
- 200% and 400% zoom;
- keyboard order in the active theme;
- screen-reader behavior;
- Urdu RTL presentation;
- forced-colors and reduced-motion behavior in real browsers;
- role matrix and rollback with Files 00, 20, 21, and 22 installed together.

## Review conclusion

No known code-level blocker remains inside the Phase 22C route-only Create surface after the recorded corrections. Phase 22C remains a Draft and is not staging-approved, merge-approved, package-approved, or production-approved until the cross-plugin acceptance gates are completed.
