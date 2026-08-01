# Rollback

File 22 rollback is non-destructive toward companion-module records and fail-closed toward a newly inserted unsafe Create surface.

1. Capture a verified database and files backup before installation or upgrade.
2. Record the plugin version, schema version, Create page ID, adapter list, and relevant File 20/File 00 versions.
3. Deactivate File 22 if a critical fault occurs.
4. Existing native content and native forms remain operational because File 22 does not own or delete them.
5. Restore File 22-owned settings or temporary orchestration records only from the verified snapshot when required.
6. Do not automatically delete an already accepted canonical Create page during ordinary plugin rollback; keep it for inspection or explicitly unpublish it after confirmation.
7. A page newly inserted by a failed repair attempt is different: File 22 may permanently delete only that exact new object. If deletion fails, it must be quarantined as an empty nonpublic draft. If both deletion and quarantine fail while the shortcode remains public, File 22 enters emergency-disable mode and records controlled cleanup evidence.
8. Mapping rollback preserves the exact previous option state, including absence, malformed strings, and `null`; it never substitutes a magic sentinel for actual state.
9. Never delete companion-plugin content, secure media, consent evidence, identity evidence, or clinical records during rollback.

Rollback acceptance requires plugin-deactivation recovery, exact failed-repair cleanup, emergency-disable behavior, and backup-restoration testing on staging.
