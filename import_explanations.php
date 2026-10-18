<?php
/**
 * استيراد شروح مكتبة ديوان وربطها بقصائد الموقع عبر بصمة النص.
 *
 * الاستخدام:
 *   php import_explanations.php [poems_explanations.csv] [مجلد_json]
 *
 * يتطلب تشغيل import_diwan.php سابقًا حتى يكون عمود poems.text_hash موجودًا.
 */

set_time_limit(0);
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "شغّل هذا السكربت من سطر الأوامر فقط.\n");
    exit(1);
}

$configPath = getenv('POETRY_CONFIG') ?: (__DIR__ . '/config.php');
if (!is_file($configPath)) {
    fwrite(STDERR, "ملف إعداد قاعدة البيانات غير موجود: $configPath\n");
    exit(1);
}
require_once $configPath;

$defaultDataDir = dirname(__DIR__, 2) . '/ديوان/poetry_data';
$csvPath = $argv[1] ?? ($defaultDataDir . '/csv/poems_explanations.csv');
$jsonDir = rtrim($argv[2] ?? ($defaultDataDir . '/json'), '/\\');

if (!is_file($csvPath)) {
    fwrite(STDERR, "ملف الشروح غير موجود: $csvPath\nمرّر مساره كوسيط أول.\n");
    exit(1);
}
if (!is_dir($jsonDir) || !is_file($jsonDir . '/_index.json')) {
    fwrite(STDERR, "مجلد JSON أو ملف _index.json غير موجود: $jsonDir\n");
    exit(1);
}

function explanation_text_key(string $text): string
{
    $harakat = '/[\x{0610}-\x{061A}\x{064B}-\x{0652}\x{0670}\x{06D6}-\x{06ED}\x{0640}]/u';
    $text = preg_replace($harakat, '', $text) ?? $text;
    $text = strtr($text, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ة' => 'ه', 'ى' => 'ي', 'ؤ' => 'و', 'ئ' => 'ي']);
    $text = preg_replace('/[\s\W_]+/u', '', $text) ?? '';
    return md5($text);
}

function explanation_normalize_name(string $name): string
{
    $harakat = '/[\x{0610}-\x{061A}\x{064B}-\x{0652}\x{0670}\x{06D6}-\x{06ED}\x{0640}]/u';
    $name = preg_replace($harakat, '', trim($name)) ?? trim($name);
    $name = strtr($name, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ة' => 'ه', 'ى' => 'ي', 'ؤ' => 'و', 'ئ' => 'ي']);
    $name = preg_replace('/ \(\d+\)$/u', '', $name) ?? $name;
    $name = preg_replace('/[\s_]+$/u', '', $name) ?? $name;
    return trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
}

function explanation_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (bool)$stmt->fetchColumn();
}

function explanation_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

if (!explanation_column_exists($pdo, 'poems', 'text_hash')) {
    fwrite(STDERR, "لا يوجد poems.text_hash. شغّل import_diwan.php أولًا.\n");
    exit(1);
}

// احتفظ بنسخة من جدول الإصدار الأول إن كان موجودًا قبل ترقية مفتاحه.
$hasExplanationTable = explanation_table_exists($pdo, 'poem_explanations');
if ($hasExplanationTable && !explanation_column_exists($pdo, 'poem_explanations', 'source_poem_id')) {
    if (explanation_table_exists($pdo, 'poem_explanations_legacy')) {
        fwrite(STDERR, "وجدت جدول شروح قديمًا ونسخة احتياطية سابقة؛ راجع الجداول قبل الترقية.\n");
        exit(1);
    }
    $pdo->exec('RENAME TABLE poem_explanations TO poem_explanations_legacy');
}

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS poem_explanations (
        source_poet_id INT UNSIGNED NOT NULL,
        source_poem_id INT UNSIGNED NOT NULL,
        poem_id INT UNSIGNED NOT NULL,
        PRIMARY KEY (source_poet_id, source_poem_id),
        KEY idx_explanation_poem (poem_id),
        explanation LONGTEXT NOT NULL,
        imported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$index = json_decode(file_get_contents($jsonDir . '/_index.json'), true);
if (!is_array($index) || !isset($index['poets']) || !is_array($index['poets'])) {
    fwrite(STDERR, "تعذر قراءة قائمة الشعراء من _index.json.\n");
    exit(1);
}

echo "قراءة قصائد المصدر وربطها بقاعدة الموقع...\n";
$sourcePoems = []; // source poet id => source poem id => normalized text hash
$sourcePoetNames = [];
$filesRead = 0;
foreach ($index['poets'] as $entry) {
    if (empty($entry['has_poems']) || empty($entry['file'])) {
        continue;
    }

    $filePath = $jsonDir . '/' . basename((string)$entry['file']);
    if (!is_file($filePath)) {
        continue;
    }
    $data = json_decode(file_get_contents($filePath), true);
    if (!is_array($data) || empty($data['poems']) || !isset($data['id'])) {
        continue;
    }

    $sourcePoetId = (string)$data['id'];
    $sourcePoetNames[$sourcePoetId] = (string)($data['name'] ?? $entry['name'] ?? '');
    foreach ($data['poems'] as $sourcePoem) {
        if (!isset($sourcePoem['id'], $sourcePoem['text']) || trim((string)$sourcePoem['text']) === '') {
            continue;
        }
        $sourcePoems[$sourcePoetId][(string)$sourcePoem['id']] = explanation_text_key((string)$sourcePoem['text']);
    }
    $filesRead++;
}

if (!$sourcePoems) {
    fwrite(STDERR, "لم أجد قصائد في ملفات JSON.\n");
    exit(1);
}

$dbPoemByPoetHash = [];
$dbPoetsByHash = [];
$targetPoetNames = [];
foreach ($pdo->query('SELECT id, name FROM poets') as $row) {
    $targetPoetNames[(string)$row['id']] = explanation_normalize_name((string)$row['name']);
}
foreach ($pdo->query('SELECT id, poet_id, text_hash FROM poems WHERE text_hash IS NOT NULL') as $row) {
    $hash = (string)$row['text_hash'];
    $poetId = (string)$row['poet_id'];
    // القصائد المستوردة مميزة بهذا الفهرس؛ نحتفظ بأول سجل عند وجود تكرار قديم.
    $dbPoemByPoetHash[$poetId][$hash] ??= (int)$row['id'];
    $dbPoetsByHash[$hash][$poetId] = true;
}

// يُستدل على الشاعر المقابل من أكثر قصائده المصدرية تطابقًا مع كل شاعر في قاعدة الموقع.
$votes = [];
foreach ($sourcePoems as $sourcePoetId => $poems) {
    foreach ($poems as $hash) {
        foreach (array_keys($dbPoetsByHash[$hash] ?? []) as $targetPoetId) {
            $votes[$sourcePoetId][$targetPoetId] = ($votes[$sourcePoetId][$targetPoetId] ?? 0) + 1;
        }
    }
}

$sourceToTargetPoet = [];
$ambiguousPoets = 0;
foreach ($votes as $sourcePoetId => $poetVotes) {
    arsort($poetVotes, SORT_NUMERIC);
    $targetIds = array_keys($poetVotes);
    $topCount = reset($poetVotes);
    $secondCount = count($poetVotes) > 1 ? $poetVotes[$targetIds[1]] : 0;
    if ($topCount === $secondCount) {
        $sourceName = explanation_normalize_name($sourcePoetNames[$sourcePoetId] ?? '');
        $nameMatches = array_values(array_filter(
            $targetIds,
            fn ($targetId) => ($targetPoetNames[$targetId] ?? '') === $sourceName
        ));
        if (count($nameMatches) !== 1) {
            $ambiguousPoets++;
            continue;
        }
        $sourceToTargetPoet[$sourcePoetId] = (string)$nameMatches[0];
        continue;
    }
    $sourceToTargetPoet[$sourcePoetId] = (string)$targetIds[0];
}

unset($votes);
echo "تمت قراءة $filesRead ملف شاعر؛ تطابق الشعراء: " . count($sourceToTargetPoet) . "؛ تطابق ملتبس متروك: $ambiguousPoets.\n";

$handle = fopen($csvPath, 'rb');
if ($handle === false) {
    fwrite(STDERR, "تعذر فتح ملف الشروح.\n");
    exit(1);
}
$bom = fread($handle, 3);
if ($bom !== "\xEF\xBB\xBF") {
    rewind($handle);
}
$headers = fgetcsv($handle, null, ',', '"', '');
if (!is_array($headers)) {
    fclose($handle);
    fwrite(STDERR, "ملف CSV فارغ أو غير صالح.\n");
    exit(1);
}
$headerMap = array_flip($headers);
foreach (['id', 'poet_id', 'explanation'] as $required) {
    if (!isset($headerMap[$required])) {
        fclose($handle);
        fwrite(STDERR, "عمود CSV المطلوب غير موجود: $required\n");
        exit(1);
    }
}

$upsert = $pdo->prepare(
    'INSERT INTO poem_explanations (source_poet_id, source_poem_id, poem_id, explanation) VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE poem_id = VALUES(poem_id), explanation = VALUES(explanation), imported_at = CURRENT_TIMESTAMP'
);
$counts = ['rows' => 0, 'imported' => 0, 'unmatched' => 0, 'empty' => 0, 'invalid' => 0];
$pdo->beginTransaction();
try {
    while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) {
        $counts['rows']++;
        if (count($row) !== count($headers)) {
            $counts['invalid']++;
            continue;
        }

        $sourcePoemId = trim((string)$row[$headerMap['id']]);
        $sourcePoetId = trim((string)$row[$headerMap['poet_id']]);
        $explanation = trim((string)$row[$headerMap['explanation']]);
        if ($explanation === '') {
            $counts['empty']++;
            continue;
        }
        if (!isset($sourcePoems[$sourcePoetId][$sourcePoemId], $sourceToTargetPoet[$sourcePoetId])) {
            $counts['unmatched']++;
            continue;
        }

        $hash = $sourcePoems[$sourcePoetId][$sourcePoemId];
        $targetPoetId = $sourceToTargetPoet[$sourcePoetId];
        $poemId = $dbPoemByPoetHash[$targetPoetId][$hash] ?? null;
        if ($poemId === null) {
            $counts['unmatched']++;
            continue;
        }

        $upsert->execute([$sourcePoetId, $sourcePoemId, $poemId, $explanation]);
        $counts['imported']++;
        if ($counts['rows'] % 1000 === 0) {
            $pdo->commit();
            $pdo->beginTransaction();
            echo "  سجل CSV: {$counts['rows']} | مستورد: {$counts['imported']} | غير مطابق: {$counts['unmatched']}\n";
        }
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fclose($handle);
    throw $e;
}
fclose($handle);

echo "\n=== نتيجة استيراد الشروح ===\n";
echo "سجلات CSV المقروءة: {$counts['rows']}\n";
echo "شروح رُبطت بقصائد الموقع: {$counts['imported']}\n";
echo "سجلات غير مطابقة: {$counts['unmatched']}\n";
echo "سجلات شرح فارغة: {$counts['empty']}\n";
echo "سجلات CSV غير صالحة: {$counts['invalid']}\n";
