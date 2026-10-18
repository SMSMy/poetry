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
    'الجاهلي'      => 'العصر الجاهلي',
    'الإسلامي'     => 'صدر الإسلام',
    'العباسي'      => 'العباسي',
];

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
    $partIndex = 0;
    foreach ($parts as $part) {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $part)), function ($l) {
            return $l !== '';
        }));
        if (empty($lines)) {
            continue;
        }

        if ($partIndex === 0) {
            // السطر الأول: اسم الشاعر مشكولًا
            $html .= '<p class="poet-bio-name">' . htmlspecialchars(implode(' ', $lines), ENT_QUOTES, 'UTF-8') . '</p>';
        } elseif ($partIndex === 1) {
            // سطور التواريخ (الهجري والميلادي)
            $html .= '<p class="poet-dates">' . htmlspecialchars(implode(' — ', $lines), ENT_QUOTES, 'UTF-8') . '</p>';
        } else {
            // فقرات السيرة: كل سطر فقرة مستقلة
            foreach ($lines as $bioLine) {
                $html .= '<p>' . htmlspecialchars($bioLine, ENT_QUOTES, 'UTF-8') . '</p>';
            }
        }
        $partIndex++;
    }

    return $html;
}
