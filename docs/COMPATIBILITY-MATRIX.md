# Compatibility Matrix

| Component | Minimum / observed package | File 22 relationship |
|---|---:|---|
| WordPress | 6.5 | Hard runtime baseline |
| PHP | 8.1 | Hard runtime baseline; CI through 8.3 |
| Sabri Membership Core | Plugin 1.2.3 / DB 1.2.0 / Contract 1.1.2 | Mandatory identity and permission authority; canonical package/callback provenance also required |
| Unified Application Shell base package | Historical distributed 1.0.0; separately observed File 20 repository `main` source header 1.4.17 (2026-11-14) | Optional canonical shell; consumers must verify the **actual deployed** package and Create contract, not infer it from either repository or historic ZIP |
| Unified Application Shell Create contract | 1.0.1 atomic contract | The historic File 20 v1.0.0 ZIP lacked it, but File 20 current repository source includes `class-create-contract.php` declaring 1.0.1; staging/live acceptance is **still unverified** until exact deployed package parity is established |
| Complete Home and News Feed runtime/API | 1.0.3 | Required Core `social_publication` native adapter contract floor |
| Complete Home and News Feed package identity | 1.0.3.2 | Required for integrated staging package provenance checks |
| File 22 Adapter API | 1.0.0 | Exact adapter registration contract |
| Learn | Adapter-specific | Optional |
| Encyclopedia | Adapter-specific | Optional |
| Video Wall | Adapter-specific | Optional |
| Reels | Requires Video owner contract | Optional |
| PDF Library | Adapter-specific secure upload | Optional |
| Marketplace | Adapter-specific verified seller identity | Optional |
| Notifications | Event bridge | Optional |

File 22 activation depends only on the canonical Membership Core at the stated current minimums. A canonical legacy File 20 version 1.0.0 package may supply the Create URL filter without being trusted as the later Create visibility or emergency-state authority. Marker-only, function-only, partial, foreign, inherited, or colliding File 20 Create claims fail closed. File 21 runtime/API `1.0.3` is the social-adapter compatibility floor while integrated staging additionally verifies package identity `1.0.3.2`; a missing or incompatible `social_publication` adapter remains a Core release failure reported through System Check, not a public-site fatal. An absent optional native module hides only its adapter.

## Repository versus deployed identity — evidence amendment (2026-11-14)

The **historical** distributed File 20 1.0.0 contract gap is not proof that today's File 20 source or live package lacks the producer contract. The current File 20 `main` repository tree `8a4dbcaf4fef8e926b9b834ecfde16c21a0f00ca` contains `sabri-unified-application-shell/includes/class-create-contract.php` and the plugin header `1.4.17`; `CreateContract::available()` explicitly checks `SABRI_SHELL_CREATE_CONTRACT_VERSION === '1.0.1'`, canonical owner and package-owned functions. This is **source evidence only**, not successful staging integration or live code verification.

Likewise the current GitHub File 00 source is 1.2.44 (DB marker 1.4.5 / contract 1.2.3), and the File 21 `sabri-complete-home-news-feed` source header is 1.0.5; neither demonstrates the active installed versions. A mismatch with the deployed artifact requires a **Deployment-Parity Audit before live root-cause classification**.

D13 remains a separate native media integration gap: File 21's inspected social native adapters do not yet implement File 22's optional Upload_Token_Adapter, even though the source plugin's base version exceeds the legacy minimum. Do not mark media acceptance PASS because the old File 21 version floor is met.

No host runtime/DB/schema/migration/deployed checksum was obtainable via current WordPress/Hostinger tool access. **Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
