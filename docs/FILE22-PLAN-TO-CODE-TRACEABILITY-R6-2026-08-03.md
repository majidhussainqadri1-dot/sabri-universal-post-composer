# File 22 — Central Plan + Harmonized File Plan — Plan-to-Code Traceability R6

Date: 03 August 2026  
Runtime candidate: `1.0.0-rc.1`  
Plan contract: `1.0.0`  
REST contract: `1.2.0`  
Status: source-complete Core candidate; package and automated QA require exact-head evidence; staging/live/operational acceptance remain separate.

## Governing interpretation

The Definitive Master Plan v3.0 and the harmonized File 22 specification are read together. File 22 is one role-aware universal creation facade and orchestration layer. It must never become a second social/news, learning, encyclopedia, media, PDF, marketplace, dashboard, security-center, notification, search, profile, timeline, consent, clinical-record, or identity backend.

The corrected File 22 plan expressly separates **Core completion** from independently certified **Adapter Pack completion**. Missing optional adapter packs warn and hide their creation type; they do not make Core fatal and they do not authorize a substitute native backend.

## Requirement-to-code map

| Governing requirement | Implemented source | Evidence boundary |
|---|---|---|
| One role-aware Create surface | `Create_Surface`, `Workflow_Surface`, `Browser_Runtime` | Browser/staging visual acceptance remains external |
| File 00 fail-closed identity/capability authority | `Permission_Resolver`, `Workflow_Coordinator`, last-point rechecks | Real-role staging remains external |
| Versioned adapter registry and fail-soft optional modules | `Registry`, `Plan_Completion_Runtime` optional adapter-pack rows | Each native pack still needs its own contract/staging certificate |
| Native-owner drafts, validation, preview, submit, status and canonical URL | `Workflow_Adapter`, `Workflow_Coordinator` | Native module owns permanent truth |
| Session metadata only; no draft bodies | `Session_Store` | Database migration must be exercised on staging |
| Four independent state dimensions | `composer_state`, `review_state`, `publication_state`, `hold_state` in `Session_Store` | Native status reconciliation controls progression |
| Immutable idempotency and partial-failure recovery | `Submission_Store`, `Reconciliation_Service` | Network/database/cron failure injection remains staging evidence |
| Session list/My Content projection | `GET /sessions` through `Plan_Rest_Controller` | File 23 remains dashboard owner |
| Create/update/discard/status/revision route contract | REST 1.2.0 routes in `Plan_Rest_Controller` | Revision/discard require native optional interfaces |
| Upload orchestration without byte ownership | `Upload_Token_Adapter`, `Upload_Token_Store`, upload REST routes | Scanner/storage/rights remain native-owner responsibilities |
| Common patient/medical/copyright/marketplace holds | `Policy_Engine` | Native modules may add stricter checks; File 22 cannot weaken native policy |
| Patient Case plaintext/offline prohibition | browser has no `localStorage`, `sessionStorage`, or `IndexedDB`; stores are metadata-only | Device/browser test remains staging evidence |
| Versioned canonical aliases | `Taxonomy_Map::VERSION` and filterable alias map | Native taxonomies remain native-owned |
| Common audit metadata | `Audit_Store`; REST response audit hook | No content, PII, IP address, consent, or evidence payload stored |
| File 23/24/25/19/Search/SEO projection contracts | `Projection_Bus` metadata-only actions | Companion modules own their records and delivery |
| Retention and cleanup | bounded session, submission, outbox, upload-token, and audit cleanup | Legal/operational approval remains external |
| Safe Mode/System Check/degraded mode | existing Safe Mode plus plan-completion diagnostic rows | Recovery drill remains staging evidence |
| Backward-compatible Core | existing `adapters`, schema and session routes preserved; REST additions are additive | Old URL and package upgrade must be exercised on staging |

## New source components

- `Draft_Lifecycle_Adapter` — optional native discard contract.
- `Upload_Token_Adapter` — optional opaque native upload contract; no File 22 bytes.
- `Revision_Adapter` — optional native revision submission contract.
- `Policy_Engine` — common safety/rights/privacy holds only.
- `Audit_Store` — bounded metadata-only audit ledger.
- `Upload_Token_Store` — bounded metadata-only upload-token ledger.
- `Taxonomy_Map` — versioned orchestration aliases.
- `Projection_Bus` — metadata-only companion-module events.
- `Plan_Rest_Controller` — missing Core REST 1.2.0 routes.
- `Plan_Completion_Runtime` — installation, cleanup, diagnostics and REST auditing.

## Explicit non-ownership proof

The added Core source does not register a content post type, create a universal content table, store draft bodies, retain patient narratives, retain consent evidence, store media bytes, deliver notifications, index content, render the File 23 dashboard, render the File 24 security center, or create the File 25 public timeline. It calls native adapters and emits bounded metadata-only events.

## Completion vocabulary

- **Specified:** complete against the two governing plans.
- **Coded:** this R6 Core source candidate implements the Core requirements above.
- **Packaged / Automated-QA Green:** only after exact-head CI, deterministic ZIP, embedded manifest and external checksum pass.
- **Staging-Accepted:** only after fresh install/upgrade, real roles, exact Files 00/20/21 integration, database migration, browsers/devices, RTL, accessibility, cache, failure injection, backup/restore and rollback pass.
- **Live-Deployed:** only after explicit Founder-approved controlled deployment and smoke tests.
- **Operational:** only after monitoring, support, backups and incident response are working.

## Remaining external gates, not missing Core source

1. File 21 exact package/runtime integrated on Hostinger staging.
2. File 20 Create contract integrated on staging.
3. Native adapter-pack certification for Learning, Encyclopedia, Video, Reel, PDF and Marketplace when those packs are promoted.
4. Real WordPress/MySQL schema migration and rollback.
5. Real role, browser, device, RTL, keyboard, screen-reader, zoom, forced-colors, no-JavaScript and slow-network acceptance.
6. Backup restoration, cron delay, duplicate worker, cache, database failure and orphan-draft recovery drills.
7. Founder acceptance, controlled live deployment and operational monitoring.

## Truthful decision

R6 is intended to close the known **Core source-code** gaps between the central plan, the harmonized File 22 plan, and the merged R5 implementation. It does not convert source completion into staging, live, or operational proof. Any failing exact-head test or new evidence reopens review, correction and retesting.
