<?php
require 'config.php';
require 'functions.php';
$id = (int)($_GET['id'] ?? 0);
if (!$id) die('الشاعر غير موجود.');

$stmt_poet = $pdo->prepare("SELECT * FROM poets WHERE id = ?");
$stmt_poet->execute([$id]);
$poet = $stmt_poet->fetch();
if (!$poet) die('الشاعر غير موجود.');

$stmt_poems = $pdo->prepare("SELECT id, title, bahr, qafiya FROM poems WHERE poet_id = ? ORDER BY id");
$stmt_poems->execute([$id]);
$poems = $stmt_poems->fetchAll();

$pageTitle = htmlspecialchars($poet['name']);
require 'templates/header.php';
?>

<div class="page-container">
    <div class="poet-info">
        <h1><?= htmlspecialchars($poet['name']) ?></h1>
        <div class="poet-era-badge"><?= htmlspecialchars($poet['era'] ?? 'عصر غير معروف') ?></div>
        <div class="poet-bio"><?= clean_poet_info($poet['info']) ?></div>
    </div>
    <h2 class="poet-poems-title">قصائده (<?= count($poems) ?> قصيدة)</h2>
    <div class="poems-list-grid">
        <?php foreach ($poems as $poem): ?>
            <a href="poem.php?id=<?= $poem['id'] ?>" class="poem-card">
                <h3 class="poem-card-title"><?= htmlspecialchars($poem['title']) ?></h3>
                <div class="poem-card-meta">
                    <span><strong>البحر:</strong> <?= htmlspecialchars($poem['bahr'] ?? 'غير محدد') ?></span>
                    <span><strong>القافية:</strong> <?= htmlspecialchars($poem['qafiya'] ?? 'غير محدد') ?></span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<?php require 'templates/footer.php'; ?>