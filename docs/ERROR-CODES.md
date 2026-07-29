# Error Codes

| Code | Meaning |
|---|---|
| `supc_invalid_key` | Adapter key is not canonical |
| `supc_duplicate_key` | Adapter key already exists |
| `supc_api_mismatch` | Adapter API version is incompatible |
| `supc_registration_exception` | Adapter failed during registration |
| `supc_availability_exception` | Adapter failed during availability or authorization evaluation |
| `SUPC-PERM-403` | Central or adapter permission denied |
| `SUPC-CONFLICT-409` | Draft or submission conflict |
| `SUPC-UPLOAD-413` | Upload exceeds permitted size |
| `SUPC-MEDIA-415` | Unsupported or invalid media |
| `SUPC-VALIDATION-422` | Payload validation failed |
| `SUPC-ADAPTER-503` | Native adapter temporarily unavailable |

Public messages remain human-readable and do not expose stack traces, filesystem paths, patient data, or secrets. Detailed diagnostics are restricted to authorized administrators.
