<?php
require 'config.php';
require 'functions.php';
$poem_id = (int)($_GET['id'] ?? 0);
if (!$poem_id) die('القصيدة المطلوبة غير موجودة.');

$stmt = $pdo->prepare("SELECT p.title, p.poem, p.bahr, p.qafiya, po.id AS poet_id, po.name AS poet_name, po.era AS poet_era FROM poems AS p JOIN poets AS po ON p.poet_id = po.id WHERE p.id = ?");
$stmt->execute([$poem_id]);
$poem_data = $stmt->fetch();
if (!$poem_data) die('القصيدة المطلوبة غير موجودة.');

$pageTitle = htmlspecialchars(str_replace('/', ' - ', $poem_data['title'])) . ' - ' . htmlspecialchars($poem_data['poet_name']);
require 'templates/header.php';
?>
<div class="page-container poem-page">
    <?php
    // تقسيم العنوان إلى شطرين (صدر وعجز) باستخدام الفاصل '/'
    $title_parts = explode('/', $poem_data['title'], 2);
    $title_sadr = trim($title_parts[0]);
    $title_ajz = isset($title_parts[1]) ? trim($title_parts[1]) : '';
    ?>

    <?php if ($title_ajz): // إذا كان العنوان يحتوي على شطرين، يتم عرضه في بطاقة خاصة ?>
        <div class="poem-title-card">
            <div class="sadr-title-part"><?= htmlspecialchars($title_sadr) ?></div>
            <div class="ajz-title-part"><?= htmlspecialchars($title_ajz) ?></div>
        </div>
    <?php else: // إذا كان العنوان من شطر واحد، يتم عرضه كعنوان عادي ?>
        <h1 class="poem-title-main-single"><?= htmlspecialchars($title_sadr) ?></h1>
    <?php endif; ?>

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
</div>

<?php require 'templates/footer.php'; ?>