# Compatibility Matrix

| Component | Minimum | File 22 relationship |
|---|---:|---|
| WordPress | 6.5 | Hard runtime baseline |
| PHP | 8.1 | Hard runtime baseline; CI through 8.3 |
| Sabri Membership Core | 1.0.1 | Mandatory identity and permission authority |
| Unified Application Shell | 1.0.1 contract | Create URL and final visibility producer |
| Complete Home and News Feed | 1.0.3 | Required Core 1.0 `social_publication` adapter owner |
| File 22 Adapter API | 1.0.0 | Exact adapter registration contract |
| Learn | Adapter-specific | Optional |
| Encyclopedia | Adapter-specific | Optional |
| Video Wall | Adapter-specific | Optional |
| Reels | Requires Video owner contract | Optional |
| PDF Library | Adapter-specific secure upload | Optional |
| Marketplace | Adapter-specific verified seller identity | Optional |
| Notifications | Event bridge | Optional |

File 22 activation still depends only on Membership Core. The missing File 21 `social_publication` adapter is a Core 1.0 release failure reported through System Check, not a public-site fatal. An absent optional module hides only its adapter.
