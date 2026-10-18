<?php
require 'config.php';
require 'functions.php';
$poem_id = (int)($_GET['id'] ?? 0);
if (!$poem_id) die('القصيدة المطلوبة غير موجودة.');

$stmt = $pdo->prepare("SELECT p.title, p.poem, p.bahr, p.qafiya, po.id AS poet_id, po.name AS poet_name, po.era AS poet_era FROM poems AS p JOIN poets AS po ON p.poet_id = po.id WHERE p.id = ?");
$stmt->execute([$poem_id]);
$poem_data = $stmt->fetch();
if (!$poem_data) die('القصيدة المطلوبة غير موجودة.');

$explanations = [];
try {
    $explanationStmt = $pdo->prepare('SELECT explanation FROM poem_explanations WHERE poem_id = ? ORDER BY source_poet_id, source_poem_id');
    $explanationStmt->execute([$poem_id]);
    $explanations = array_values(array_unique($explanationStmt->fetchAll(PDO::FETCH_COLUMN)));
} catch (PDOException $e) {
    // يبقى عرض القصيدة متاحًا قبل تشغيل مستورد الشروح وإنشاء جدوله.
    error_log('تعذر تحميل شرح القصيدة: ' . $e->getMessage());
}

$pageTitle = htmlspecialchars($poem_data['title']) . ' - ' . htmlspecialchars($poem_data['poet_name']);
require 'templates/header.php';
?>
<div class="page-container poem-page">
    <h1 class="poem-title-main"><?= htmlspecialchars($poem_data['title']) ?></h1>
    <h2 class="poem-author">
        <a href="poet.php?id=<?= $poem_data['poet_id'] ?>"><?= htmlspecialchars($poem_data['poet_name']) ?></a>
    </h2>
    <div class="poem-meta-box"> 
        <div class="meta-item">
            <span class="meta-label">العصر</span>
            <span class="meta-value"><?= htmlspecialchars($poem_data['poet_era']) ?></span>
        </div>
        <?php if ($poem_data['bahr']): ?>
            <div class="meta-item"><span class="meta-label">البحر</span><span class="meta-value"><?= htmlspecialchars($poem_data['bahr']) ?></span></div>
        <?php endif; ?>
        <?php if ($poem_data['qafiya']): ?>
             <div class="meta-item"><span class="meta-label">القافية</span><span class="meta-value"><?= htmlspecialchars($poem_data['qafiya']) ?></span></div>
        <?php endif; ?>
    </div>
    <div class="poem-display-area">
        <?= format_poem($poem_data['poem']) ?>
    </div>
    <?php if (!empty($explanations)): ?>
        <section class="poem-explanation" aria-labelledby="poem-explanation-title">
            <h2 id="poem-explanation-title">شرح القصيدة</h2>
            <?php foreach ($explanations as $explanation): ?>
                <?php if (trim((string)$explanation) === '') continue; ?>
                <?php $explanationParts = split_poem_explanation((string)$explanation); ?>
                <div class="explanation-entry">
                    <?php if ($explanationParts['literary'] !== ''): ?>
                        <section class="explanation-part">
                            <h3>إضاءة أدبية</h3>
                            <div class="explanation-text"><?= nl2br(htmlspecialchars($explanationParts['literary'], ENT_QUOTES, 'UTF-8')) ?></div>
                        </section>
                    <?php endif; ?>
                    <?php if ($explanationParts['vocabulary'] !== ''): ?>
                        <section class="explanation-part">
                            <h3>معاني المفردات</h3>
                            <div class="explanation-text"><?= nl2br(htmlspecialchars($explanationParts['vocabulary'], ENT_QUOTES, 'UTF-8')) ?></div>
                        </section>
                    <?php endif; ?>
                    <?php if ($explanationParts['literary'] === '' && $explanationParts['vocabulary'] === '' && $explanationParts['general'] !== ''): ?>
                        <div class="explanation-part explanation-text"><?= nl2br(htmlspecialchars($explanationParts['general'], ENT_QUOTES, 'UTF-8')) ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <p class="explanation-note">هذا الشرح مولّد آليًا، ويُقرأ للاستئناس لا بوصفه تحقيقًا أدبيًا.</p>
        </section>
    <?php endif; ?>
</div>

<?php require 'templates/footer.php'; ?>
