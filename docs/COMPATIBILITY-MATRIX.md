# Compatibility Matrix

| Component | Minimum / observed package | File 22 relationship |
|---|---:|---|
| WordPress | 6.5 | Hard runtime baseline |
| PHP | 8.1 | Hard runtime baseline; CI through 8.3 |
| Sabri Membership Core | 1.0.1 | Mandatory identity and permission authority |
| Unified Application Shell base package | Distributed 1.0.0 | Optional canonical shell; consumes `sabri_shell_create_url`; absence of the later Create contract must not disable File 22 |
| Unified Application Shell Create contract | 1.0.1 atomic contract | Required for package-owned Safe Mode and final role-aware Create visibility; not present in the distributed File 20 version 1.0.0 ZIP and therefore a staging/release integration blocker |
| Complete Home and News Feed | 1.0.3 | Required Core 1.0 `social_publication` adapter owner |
| File 22 Adapter API | 1.0.0 | Exact adapter registration contract |
| Learn | Adapter-specific | Optional |
| Encyclopedia | Adapter-specific | Optional |
| Video Wall | Adapter-specific | Optional |
| Reels | Requires Video owner contract | Optional |
| PDF Library | Adapter-specific secure upload | Optional |
| Marketplace | Adapter-specific verified seller identity | Optional |
| Notifications | Event bridge | Optional |

File 22 activation depends only on the canonical Membership Core. A canonical legacy File 20 version 1.0.0 package may supply the Create URL filter without being trusted as the later Create visibility or emergency-state authority. Marker-only, function-only, partial, foreign, inherited, or colliding File 20 Create claims fail closed. The missing File 21 `social_publication` adapter remains a Core 1.0 release failure reported through System Check, not a public-site fatal. An absent optional native module hides only its adapter.
