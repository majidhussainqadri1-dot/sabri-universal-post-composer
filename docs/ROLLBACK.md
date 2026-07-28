# Rollback

File 22 rollback is non-destructive.

1. Capture a verified database and files backup before installation or upgrade.
2. Record the plugin version, schema version, Create page ID, adapter list, and relevant File 20/File 00 versions.
3. Deactivate File 22 if a critical fault occurs.
4. Existing native content and native forms remain operational because File 22 does not own or delete them.
5. Restore File 22-owned settings or temporary orchestration records only from the verified snapshot when required.
6. Do not delete the managed Create page automatically; keep it for inspection or explicitly unpublish it after confirmation.
7. Never delete companion-plugin content, secure media, consent evidence, identity evidence, or clinical records during rollback.

Rollback acceptance requires both plugin-deactivation recovery and backup-restoration testing on staging.
