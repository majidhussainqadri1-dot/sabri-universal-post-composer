# File 22 V12.3 — read-only Hostinger/WordPress runtime evidence freeze

**Applies to WP-03–WP-06, G1, G9–G11.** The commands below are *proposed operator instructions*; no production server command has been run by this PR. Capture only in a restricted operations folder, outside any public web directory, with a date and operator. Treat outputs as internal restricted evidence.

## Strict safety preconditions

- Get authorized administrator/Hostinger **read-only** access. Prefer staging first. Do not paste credentials, application passwords, session cookies, full `wp_options`, patient content, clinical notes, tokens or secret-bearing logs into public GitHub.
- Do not migrate, install, activate/deactivate, edit options, delete caches, or modify files during **freeze**. The sole purpose is to observe.
- Record UTC time, environment ID and requested site, but omit client IPs or patient identifiers from public reports.
- Confirm WP-CLI path and target WordPress install; never run a filesystem hash over all `wp-content`, which might include personal uploads or secret configuration. Hash only the exact active plugin package.

## Step 1 — identity and exact runtime

Record the output of the following read-only examples, once authorized:

```bash
date -u '+%Y-%m-%dT%H:%M:%SZ'
wp core version
wp cli version
php -v
wp plugin list --fields=name,status,version,update --format=table
wp theme list --fields=name,status,version --format=table
wp config get table_prefix
```

The last command is **not** a request for database credentials or salts. Do not execute `wp config list` or print secrets.

The relevant plugin names are `sabri-universal-post-composer` (File22), `sabri-membership-core` (File00), `sabri-unified-application-shell` (File20), `sabri-complete-home-news-feed` (File21), and any verified native media/transfer/security providers. A GitHub source-version label is *not* proof that a given plugin is installed or active.

## Step 2 — options and schema *metadata only*

List scoped option **names** without showing values:

```bash
wp option list --search='supc_%' --fields=option_name,autoload --format=table
wp option list --search='smc_%' --fields=option_name,autoload --format=table
wp option list --search='sabri_hnf_%' --fields=option_name,autoload --format=table
```

Relevant version/migration **values** may be read individually only after checking they do not contain secrets or patient content. Record the exact option name, its declared type, and version markers; redact other data.

A read-only schema inventory can use `information_schema.columns` for the current database and prefix, returning table/column names, data types and nullability. Record relevant File22 tables only (e.g. `*_supc_%`); **do not query rows containing user text or identifiers**. For migration health, read only migration identifiers, status counts and timestamps as permitted by the platform privacy officer.

```sql
SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, IS_NULLABLE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME LIKE '%\\_supc\\_%' ESCAPE '\\'
ORDER BY TABLE_NAME, ORDINAL_POSITION;
```

The SQL above is illustrative: check the real WordPress prefix and SQL client escaping before execution. If unsure, limit to `SHOW TABLES` and `SHOW COLUMNS` manually on known File22 table names.

## Step 3 — deployed package hash and source parity

After finding the **actual plugin path** from WordPress, record its directory name, plugin header and manifest presence. Hash **only** the active, known plugin directory. Example in a restricted shell, from the verified `wp-content/plugins` parent:

```bash
find sabri-universal-post-composer -type f -print0 \
  | sort -z \
  | xargs -0 sha256sum > /restricted-ops/file22-deployed-files.sha256
sha256sum /restricted-ops/file22-deployed-files.sha256
```

Do not assume the directory is the one WordPress loaded; verify the main plugin file location first, especially if duplicate plugin folders exist. File hashes alone do not establish ZIP equality when metadata/packaging differ; compare file manifests with the exact reviewed release artifact and source tree.

**Mismatch rule:** If deployed files/versions and source candidate disagree, initiate a Deployment-Parity Audit *before* any live-bug patch. Preserve deployed evidence and identify the exact running code first.

## Step 4 — runtime symptoms, logs and dependencies

- Snapshot runtime errors for a narrow known time window, **redacted**; collect only code/error fingerprint and file:line tied to exact deployed version.
- Record File00/20/21 API, contract, schema and activation flags through their authorized diagnostics, avoiding raw personal state.
- File21: determine whether the actual running adapter implements `Upload_Token_Adapter`; inspect accepted MIME types, upload begin/complete/cancel controls, signed URL, scanner/quarantine and media authority.
- Record maintenance/safe-mode/cache plugin status; no speculative cause from front-end errors.
- Capture a non-sensitive synthetic-role browser route/response only if an authorized staging test account exists.

## Step 5 — freeze certificate

Use these mandatory fields in a redacted, reviewer-signed report:

| Field | Evidence / disposition |
| --- | --- |
| Site/environment | [live or staging; date/UTC] |
| Repository HEAD | [exact main and candidate sha; distinct] |
| Deployed Version | [plugin header, loaded path, checksum] |
| DB Version | [exact schema/version marker and table metadata] |
| Migration State | [not run/running/complete/deferred/failed; evidence] |
| Active File00/20/21/17/24 providers | [versions, relevant contracts, enabled flags] |
| Deployment parity | [match/mismatch/unknown; exact artifact] |
| Live Verification Status | [not performed/failed/passed; test date and artifact] |
| Incident root cause | [confirmed/provisional; distinguish front-end symptom] |
| Defect census | [found/fixed/open/blocked, source vs staging vs live] |

Do not promote PR #53 on the basis of this document alone. First complete G7/G8, stage and independently accept G9/G10, obtain Founder written approval, then controlled G11 live deployment and G12 live re-test/parity.

## Current real availability

WordPress Jetpack site tooling reported `site_disconnected` and a separate WPVibe connection attempt returned HTTP 530. This does **not** establish an origin-server failure cause. It means that the present tool session has no reliable live WordPress/DB/deployed-source read access.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
