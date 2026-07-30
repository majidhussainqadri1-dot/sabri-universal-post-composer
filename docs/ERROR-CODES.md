# Error and Diagnostic Codes

## Runtime `WP_Error` codes

| Code | Meaning |
|---|---|
| `supc_invalid_key` | Adapter key is not canonical |
| `supc_duplicate_key` | Adapter key already exists |
| `supc_api_mismatch` | Base Adapter API is incompatible |
| `supc_invalid_required_capability` | Adapter supplied an empty or noncanonical central capability |
| `supc_invalid_native_module` | Adapter supplied a malformed native-module owner slug |
| `supc_invalid_minimum_native_version` | Adapter supplied a malformed semantic minimum version |
| `supc_invalid_privacy` | Adapter supplied a privacy classification outside the controlled vocabulary |
| `supc_registration_exception` | Adapter registration threw an isolated exception |
| `supc_availability_exception` | Adapter failed during availability/state evaluation |
| `supc_workflow_disabled` | File 22 or unified shell Safe Mode disabled workflows |
| `supc_invalid_workflow_request` | Current subject or adapter key is invalid |
| `supc_workflow_adapter_unavailable` | Requested full Workflow Adapter is missing |
| `supc_workflow_api_mismatch` | Captured Workflow API version is incompatible |
| `supc_native_workflow_unavailable` | Central permission passed, but native workflow is unavailable |
| `supc_workflow_permission_denied` | Membership Core, central capability, or adapter policy denied the authenticated subject |
| `supc_native_drafts_unsupported` | Registration contract does not support direct native drafts |
| `supc_invalid_schema_contract` | Static or subject schema version, vocabulary, privacy, bounds, choices, or size is invalid |
| `supc_invalid_workflow_payload` | Payload contains unsupported objects, resources, nesting, or nonfinite values |
| `supc_workflow_payload_too_large` | Encoded payload exceeds 1 MiB |
| `supc_workflow_payload_unknown_field` | Payload contains a field absent from the authenticated subject's schema |
| `supc_workflow_payload_field_invalid` | Field value has the wrong type, choice, range, or format |
| `supc_workflow_payload_required_field_missing` | A required subject-schema field is absent or empty |
| `supc_invalid_native_reference` | Native reference is empty, oversized, or noncanonical |
| `supc_invalid_idempotency_key` | Submission key is not two UUID-v4 values separated by a colon |
| `supc_invalid_native_result` | Native draft/result envelope is malformed, unsafe, oversized, or has an invalid draft state |
| `supc_invalid_validation_result` | Native validation envelope or code collections are invalid |
| `supc_invalid_preview_result` | Preview URL or expiry is invalid |
| `supc_invalid_native_status` | Native status is outside controlled workflow states |
| `supc_invalid_canonical_url` | Native owner denied the subject or returned an unsafe URL |
| `supc_native_workflow_error` | Native `WP_Error` was normalized and raw message/data discarded |
| `supc_workflow_adapter_exception` | Native operation threw; class and message were not emitted |

## File 22 public API health codes

| Code | Meaning |
|---|---|
| `public_api_version_mismatch` | File 22 public API version marker is missing or wrong |
| `public_api_owner_mismatch` | Public API owner marker is missing or foreign |
| `public_api_function_collision` | One or more public functions/markers were preclaimed; File 22 did not declare a partial API |
| `public_api_incomplete` | Required File 22 public functions are not all present |

## File 20 Create contract health codes

| Code | Meaning |
|---|---|
| `file20_contract_version_mismatch` | File 20 Create contract is not exact version 1.0.1 |
| `file20_contract_owner_mismatch` | File 20 Create contract owner is missing or wrong |
| `file20_contract_collision` | File 20 producer functions are not owned by the expected shell |
| `file20_contract_functions_missing` | Required Create producer functions are absent |
| `file20_contract_unavailable` | File 20 reports its Create producer contract unavailable |
| `file20_contract_exception` | A trusted File 20 readiness callback threw and File 22 failed closed |

## File 21 release-critical health codes

Controlled codes include:

- `social_publication_not_registered`;
- `social_publication_contract_mismatch`;
- `social_publication_native_version_unreported`;
- `social_publication_native_version_invalid`;
- `social_publication_native_version_too_low`;
- `social_publication_temporarily_unavailable`;
- `adapter_key_mismatch`;
- `native_module_mismatch`;
- `minimum_native_version_too_low`;
- `required_capability_mismatch`;
- `group_mismatch`;
- `privacy_classification_mismatch`;
- `diagnostic_contract_missing`;
- `workflow_contract_missing`;
- `workflow_registration_metadata_missing`;
- `workflow_api_mismatch`;
- `workflow_capability_mismatch`;
- `native_draft_contract_missing`;
- `invalid_schema_contract`;
- `subject_schema_api_mismatch`;
- `subject_schema_contract_missing`.

A route-only File 21 adapter must produce a release failure.

## Create-page repair codes

- `dry_run_ready`, `dry_run_repairable`, `dry_run_ambiguous`, `dry_run_missing`;
- `no_change`, `mapped_existing`, `created_managed_page`;
- `ambiguous_selection_required`, `invalid_candidate`;
- `mapping_persistence_failed`, `repair_locked`;
- `managed_slug_unavailable`, `managed_page_insert_failed`, `managed_page_validation_failed`;
- `invalid_request`.

## Native diagnostic allowlist

Allowed native codes are only:

`permission_denied`, `validation_failed`, `conflict`, `rate_limited`, `temporarily_unavailable`, `not_found`, `expired`, and `invalid_reference`.

Every other native code becomes `native_error`. Exceptions use only `native_exception`.

System Check applies fixed row-key and operational-code allowlists. An unknown row key becomes `unrecognized_check`, and an adapter or report-filter code outside the code list becomes `unrecognized_diagnostic`; arbitrary sanitized strings are not treated as safe merely because they look canonical.

Public messages and diagnostics never expose stacks, paths, patient data, raw payloads, IDs, URLs, native references, raw idempotency keys, exception classes/messages, native error data, or secrets.
