# فائل 22 — مکمل production PHP static-analysis coverage: 12 نومبر 2026ء

**اس دستاویز کی حیثیت:** source-level QA coverage، staging/live incident یا deployed artifact evidence نہیں۔

## اصل مسئلہ
GitHub main SHA `4fea4c9b4b6a69a34c798978f5857caa0011911a` کی مکمل recursive Git tree میں `includes/` کے تحت **49** first-party production PHP source files موجود تھیں۔ `phpstan.neon.dist` کے paths (directory `includes/contracts` سمیت) صرف **46** کو کور کرتے تھے۔ تین paths شامل نہیں تھے:

1. `includes/core/class-governing-plan-runtime.php`
2. `includes/core/governing-plan-functions.php`
3. `includes/integration/class-file23-dashboard-adapter-runtime.php`

یہ کوئی ثابت شدہ live vulnerability نہیں؛ یہ automated static-analysis coverage gap ہے۔ موجودہ PHPCS ان فائلوں کا تجزیہ کرتا ہے، مگر PHPStan کی config میں ان کی عدمِ شمولیت مستقبل کے regressions کو پوشیدہ رکھ سکتی تھی۔

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
