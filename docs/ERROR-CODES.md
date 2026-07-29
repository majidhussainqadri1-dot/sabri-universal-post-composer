# Error Codes

| Code | Meaning |
|---|---|
| `supc_invalid_key` | Adapter key is not canonical |
| `supc_duplicate_key` | Adapter key already exists |
| `supc_api_mismatch` | Base adapter API version is incompatible |
| `supc_invalid_required_capability` | Workflow adapter registration supplied a non-canonical central capability |
| `supc_registration_exception` | Adapter failed during registration |
| `supc_availability_exception` | Adapter failed during availability or authorization evaluation |
| `supc_workflow_disabled` | File 22 or the unified shell is in Safe Mode |
| `supc_invalid_workflow_request` | Current subject or adapter key is invalid |
| `supc_workflow_adapter_unavailable` | Requested direct workflow is missing or incompatible |
| `supc_workflow_api_mismatch` | Captured direct workflow API version is incompatible |
| `supc_native_workflow_unavailable` | Authorized native workflow reports unavailable |
| `supc_workflow_permission_denied` | Membership Core, central capability, or adapter policy denied the current authenticated subject |
| `supc_native_drafts_unsupported` | Captured adapter contract does not support direct native draft orchestration |
| `supc_invalid_schema_contract` | Schema version, field vocabulary, privacy, bounds, choices, or size is incompatible |
| `supc_invalid_workflow_payload` | Payload contains objects, resources, excessive nesting, non-finite values, or unsupported values |
| `supc_workflow_payload_too_large` | Encoded payload exceeds the 1 MiB orchestration limit |
| `supc_invalid_native_reference` | Native reference is empty, too long, or non-canonical |
| `supc_invalid_idempotency_key` | Submission key is not two UUID-v4 values separated by a colon |
| `supc_invalid_native_result` | Native draft, submit, or status envelope is malformed, unsafe, oversized, or uses an invalid draft state |
| `supc_invalid_validation_result` | Native validation envelope or its error/warning code collections are invalid |
| `supc_invalid_preview_result` | Native preview URL or short-lived expiration is invalid |
| `supc_invalid_native_status` | Native status is outside the controlled workflow states |
| `supc_invalid_canonical_url` | Native owner denied the subject or returned an external, downgraded, malformed, or unsafe URL |
| `supc_native_workflow_error` | Native `WP_Error` was normalized; raw message/data were discarded and its code was mapped through a fixed allowlist |
| `supc_workflow_adapter_exception` | Native workflow threw an isolated exception; class name and message were not emitted |
| `SUPC-PERM-403` | Central or adapter permission denied |
| `SUPC-CONFLICT-409` | Draft or submission conflict |
| `SUPC-UPLOAD-413` | Upload exceeds permitted size |
| `SUPC-MEDIA-415` | Unsupported or invalid media |
| `SUPC-VALIDATION-422` | Payload validation failed |
| `SUPC-ADAPTER-503` | Native adapter temporarily unavailable |

Allowed native diagnostic codes are limited to `permission_denied`, `validation_failed`, `conflict`, `rate_limited`, `temporarily_unavailable`, `not_found`, `expired`, and `invalid_reference`. Every other native-controlled code is exposed only as `native_error`. Exceptions use the fixed diagnostic code `native_exception`.

Public messages do not expose stack traces, filesystem paths, patient data, raw payloads, native exception classes/messages, native error data, arbitrary native codes, or secrets.
