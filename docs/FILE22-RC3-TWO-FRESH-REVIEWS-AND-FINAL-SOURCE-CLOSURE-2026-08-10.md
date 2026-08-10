# File 22 — RC3 Two Fresh Reviews and Final Source Closure

Date: 2026-08-10 (PKT)

## Scope

This record covers the integrated `1.0.0-rc.3` repository candidate for **File 22 — Sabri Universal Post Composer** against the two current governing sources supplied by the Founder: the current consolidated central plan and the current File 22 plan.

The review scope is repository/source truth only. It does not convert a green repository candidate into staging, live or operational truth.

## Fresh Review Round 1 — integration and truth audit

Review focus:

- current `main` history versus the fuller plan-complete candidate lineage;
- current File 22 ownership versus Files 00/20/21/23/24/25/26;
- new central-plan green/free-tier/File26 changes;
- runtime/bootstrap completeness;
- documentation and manifest truth;
- CSS loading scope and visual-token ownership;
- historical evidence preservation.

Defects found and corrected:

1. The first integration attempt made the Sabri Green fallback load too broadly. It was removed from a global hook and made File-22-surface scoped by loading it through the existing Create-surface stylesheet path.
2. README, `readme.txt`, CHANGELOG and MANIFEST still described older RC1/earlier-candidate truth. They were reconciled to `1.0.0-rc.3` while retaining explicit staging/live disclaimers.
3. Replacing MANIFEST with only current files initially broke historical evidence verification. Historical review entries were restored without allowing those old heads to become current runtime truth.
4. A reconciliation edit accidentally truncated `class-plugin.php`, which removed stronger Runtime_Trust/File20/File21 provenance checks and introduced a syntax failure. The exact hardened plan-complete runtime blob was restored rather than hand-reimplemented.

Round-1 disposition: all identified source defects corrected and sent through fresh exact-head CI.

## Fresh Review Round 2 — adversarial plan/contract audit

Review focus:

- exact adapter keys versus the governing content-type catalog;
- fail-soft optional modules;
- no duplicate native backend;
- File 26 Search/Discovery/Ranking ownership;
- File 25 visual-token ownership;
- File 00 current-subject authorization;
- Patient Case, medical safety, rights/copyright and lifecycle contracts;
- historical regression compatibility without false current-state claims;
- release identity and package isolation.

Defects found and corrected:

1. Optional adapter diagnostics used obsolete shorthand keys (`learning`, `encyclopedia`, `pdf`, `marketplace`) instead of canonical adapter keys (`learning_lesson`, `encyclopedia_entry`, `pdf_document`, `marketplace_listing`). Runtime diagnostics and PHPUnit regressions were corrected together.
2. The cumulative repository contract expected preserved historical marker phrases. Current documentation was amended with an explicitly historical compatibility block so old evidence remains verifiable without implying old code is current.
3. The canonical-record phrase required by the repository contract had been lost during README consolidation. The current README now states the architectural invariant directly: **One canonical native record; multiple authorized projections.**

Round-2 disposition: all identified source defects corrected and sent through fresh exact-head CI.

## Final post-correction verification law

This closure is valid only for an exact commit where all of the following are green at that same source head:

- File 22 CI;
- PHPUnit cumulative contract tests;
- PHP 8.1 / 8.2 / 8.3 syntax;
- PHPStan;
- WordPress Coding Standards/security checks;
- repository contract;
- `File 22 New Plans 1.0.0-rc.3` exact-head workflow;
- deterministic RC3 package build, embedded manifest and archive SHA-256 verification;
- retained historical review-evidence workflows that remain applicable to cumulative source invariants.

If any later commit changes source, configuration, manifest, package workflow or release identity, this verification must be rerun on that later exact head.

## Source-closure decision

Subject to the exact-head green condition above, the known repository/source gaps identified in these two fresh reviews are closed for the two current governing plans. This means **Coded** may be marked complete at repository scope and **Packaged / Automated-QA Green** may be marked complete only when the exact-head RC3 workflow produces and verifies its artifact.

The following remain separate and unclaimed:

- Staging-Accepted;
- Live-Deployed;
- Operational.

Mandatory environment work still includes Hostinger staging fresh install/upgrade, exact deployed companion versions, database/schema/migration verification, real-role workflows, browser/device/accessibility/RTL/weak-network testing, theme/cache interaction, backup restore, rollback rehearsal, Founder acceptance, production deployment, live smoke testing, deployed-artifact parity and post-deployment monitoring.

No repository or CI result in this document is a substitute for those environment gates.
