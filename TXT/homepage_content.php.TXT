<?php
// هذا الكود هو نفس كود الصفحة الرئيسية السابق الذي يعرض العصور والشعراء

$eraFilter = $_GET['era'] ?? null;

if ($eraFilter): // حالة فلترة عصر معين
    ?>
    <h1 class="page-title">شعراء <?= htmlspecialchars($eraFilter) ?></h1>
    <a href="index.php" class="back-link">← العودة إلى كل العصور</a>
    <?php
    $stmt = $pdo->prepare("SELECT id, name FROM poets WHERE era = ? ORDER BY name");
    $stmt->execute([$eraFilter]);
    $poets = $stmt->fetchAll();
    ?>
    <div class="poets-grid">
         <?php foreach ($poets as $poet): ?>
            <a href="poet.php?id=<?= $poet['id'] ?>" class="poet-card"><?= htmlspecialchars($poet['name']) ?></a>
        <?php endforeach; ?>
    </div>

<?php else: // الحالة الافتراضية
    ?>
    <h1 class="page-title">فهرس الشعراء حسب العصور</h1>
    <?php
    $stmt_eras = $pdo->query("SELECT era, COUNT(id) as poet_count FROM poets WHERE era IS NOT NULL AND era != '' GROUP BY era ORDER BY FIELD(era, 'العصر الجاهلي', 'العصر الإسلامي', 'العصر العباسي', 'عصر الدول والإمارات', 'العصر الحديث')");
    $eras = $stmt_eras->fetchAll();

    foreach ($eras as $era): ?>
        <section class="era-section">
            <h2 class="era-title"><?= htmlspecialchars($era['era']) ?></h2>
            <div class="poets-grid">
                <?php
                $stmt_poets = $pdo->prepare("SELECT id, name FROM poets WHERE era = ? ORDER BY name LIMIT 12");
                $stmt_poets->execute([$era['era']]);
                $poets = $stmt_poets->fetchAll();
                foreach ($poets as $poet): ?>
                    <a href="poet.php?id=<?= $poet['id'] ?>" class="poet-card"><?= htmlspecialchars($poet['name']) ?></a>
                <?php endforeach; ?>
            </div>
            <?php if ($era['poet_count'] > 12): ?>
                <div class="view-all-container">
                    <a href="index.php?era=<?= urlencode($era['era']) ?>" class="view-all-link">عرض كل شعراء العصر (<?= $era['poet_count'] ?>) »</a>
                </div>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
<?php endif; ?>