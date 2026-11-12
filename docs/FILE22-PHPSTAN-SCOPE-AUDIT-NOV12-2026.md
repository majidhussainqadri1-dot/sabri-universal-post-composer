# فائل 22 — مکمل production PHP static-analysis coverage: 12 نومبر 2026ء

**اس دستاویز کی حیثیت:** source-level QA coverage، staging/live incident یا deployed artifact evidence نہیں۔

## اصل مسئلہ
GitHub main SHA `4fea4c9b4b6a69a34c798978f5857caa0011911a` کی مکمل recursive Git tree میں `includes/` کے تحت **49** first-party production PHP source files موجود تھیں۔ `phpstan.neon.dist` کے paths (directory `includes/contracts` سمیت) صرف **46** کو کور کرتے تھے۔ تین paths شامل نہیں تھے:

1. `includes/core/class-governing-plan-runtime.php`
2. `includes/core/governing-plan-functions.php`
3. `includes/integration/class-file23-dashboard-adapter-runtime.php`

یہ کوئی ثابت شدہ live vulnerability نہیں؛ یہ automated static-analysis coverage gap ہے۔ موجودہ PHPCS ان فائلوں کا تجزیہ کرتا ہے، مگر PHPStan کی config میں ان کی عدمِ شمولیت مستقبل کے regressions کو پوشیدہ رکھ سکتی تھی۔

## توسیعی QA سے واقعی ظاہر ہونے والی چار تشخیصی خرابیاں

ابتدائی PR #35 کے exact-head PHPStan job (GitHub Actions job 131170440307) نے File 23 کے optional integration میں یہ چار نتائج فراہم کیے، جنہیں چار علیحدہ سافٹ ویئر حملوں یا لائیو runtime bugs کا نام نہیں دیا گیا:
1. `SPDB_Provider_Adapter` interface PHPStan کے isolated environment میں غیر معروف تھی، کیونکہ اصل interface فائل 23 کی ملکیت ہے اور File 22 کا runtime اسے صرف File 23 موجود ہونے پر متحرک کرتا ہے۔
2. `File23_Dashboard_Adapter_Runtime::list_items()` کا واضح return type غائب تھا۔
3. `File23_Dashboard_Adapter_Runtime::get_item()` کا واضح return type غائب تھا۔
4. `File23_Dashboard_Adapter_Runtime::execute_operation()` کا واضح return type غائب تھا۔

بنیادی سبب: تین اختیاری modules کو PHPStan scope میں شامل کرتے وقت ایک اختیاری خارجی interface کی source-only declaration موجود نہیں تھی، اور تین return signatures سخت static analysis کے مطابق مکمل نہیں تھے۔ اسی ایک PHPStan ناکامی کے باعث چار الگ CI jobs ناکام ہوئیں؛ چار jobs کو چار نئے root causes نہ شمار کیا جائے۔

**محدود اصلاح:** File 23 کے اصل `main` SHA `dcae138e6073f4d0ff596623deb05b9940b8271b` کی `includes/interface-spdb-provider-adapter.php` سے 2.0.0 contract ملا کر `tests/phpstan-wordpress-stubs.php` میں صرف static-analysis کے لیے اس interface کی محفوظ مشروط نقل بنائی گئی، production bootstrap میں شامل نہیں۔ File 22 کے صرف تین متعلقہ methods کو `array|\\WP_Error` result type دیا گیا، ان کے return payload یا native ownership میں کوئی تبدیلی نہیں کی گئی۔ `tests/StaticAnalysisCoverageTest.php` میں اس معاہدے کا source regression guard شامل کیا گیا۔ کامیاب exact-head CI ثبوت دیکھے بغیر اصلاح مکمل نہیں سمجھی جائے گی۔

## اصلاح
- `phpstan.neon.dist` کے مطابق تینوں paths صراحت سے شامل کیے گئے؛ PHPStan کے باقی paths یا ignoreErrors کو وسیع نہیں کیا گیا۔
- `tests/StaticAnalysisCoverageTest.php` شامل کیا گیا تاکہ آئندہ `includes/` کی کوئی نئی production PHP فائل تجزیے سے باہر نہ رہے۔ ٹیسٹ یہ بھی تصدیق کرتا ہے کہ Composer کا `phpstan` script یہی configuration استعمال کرتا ہے۔
- `MANIFEST.md` میں نئے test اور اس رپورٹ کا درج ہونا لازم ہے۔

## قبولیت
- نئی PR HEAD پر PHPUnit، PHPStan، PHP lint، PHPCS اور تمام متعلقہ GitHub workflows چلانا لازم ہے۔
- PHPStan کی حقیقی warning/error سامنے آئے تو اس کا بنیادی سبب الگ investigate کیا جائے؛ diagnostics کو ignoreErrors میں چھپا کر زبردستی سبز نہ بنایا جائے۔
- QA pass سے live deployment، actual DB migration، native adapter authorization یا backup/restore ثابت نہیں ہوتے۔

## حالات
Repository HEAD (baseline): `4fea4c9b4b6a69a34c798978f5857caa0011911a`۔
Deployed Version: غیر مصدقہ۔
DB Version: غیر مصدقہ۔
Migration State: غیر مصدقہ۔
Live Verification Status: نہیں ہوئی؛ Jetpack disconnected / WPVibe connect HTTP 530 کے شواہد بذاتِ خود root cause نہیں۔
