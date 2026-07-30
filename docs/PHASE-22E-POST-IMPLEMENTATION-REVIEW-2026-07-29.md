# Phase 22E Post-Implementation Review — 2026-07-29

## Review rule

Phase 22E was reviewed separately after its initial implementation and automated run. The implementation was not treated as complete merely because syntax, PHPUnit, PHPCS, and repository checks passed.

## Reviewed scope

- `Workflow_Adapter` contract;
- coordinator authorization order;
- schema, draft, validation, preview, submission, status, and canonical URL envelopes;
- payload, native-reference, idempotency, and URL validation;
- exception and native-error privacy;
- native ownership and durable-idempotency boundaries;
- PHPUnit, PHPStan, PHPCS, PHP 8.1–8.3 syntax, documentation, and stacked PR boundaries.

## Initial automated result

Initial reviewed head:

`6612b7b0236930c25f30572a20998ef7c1add0a6`

File 22 CI run `146` passed PHP 8.1–8.3 syntax, PHPCS, PHPUnit, and repository contracts, but PHPStan failed because several union-return methods lacked parsed iterable value types. This is a required correction, not a release exception.

## Findings requiring correction

### 1. Workflow API version was declared but not negotiated

`SUPC_WORKFLOW_API_VERSION` existed without a `Workflow_Adapter` handshake. A base adapter API match did not prove compatibility with the direct orchestration contract.

**Required correction:** add an exact `workflow_api_version()` contract and reject incompatible workflow adapters before native invocation.

### 2. Native-draft support declaration was ignored

`supports_native_drafts()` existed but `create_draft()` invoked the native method even when support was false.

**Required correction:** fail closed with a controlled error before native draft invocation.

### 3. Native `WP_Error` objects were passed through unchanged

Native error messages and data can contain stack context, filesystem paths, payload fragments, patient information, or secrets.

**Required correction:** convert native errors into a generic File 22 error and retain only a sanitized native code in privacy-safe diagnostics.

### 4. Native availability was checked before permission

An unauthorized caller could receive a different result depending on whether a native module was online, disclosing integration state before the central permission decision.

**Required correction:** enforce Membership Core and adapter permission before exposing native availability state.

### 5. Idempotency validation accepted structurally weak keys

The initial pattern accepted any 32-character repeated string from the allowed alphabet.

**Required correction:** require the File 22 two-UUID-v4 key format and document that native modules remain responsible for durable key-to-record reconciliation.

### 6. Native result and schema sizes were unbounded

Payload size was bounded, but schema and native result arrays could be arbitrarily large.

**Required correction:** apply explicit schema and result encoded-size limits in addition to type and nesting validation.

### 7. Result envelopes returned arbitrary extra native data

Draft, preview, validation, submission, and status results could include unrelated or sensitive fields because the coordinator returned native arrays largely unchanged.

**Required correction:** normalize each operation to a strict whitelist and discard unapproved fields.

### 8. Validation errors could contain free-form sensitive text

The documentation called validation errors privacy-safe codes, but the implementation accepted arbitrary strings.

**Required correction:** accept only canonical bounded error and warning code collections.

### 9. Preview lifetime was not enforced

A same-origin preview URL could be returned without a controlled expiration or with a very long lifetime.

**Required correction:** require a future expiration within a short maximum preview lifetime.

### 10. Draft status was not normalized

`create_draft()` verified only the native reference and could return an arbitrary status or extra fields.

**Required correction:** normalize the draft result to a controlled native reference and controlled status.

### 11. Static-analysis contracts were incomplete

One-line PHPDoc tags were not parsed as intended for several array union return types, and PHPStan lacked the WordPress `wp_json_encode()` signature.

**Required correction:** use explicit return types with parseable generic PHPDoc and a PHPStan-only WordPress signature stub.

## Review conclusion

**REQUEST CHANGES.** Phase 22E remains Draft, unmerged, unstaged, and not approved for production. All findings above require correction, focused regression tests, fresh CI, and a separate post-correction verification before any subsequent File 22 phase.
