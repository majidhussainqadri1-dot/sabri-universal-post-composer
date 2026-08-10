# File 21 Package Identity Diagnostic Correction — R8

## Defect

File 22 used the strict three-part Semantic Versioning validator for File 21 package identity. Consequently the valid WordPress package identity `1.0.3.2` was discarded and Composer Health emitted `file21_package_identity_unknown` although File 21 runtime/API `1.0.3` and its native adapter were healthy.

## Correction

- File 21 runtime/API remains strict SemVer and is checked independently at `1.0.3+`.
- Package detection reads canonical `SABRI_HNF_PACKAGE_VERSION` first, then uses a bounded matching plugin-header fallback only when the constant is absent.
- Package identities accept strict numeric three- or four-part WordPress versions and compare them without integer overflow.
- `missing`, `invalid`, and `too_low` are separate diagnostic states and codes.
- Exact package `1.0.3.2` maps to `file21_package_identity — PASS`.

## Review cycle 1

The full source, static-analysis, coding-standard, syntax, and PHPUnit gates are rerun after the correction. The review specifically checks that strict SemVer was not weakened and that package identity cannot silently fall back from an invalid canonical constant.

## Review cycle 2 — fresh adversarial

The correction is rechecked against equal, lower, higher, malformed, leading-zero, oversized-component-count, missing-constant, and header-fallback paths. A second full regression run follows. Known unresolved defects from this correction must be zero before the branch is committed.

## Release boundary

This correction establishes Coded, Packaged, and Automated-QA evidence only after the exact-head workflows complete. Hostinger staging, live deployment, and operational acceptance remain separate gates.
