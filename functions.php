<?php
/**
 * الدوال المساعدة المشتركة للمشروع.
 * 
 * ملاحظة: النسخة الأصلية لهذا الملف ضاعت (حُفظ مكانها كود poem.php بالخطأ
 * وأُُُرفع كذلك إلى GitHub)، فأُعيد بناؤها مطابقةً لطريقة استخدامها
 * في poem.php و poet.php و import.php، وبما يوافق التنسيقات الموجودة
 * في CSS/style.css (بطاقة البيت .poem-card بعمودي .sadr-part و .ajz-part).
 */

/** خريطة توحيد أسماء العصور (ما كان مستخدمًا في era_data.php بصيغ مختلفة) */
$POETRY_ERA_ALIASES = [
    // من era_data.php القديم
    'الجاهلي' => 'العصر الجاهلي',
    'إسلامي'  => 'صدر الإسلام',
    'الإسلامي' => 'صدر الإسلام',
    'مخضرم (جاهلي وإسلامي)' => 'المخضرمون',
    'مخضرم'   => 'المخضرمون',

    // من ملفات poetry_data الجديدة (الأسماء: العصر الجاهلي، العصر الإسلامي، ...)
    'عصر إسلامي'   => 'صدر الإسلام',
    'العصر الإسلامي' => 'صدر الإسلام',
    'العصر الأموي'  => 'الأموي',
    'العصر العباسي' => 'العباسي',
    'العصر الأندلسي' => 'الأندلسي',
    'العصر الأيوبي' => 'الأيوبي',
    'المملوكي'      => 'مملوكي',
    'العصر المملوكي' => 'مملوكي',
    'العصر العثماني' => 'عثماني',
    'حديث'          => 'العصر الحديث',
];

/** الترتيب المعياري للعصور المستخدم في جميع الصفحات */
$POETRY_ERA_ORDER = [
    'العصر الجاهلي', 'المخضرمون', 'صدر الإسلام', 'الأموي', 'العباسي',
    'الأندلسي', 'الأيوبي', 'مملوكي', 'عثماني', 'العصر الحديث',
];

/** توحيد اسم عصر إلى صيغته المعيارية */
function normalize_era(string $era): string
{
    global $POETRY_ERA_ALIASES;
    $era = trim($era);
    return $POETRY_ERA_ALIASES[$era] ?? $era;
}

/**
 * تحديد عصر الشاعر من القائمة المرجعية era_data.php.
 * 
 * @param string $poetName اسم الشاعر (كما في اسم الملف)
 * @return string|null اسم العصر أو null إن لم يوجد
 */
function deduce_era(string $poetName): ?string
{
    global $POETRY_ERA_ALIASES;
    static $map = null;

    if ($map === null) {
        // era_data.php يُعيد مصفوفة (اسم الشاعر => العصر)
        $map = require __DIR__ . '/era_data.php';
        if (!is_array($map)) {
            $map = [];
        }
    }

    if (!isset($map[$poetName])) {
        return null;
    }

    $era = trim($map[$poetName]);

    // توحيد التسمية مع باقي صفحات الموقع (index.php و eras.php)
    return $POETRY_ERA_ALIASES[$era] ?? $era;
}

/**
 * تنسيق نص القصيدة إلى بطاقات أبيات (صدر / عجز) لعرضها في poem.php.
 * 
 * نص القصيدة في قاعدة البيانات: شطران متقابلان يفصل بينهما ' / '
 * والأبيات يفصل بينها سطر جديد \n.
 * 
 * ملاحظة: الدالة تُخرج بطاقات البيت فقط؛ حاوية .poem-display-area
 * موجودة في صفحة poem.php نفسها.
 * 
 * @param string $poemText نص القصيدة الخام كما في قاعدة البيانات
 * @return string HTML بطاقات الأبيات
 */
function format_poem(string $poemText): string
{
    $html = '';

    // تقسيم القصيدة إلى أبيات (سطر لكل بيت) مع دعم مختلف نهايات الأسطر
    $lines = preg_split('/\r\n|\r|\n/', trim($poemText));
    if ($lines === false) {
        return '';
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        // فصل الصدر عن العجز بأول '/' فقط
        $hemistiches = explode('/', $line, 2);
        $sadr = trim(preg_replace('/\s+/u', ' ', $hemistiches[0]));
        $ajz  = isset($hemistiches[1]) ? trim(preg_replace('/\s+/u', ' ', $hemistiches[1])) : '';

        if ($sadr === '' && $ajz === '') {
            continue;
        }

        $html .= '<div class="poem-card">';
        $html .= '<div class="sadr-part">' . htmlspecialchars($sadr, ENT_QUOTES, 'UTF-8') . '</div>';
        if ($ajz !== '') {
            $html .= '<div class="ajz-part">' . htmlspecialchars($ajz, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        $html .= '</div>';
    }

    return $html;
}

/**
 * فصل شرح مكتبة ديوان إلى الإضاءة الأدبية ومعاني المفردات.
 * إذا لم تحمل البيانات العناوين المعروفة، تُعاد كاملةً كنص عام.
 *
 * @return array{literary:string,vocabulary:string,general:string}
 */
function split_poem_explanation(string $explanation): array
{
    $result = ['literary' => '', 'vocabulary' => '', 'general' => ''];
    $pattern = '/(?:^|\R)[\t ]*(إضاءة أدبية|معاني المفردات)[\t ]*(?:\R|$)/u';
    $found = preg_match_all($pattern, $explanation, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

    if (!$found) {
        $result['general'] = trim($explanation);
        return $result;
    }

    foreach ($matches as $index => $match) {
        $heading = $match[1][0];
        $start = $match[0][1] + strlen($match[0][0]);
        $end = isset($matches[$index + 1]) ? $matches[$index + 1][0][1] : strlen($explanation);
        $content = trim(substr($explanation, $start, $end - $start));
        $key = $heading === 'إضاءة أدبية' ? 'literary' : 'vocabulary';

        // لا نطمس قسمًا سابقًا إذا تكرر عنوانه في نص المصدر.
        if ($result[$key] === '') {
            $result[$key] = $content;
        }
    }

    return $result;
}

/**
 * تنسيق نص سيرة الشاعر الخام إلى HTML مُهيكل.
 * 
 * صيغة السيرة الخام في ملفات JSON وبقاعدة البيانات:
 *   الاسم\n*\nمولده بالهجري\nمولده بالميلادي\n*\nنص السيرة الطويل
 * 
 * @param string $info نص السيرة الخام
 * @return string HTML فقرات السيرة (توضع داخل div.poet-bio)
 */
function clean_poet_info(string $info): string
{
    if (trim($info) === '') {
        return '';
    }

    // إزالة محارف BOM وأحرف التحكم المتبقية
    $info = preg_replace('/^[\x{FEFF}\s]+/u', '', $info);

    // تجزئة على النجمة *
    $parts = array_map('trim', explode('*', $info));
    $parts = array_values(array_filter($parts, function ($p) {
        return $p !== '';
    }));

    if (empty($parts)) {
        return '';
    }

    $html = '';

    // الجزء الأول: اسم الشاعر المشكول (يُعرض أيضًا في h1 صفحة الشاعر)
    $namePart = array_shift($parts);
    $nameLines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $namePart)), function ($l) {
        return $l !== '';
    }));
    if (!empty($nameLines)) {
        $html .= '<p class="poet-bio-name">' . htmlspecialchars(implode(' ', $nameLines), ENT_QUOTES, 'UTF-8') . '</p>';
    }

    // الأجزاء التالية نقاسمها: تواريخ (أسطر قصيرة أرقام) أو فقرات سيرة.
    // يتكيف تلقائيًا مع صيغة era_data.php القديمة ووصف ملفات poetry_data الجديدة.
    $isDatesLine = function (string $line): bool {
        // سطر تاريخ مثل: "303 - 354 ه" أو "915 - 965 م" (قصير وفيه أرقام)
        // قياس الطول بالأحرف عبر /u بدل mb_strlen (توافقًا مع مضيفات بلا mbstring)
        return preg_match('/^.{0,25}$/us', $line) === 1 && preg_match('/[\d\x{0660}-\x{0669}]/u', $line) === 1;
    };

    foreach ($parts as $part) {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $part)), function ($l) {
            return $l !== '';
        }));
        if (empty($lines)) {
            continue;
        }

        // أجزاء أسطرها كلها قصيرة التواريخ → بطاقة تواريخ
        $datesPart = count($lines) <= 3
            && array_product(array_map(fn ($l) => (int)$isDatesLine($l), $lines)) === 1;

        if ($datesPart) {
            $html .= '<p class="poet-dates">' . htmlspecialchars(implode(' — ', $lines), ENT_QUOTES, 'UTF-8') . '</p>';
        } else {
            // فقرات السيرة: كل سطر فقرة مستقلة
            foreach ($lines as $bioLine) {
                $html .= '<p>' . htmlspecialchars($bioLine, ENT_QUOTES, 'UTF-8') . '</p>';
            }
        }
    }

    return $html;
}
