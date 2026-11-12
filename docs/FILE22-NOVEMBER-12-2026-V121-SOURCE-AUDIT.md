# فائل 22 — 12 نومبر 2026ء: V12.1 ماخذی تدقیق و اصلاحی روداد

## دائرہ اور حقیقت کی حد
- مرکزی تقاضا: **صابری سوشل ہومیوپیتھی پلیٹ فارم ماسٹر پلان V12.1**، مورخہ 10 نومبر 2026ء؛ فائل 22 کے جامع منصوبۂ نفاذ کے کام WP-001، WP-007، WP-008، WP-053۔
- اصل GitHub `main` بنیاد: `b7a7f2e69411cbd32f0574fd12d766fb70c01b7a` (27 ستمبر 2026ء)؛ امیدوار پلگ اِن `1.0.0-rc.3`؛ schema identifier `1.0.0`؛ REST `1.2.0`۔
- یہ تدقیق **صرف repository/source evidence** ہے۔ Exact deployed code، active plugin version، live DB/schema، migration state، package checksum، staging اور browser/production tests دستیاب نہیں۔
- **Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
- Github PR #26 ایک علیحدہ، غیر ضم شدہ Future Composer Intelligence مجوزہ شاخ ہے؛ اس میں کوئی تبدیلی نہیں کی گئی۔

## ثبوت، علت اور تصحیح
2026-11-12 کو `main` کی مکمل recursive Git tree (non-truncated) اور `MANIFEST.md` کے حرف بہ حرف `- \`path\`` اندراجات کا تقابل کیا گیا۔ 203 tracked blobs کے مقابلے میں MANIFEST میں 191 درج شدہ راستے تھے۔ جامع فہرست کے لیے درج ذیل پانچ قابلِ تصدیق عدمِ مطابقت ملیں:

| شناخت | حقیقت | ماخذی اصلاح |
|---|---|---|
| D22-DOC-01 | `.github/workflows/file22-plan-complete-core.yml` فہرست میں تھا مگر Git tree میں نہیں تھا | متروک حوالہ حذف |
| D22-DOC-02 | `tests/CompleteAuthorizationForensicTest.php` tree میں موجود، فہرست سے غائب | درج |
| D22-DOC-03 | `tests/CoreComposerRuntimeTest.php` tree میں موجود، فہرست سے غائب | درج |
| D22-DOC-04 | `tests/ReconciliationOutboxTest.php` tree میں موجود، فہرست سے غائب | درج |
| D22-DOC-05 | `tests/run-file22-file23-dashboard-bridge-tests.php` tree میں موجود، فہرست سے غائب | درج |

سابقہ منصوبے میں `includes/integration/class-file23-dashboard-adapter-runtime.php` کے فہرست سے غائب ہونے کا شبہ **اس تازہ تقابل میں غلط ثابت ہوا**: یہ فائل `main` کے `MANIFEST.md` میں پہلے ہی درج تھی۔ لہٰذا سابقہ تین مثالوں کو جوں کا توں پانچ کا حصہ نہیں بنایا گیا۔

چھٹی، نوعیتاً جدا دستاویزی خرابی:
- **D22-DOC-06**: `docs/ADAPTER-CONTRACT.md` نے تاریخی Phase 22E کے متعلق `no REST` کی عبارت کو موجودہ contract میں برقرار رکھا، جبکہ RC3 میں `includes/http/class-rest-controller.php`، `class-plan-rest-controller.php`، `class-reconciliation-rest-controller.php` اور `assets/js/workflow-composer.js` موجود ہیں۔ پرانے دعوے کو تاریخی حالت کے طور پر مشروط کرکے حالیہ private REST authorization اور privacy requirements واضح کی گئیں۔

## مستقل حفاظتی امتحان
`tests/ManifestInventoryTest.php` میں تین source-only PHPUnit امتحان شامل ہیں:
1. فہرست کا ہر path واقعی tree میں موجود ہو؛ duplicate path یا parent traversal درج نہ ہو۔
2. `includes/`، `tests/`، `assets/`، `.github/workflows/` کے PHP، CSS، JS، YAML فائلیں manifest سے خارج نہ ہوں۔
3. موجودہ adapter contract اپنے authenticated private REST اور browser write surface کی حقیقت بیان کرے، obsolete no-REST عبارت نہ ہو۔

نئے ٹیسٹ کا وجود live security کی منظوری یا native workflows کے درست ہونے کی ضمانت نہیں۔ GitHub CI کی نئی PR HEAD پر کامیابی الگ ثبوت ہوگا۔

## شمار و حالت
| زمرہ | مقدار | فیصلہ |
|---|---:|---|
| قابلِ تصدیق ماخذی دستاویزی نقائص | **6** | اسی correction branch پر اصلاح |
| پہلے سے لگایا گیا، مگر غلط ثابت شدہ اندراج | **1** | تقابلی رپورٹ میں تصحیح |
| اس اصلاح میں application runtime PHP/JS changes | **0** | بلا ثبوت application bug patch نہیں |
| نئی مسلسل حفاظتی regression test file | **1** | source branch پر شامل |
| اصل live runtime defects ثابت | **0** | یہ clean live کا ثبوت نہیں |
| live defects resolved | **0** | کوئی live retest نہیں |

## باقی P0 رکاوٹیں
- WP-002 تا WP-006: deployed plugin package+SHA، active File00/20/21 versions، live/staging schema+migration، relevant options/tables، logs اور parity غیر مصدقہ۔
- File20 distributed legacy runtime اور atomic Create contract 1.0.1 کے درمیان staging compatibility عملی طور پر دکھانی ہوگی۔
- File21 `social_publication` adapter کے native durable draft، permission، schedule/correction اور idempotent submission کی real integration آزمائش ضروری ہے۔
- Real-role WordPress staging، browser/RTL/a11y، failure injection، backup/restore، rollback، Founder acceptance اور controlled production rollout باقی ہیں۔

## آزمائش کی سچی حد
- 27 ستمبر 2026ء کے `main` کے تین کامیاب GitHub workflows صرف اسی پرانے exact SHA کی شہادت ہیں۔
- correction branch کے `MANIFEST.md` اور Git tree میں **کوئی missing-listed یا unlisted runtime/test/assets/workflow path نہ پایا گیا**؛ یہ repository tree consistency check ہے، PHPUnit/CI یا live test نہیں۔
- نئے branch HEAD کا PHPUnit، PHPCS، PHPStan اور package workflow نتیجہ PR CI مکمل ہونے پر ہی معتبر ہوگا۔

## اگلی ترتیب
1. Corrective PR کی exact-head CI اور code-review؛ failures ہوں تو اسی PR میں root cause کے مطابق اصلاح۔
2. Accepted source → deterministic package hash → staging import/upgrade → File00/20/21 versions اور schema freeze۔
3. End-to-end tests T-01..T-50، negative authorization، privacy، cache، idempotency، media revocation اور rollback۔
4. بانی کی واضح release approval، controlled deploy، live re-test، deployed checksum parity اور operational monitoring۔

**حتمی حالات**: Repository HEAD: اوپر درج بنیاد، PR HEAD الگ؛ Deployed Version: نامعلوم؛ DB Version: نامعلوم؛ Migration State: نامعلوم؛ Live Verification Status: Not performed/unverified۔
