<?php
/**
 * دمج بيانات مكتبة «ديوان» (poetry_data/json) في قاعدة البيانات.
 *
 * المصدر: ملف JSON لكل شاعر بصيغة:
 *   { id, name, era, description, poem_count,
 *     poems: [ { id, title, text, verse_count, meter, rhyme_label,
 *                rhyme_letter, poem_type, explanation, ... } ] }
 *
 * سياسة الدمج:
 *  - المطابقة بالاسم (مساواة بعد تنظيم الحروف) → دمج قصائدهم.
 *  - مطابقة بالاحتواء (يحتوي أحدهما الآخر) → دمج فقط إذا وُجد تداخل نصي يثبت أنهما ذات الشاعر،
 *    وإلا يُضاف الشاعر الجديد كذّات مستقل (إحترازًا من خلط شعراء مختلفين).
 *  - تفادي التكرار عبر مفتاح نص موحد (بلا حركات/مسافات/فواصل الشطرين) في poems.text_hash.
 *
 * الشروح («إضاءة أدبية») والوسوم: تُستبعد من هذا الاستيراد، المؤجلة لمرحلة قادمة
 * (متاحة لاحقًا: poems_explanations.csv و poem_tags.csv من نفس مكتبة ديوان).
 *
 * الاستخدام (CLI):
 *   php import_diwan.php [مجلد_البيانات]
 *   (الافتراضي: ../ديوان/poetry_data/json — وإذا عُرّف ثابت DIWAN_JSON_DIR في config.php فالسطر يعلوه)
 */

set_time_limit(0);
if (PHP_SAPI !== 'cli') {
    die('تشغّل هذا السكربت من سطر الأوامر (CLI) فقط لأنه استيراد ضخم.');
}
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

/* ======================= 1) الأدوات (قبل أي استخدام) ======================= */

$HARAKAT = '/[\x{0610}-\x{061A}\x{064B}-\x{0652}\x{0670}\x{06D6}-\x{06ED}\x{0640}]/u';

/** مفتاح نصي موحد لتفادي التكرار: بلا حركات، حروف موحدة، بلا فواصل/مسافات/علامات */
function text_key(string $text): string
{
    global $HARAKAT;
    $t = preg_replace($HARAKAT, '', $text) ?? $text;
    $t = strtr($t, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ة' => 'ه', 'ى' => 'ي', 'ؤ' => 'و', 'ئ' => 'ي']);
    $t = preg_replace('/[\s\W_]+/u', '', $t) ?? '';
    return md5($t);
}

/** طول سلسلة UTF-8 بالأحرف دون الاعتماد على امتداد mbstring */
function u8len(string $s): int
{
    return (int)preg_match_all('/./us', $s);
}

/** تطبيع اسم: توحيد الحروف + إزالة زوائد مثل '_' و " (2)" + تقليص المسافات */
function norm_name(string $s): string
{
    global $HARAKAT;
    $s = trim($s);
    $s = preg_replace($HARAKAT, '', $s) ?? $s;
    $s = strtr($s, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ة' => 'ه', 'ى' => 'ي', 'ؤ' => 'و', 'ئ' => 'ي']);
    $s = preg_replace('/ \(\d+\)$/u', '', $s) ?? $s; // نهاية " (2)" من ملفات مكررة
    $s = preg_replace('/[\s_]+$/u', '', $s) ?? $s;   // شرطة سفلية زائدة في بعض الأسماء
    return trim(preg_replace('/\s+/u', ' ', $s)) ?? $s;
}

/** تحويل نص القصيدة من صيغة «شطر لكل سطر» إلى صيغة الموقع «صدر / عجز \n» */
function convert_poem_text(string $text): string
{
    $lines = array_values(array_filter(
        array_map('trim', preg_split('/\r\n|\r|\n/', trim($text))),
        fn ($l) => $l !== ''
    ));
    if (empty($lines)) {
        return '';
    }
    $joined = [];
    $count = count($lines);
    $i = 0;
    while ($i < $count) {
        $line = $lines[$i];
        if (preg_match('/\//u', $line)) { // منسوق مسبقًا بصيغة «صدر / عجز»
            $joined[] = preg_replace('/\s+\/\s+/u', ' / ', $line);
            $i++;
            continue;
        }
        if ($i + 1 < $count && !preg_match('/\//u', $lines[$i + 1])) {
            $joined[] = $line . ' / ' . $lines[$i + 1];
            $i += 2;
        } else {
            $joined[] = $line; // شطر وحيد يبقى بلا عجز
            $i++;
        }
    }
    return implode("\n", $joined) . "\n";
}

/** اقتطاع آمن بعدد أحرف UTF-8 */
function utf8_cut(string $s, int $chars): string
{
    return (string)(preg_match('/^(.{0,' . $chars . '})/us', trim($s), $m) ? $m[1] : '');
}

function column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $stmt->execute([$table, $column]);
    return (bool)$stmt->fetchColumn();
}

/* ======================= 2) مجلد المصدر ======================= */

$diwanDir = $argv[1] ?? (defined('DIWAN_JSON_DIR') ? DIWAN_JSON_DIR : dirname(__DIR__) . '/ديوان/poetry_data/json');
$diwanDir = rtrim($diwanDir, '/\\');
if (!is_dir($diwanDir)) {
    fwrite(STDERR, "لم يُجل مجلد البيانات: $diwanDir\n");
    exit(1);
}

echo "بدء دمج بيانات «ديوان» من: $diwanDir\n";
echo "ملاحظة: الشروح والوسوم مستبعدة في هذه المرحلة (مؤجلة لجلسة قادمة).\n\n";

/* ======================= 3) هجرة المخطط ======================= */

if (!column_exists($pdo, 'poems', 'text_hash')) {
    $pdo->exec("ALTER TABLE poems ADD COLUMN text_hash CHAR(32) DEFAULT NULL");
    echo "أُضيف عمود poems.text_hash\n";
}
foreach (['verses_number' => 'INT DEFAULT NULL', 'rhyme_label' => 'VARCHAR(64) DEFAULT NULL', 'poem_type' => 'TINYINT DEFAULT NULL'] as $col => $def) {
    if (!column_exists($pdo, 'poems', $col)) {
        $pdo->exec("ALTER TABLE poems ADD COLUMN $col $def");
        echo "أُضيف عمود poems.$col\n";
    }
}
$stmt = $pdo->query("SHOW INDEX FROM poems WHERE Key_name = 'idx_poet_hash'");
if (!$stmt->fetch()) {
    $pdo->exec("ALTER TABLE poems ADD INDEX idx_poet_hash (poet_id, text_hash)");
    echo "أُضيف فهرس idx_poet_hash\n";
}

/* ======================= 4) ملء text_hash للقصائد القديمة ======================= */

$todo = (int)$pdo->query("SELECT COUNT(*) FROM poems WHERE text_hash IS NULL")->fetchColumn();
if ($todo > 0) {
    echo "حساب مفاتيح النص لقصائد القاعدة الحالية ($todo قصيدة)...\n";
    $stmt = $pdo->query("SELECT id, poem FROM poems WHERE text_hash IS NULL");
    $upd = $pdo->prepare("UPDATE poems SET text_hash = ? WHERE id = ?");
    $n = 0;
    while ($poemRow = $stmt->fetch(PDO::FETCH_NUM)) {
        list($id, $poem) = $poemRow;
        $upd->execute([text_key((string)$poem), $id]);
        if (++$n % 10000 === 0) echo "  ...$n\n";
    }
    echo "تم حساب $n مفتاحًا.\n\n";
}

/* ======================= 5) الفهرس والمطابقة ======================= */

// شعراء القاعدة الحاليون
$poetsMap  = []; // normName => id
$poetsList = []; // [id, norm]
foreach ($pdo->query("SELECT id, name FROM poets") as $row) {
    $norm = norm_name($row['name']);
    $poetsMap[$norm] = (int)$row['id'];
    $poetsList[] = ['id' => (int)$row['id'], 'norm' => $norm];
}

$indexFile = $diwanDir . '/_index.json';
if (!is_file($indexFile)) {
    fwrite(STDERR, "لم يُجل فهرس _index.json داخل $diwanDir\n");
    exit(1);
}
$index = json_decode(file_get_contents($indexFile), true);
$diwanPoets = array_values(array_filter($index['poets'] ?? [], fn ($p) => !empty($p['has_poems'])));

$counter = ['poets_new' => 0, 'poems_new' => 0, 'poems_dup' => 0, 'merged' => 0, 'standalone' => 0, 'files' => 0];
$insertGuard = []; // مفاتيح الشعراء الجدد المضافين في هذه الجلسة (لتجنب 1062 عند تكرار المسمى)

$selHashes = $pdo->prepare("SELECT text_hash FROM poems WHERE poet_id = ? AND text_hash IS NOT NULL");
$findPoet  = $pdo->prepare("SELECT id FROM poets WHERE name = ?");
$insPoet   = $pdo->prepare("INSERT INTO poets (name, era, info) VALUES (?, ?, ?)");
$insPoem   = $pdo->prepare(
    "INSERT INTO poems (poet_id, title, poem, bahr, qafiya, verses_number, rhyme_label, poem_type, text_hash)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
);

$pdo->beginTransaction();
try {
    foreach ($diwanPoets as $dp) {
        $file = $diwanDir . '/' . $dp['file'];
        if (!is_file($file)) continue;
        $counter['files']++;

        $data = json_decode(file_get_contents($file), true);
        if (!is_array($data) || empty($data['poems'])) continue;

        $name = trim((string)($data['name'] ?? $dp['name']));
        $desc = trim((string)($data['description'] ?? ''));
        $era  = normalize_era((string)($data['era'] ?? $dp['era'] ?? ''));
        $norm = norm_name($name);

        // تجميع القصائد الجديدة بمفاتيحها
        $newPoems = [];
        foreach ($data['poems'] as $pm) {
            $text = (string)($pm['text'] ?? '');
            if (trim($text) === '') continue;
            $newPoems[] = [
                'title'       => utf8_cut((string)($pm['title'] ?? ''), 500),
                'poem'        => convert_poem_text($text),
                'key'         => text_key($text),
                'bahr'        => utf8_cut((string)($pm['meter'] ?? ''), 100),
                'qafiya'      => utf8_cut((string)($pm['rhyme_letter'] ?? ''), 100),
                'rhyme_label' => utf8_cut((string)($pm['rhyme_label'] ?? ''), 64),
                'verses'      => isset($pm['verse_count']) ? (int)$pm['verse_count'] : null,
                'type'        => isset($pm['poem_type']) ? (int)$pm['poem_type'] : null,
            ];
        }
        if (empty($newPoems)) continue;

        /* اختيار الشاعر المستهدف: مساواة أولًا ثم احتواء */
        $targetId = $poetsMap[$norm] ?? null;
        if ($targetId === null) {
            foreach ($poetsList as $existing) {
                $short = u8len($norm) <= u8len($existing['norm']) ? $norm : $existing['norm'];
                $long  = u8len($norm) <= u8len($existing['norm']) ? $existing['norm'] : $norm;
                if (u8len($short) < 4 || u8len($short) > 12) continue;
                if (preg_match('/(?<!\S)' . preg_quote($short, '/') . '(?!\S)/u', $long)) {
                    $targetId = $existing['id'];
                    break;
                }
            }
        }

        if ($targetId !== null) {
            // التحقق من المصاهرة عند الاحتواء: تكفي قصيدة مشتركة واحدة
            $selHashes->execute([$targetId]);
            $knownSet = array_flip($selHashes->fetchAll(PDO::FETCH_COLUMN));
            $inter = 0;
            foreach ($newPoems as $pm) {
                if (isset($knownSet[$pm['key']])) $inter++;
            }
            if ($inter === 0 && !isset($poetsMap[$norm])) {
                // لا معلومات مشتركة → اسم متشابه من فنون مختلفة؛ أضف مستقلًا
                $insPoet->execute([$name, $era, trim($name) . "\n*\n" . $desc]);
                $targetId = (int)$pdo->lastInsertId();
                $poetsMap[$norm] = $targetId;
                $poetsList[] = ['id' => $targetId, 'norm' => $norm];
                $counter['poets_new']++; $counter['standalone']++;
                $knownSet = [];
            } else {
                $counter['merged']++;
            }
        } else {
            // شاعر جديد تمامًا
            try {
                $insPoet->execute([$name, $era, trim($name) . "\n*\n" . $desc]);
                $targetId = (int)$pdo->lastInsertId();
            } catch (PDOException $pe) {
                if ($pe->getCode() !== '23000') throw $pe;
                // اسم مكرر من سجلات مصدر متناقضة: نستعيد المعرف من الجدول
                $findPoet->execute([$name]);
                $targetId = (int)$findPoet->fetchColumn();
            }
            if (!isset($poetsMap[$norm])) {
                $poetsMap[$norm] = $targetId;
            }
            $poetsList[] = ['id' => $targetId, 'norm' => $norm];
            $counter['poets_new']++; $counter['standalone']++;
            $knownSet = [];
        }

        // الاستيراد الفعلي مع تفادي التكرار
        foreach ($newPoems as $pm) {
            if (isset($knownSet[$pm['key']])) {
                $counter['poems_dup']++;
                continue;
            }
            $insPoem->execute([
                $targetId, $pm['title'], $pm['poem'], $pm['bahr'],
                $pm['qafiya'], $pm['verses'], $pm['rhyme_label'], $pm['type'], $pm['key'],
            ]);
            $knownSet[$pm['key']] = true;
            $counter['poems_new']++;
        }
        unset($knownSet);

        if ($counter['files'] % 50 === 0) {
            $pdo->commit();
            $pdo->beginTransaction();
            echo "  ملفات: {$counter['files']} | شعراء جدد: {$counter['poets_new']} | قصائد جدد: {$counter['poems_new']} | مكرر محذوف: {$counter['poems_dup']}\n";
        }
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}

echo "\n=== نتيجة الدمج ===\n";
echo "ملفات مصدر معالجة:      {$counter['files']}\n";
echo "شعراء المطابَقون:        {$counter['merged']}\n";
echo "شعراء مستقل مضاف:        {$counter['standalone']} (إجمالي شعراء جدد: {$counter['poets_new']})\n";
echo "قصائد مضافة:             {$counter['poems_new']}\n";
echo "قصائد مكرر تخطّت (متطابقة نصًا): {$counter['poems_dup']}\n";

$lead = $pdo->query("SELECT (SELECT COUNT(*) FROM poets) poets, (SELECT COUNT(*) FROM poems) poems")->fetch();
echo "حالة القاعدة الآن: {$lead['poets']} شاعر | {$lead['poems']} قصيدة\n";
