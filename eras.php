<?php
require 'config.php';
require 'functions.php';
$pageTitle = 'العصور الشعرية';
require 'templates/header.php';

$stmt = $pdo->query("SELECT era, COUNT(id) as poet_count FROM poets WHERE era IS NOT NULL AND era != '' AND era != 'عصر غير محدد' GROUP BY era ORDER BY FIELD(era, 'العصر الجاهلي', 'صدر الإسلام', 'الأموي', 'العباسي', 'الأندلسي', 'مملوكي', 'عثماني', 'العصر الحديث')");
$eras = $stmt->fetchAll();
?>

<div class="page-container">
    <h1 class="page-title"><?= $pageTitle ?></h1>
    <div class="eras-grid">
        <?php foreach ($eras as $era): ?>
            <a href="index.php?era=<?= urlencode($era['era']) ?>" class="era-card">
                <span class="era-name"><?= htmlspecialchars($era['era']) ?></span>
                <span class="era-count"><?= $era['poet_count'] ?> شاعر</span>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<?php require 'templates/footer.php'; ?>