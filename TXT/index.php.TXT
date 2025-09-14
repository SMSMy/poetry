<?php
require 'config.php';
require 'functions.php';
$eraFilter = $_GET['era'] ?? null;
$searchQuery = $_GET['q'] ?? null;
$pageTitle = 'ديوان العرب'; // عنوان افتراضي
require 'templates/header.php';
?>

<div class="page-container">

    <?php
    // ========= الحالة الأولى: إذا كان هناك عملية بحث =========
    if ($searchQuery):
        $pageTitle = 'نتائج البحث عن: "' . htmlspecialchars($searchQuery) . '"';
        echo "<script>document.title = '" . addslashes($pageTitle) . " - ديوان العرب';</script>";

        // كود البحث... (نفس الكود من الرد السابق، سأختصره هنا)
        $searchParam = "%" . $searchQuery . "%";
        $stmt_poets = $pdo->prepare("SELECT id, name, era FROM poets WHERE name LIKE ? ORDER BY name");
        $stmt_poets->execute([$searchParam]);
        $matchingPoets = $stmt_poets->fetchAll();
        $stmt_poems = $pdo->prepare("SELECT p.id, p.title, p.poem, po.name as poet_name, po.id as poet_id FROM poems p JOIN poets po ON p.poet_id = po.id WHERE p.title LIKE ? OR p.poem LIKE ?");
        $stmt_poems->execute([$searchParam, $searchParam]);
        $matchingPoems = $stmt_poems->fetchAll();
    ?>
        <h1 class="page-title"><?= $pageTitle ?></h1>
        <section class="search-results-section">
            <h2 class="results-subtitle">شعراء (<?= count($matchingPoets) ?>)</h2>
            <div class="search-results-list">
                <?php if(empty($matchingPoets)): ?><p class="no-results">لا توجد نتائج في أسماء الشعراء.</p><?php else: ?>
                    <?php foreach($matchingPoets as $poet): ?>
                    <a href="poet.php?id=<?= $poet['id'] ?>" class="poet-link-item">
                        <span class="poet-name"><?= htmlspecialchars($poet['name']) ?></span>
                        <span class="poet-era-badge"><?= htmlspecialchars($poet['era']) ?></span>
                    </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
        <section class="search-results-section">
            <h2 class="results-subtitle">قصائد (<?= count($matchingPoems) ?>)</h2>
            <div class="poem-search-results">
                 <?php if(empty($matchingPoems)): ?><p class="no-results">لا توجد نتائج في نصوص القصائد.</p><?php else: ?>
                    <?php foreach ($matchingPoems as $poem): 
                        preg_match('/^.*' . preg_quote($searchQuery, '/') . '.*$/imu', $poem['poem'], $matches);
                        $snippet = $matches[0] ?? substr($poem['poem'], 0, 150);
                    ?>
                        <a href="poem.php?id=<?= $poem['id'] ?>" class="poem-search-item">
                            <h3 class="poem-search-title"><?= htmlspecialchars($poem['title']) ?></h3>
                            <p class="poem-search-author">لـِ <?= htmlspecialchars($poem['poet_name']) ?></p>
                            <blockquote class="poem-search-snippet">... <?= str_ireplace($searchQuery, "<strong>$searchQuery</strong>", htmlspecialchars($snippet)) ?> ...</blockquote>
                        </a>
                    <?php endforeach; ?>
                 <?php endif; ?>
            </div>
        </section>

    <?php elseif ($eraFilter): // ========= عرض شعراء عصر معين =========
        $pageTitle = 'شعراء ' . htmlspecialchars($eraFilter);
        echo "<script>document.title = '" . addslashes($pageTitle) . " - ديوان العرب';</script>";
        $stmt = $pdo->prepare("SELECT id, name FROM poets WHERE era = ? ORDER BY name");
        $stmt->execute([$eraFilter]);
        $poets = $stmt->fetchAll();
    ?>
        <h1 class="page-title"><?= $pageTitle ?> (<?= count($poets) ?> شاعر)</h1>
        <a href="index.php" class="back-link">← العودة إلى كل العصور</a>
        <div class="poets-grid">
             <?php foreach ($poets as $poet): ?>
                <a href="poet.php?id=<?= $poet['id'] ?>" class="poet-card"><?= htmlspecialchars($poet['name']) ?></a>
            <?php endforeach; ?>
        </div>

    <?php else: // ========= الصفحة الرئيسية الافتراضية =========
        $pageTitle = 'فهرس الشعراء حسب العصور';
        echo "<script>document.title = 'ديوان العرب - " . addslashes($pageTitle) . "';</script>";
        $stmt_eras = $pdo->query("SELECT era, COUNT(id) as poet_count FROM poets WHERE era IS NOT NULL AND era != '' GROUP BY era ORDER BY FIELD(era, 'العصر الجاهلي', 'صدر الإسلام', 'الأموي', 'العباسي', 'الأندلسي', 'مملوكي', 'عثماني', 'العصر الحديث')");
        $eras = $stmt_eras->fetchAll();
    ?>
        <h1 class="page-title"><?= $pageTitle ?></h1>
        <?php foreach ($eras as $era): ?>
            <section class="era-section">
                <h2 class="era-title"><?= htmlspecialchars($era['era']) ?></h2>
                <div class="poets-grid">
                    <?php
                    $stmt_poets = $pdo->prepare("SELECT id, name FROM poets WHERE era = ? ORDER BY RAND() LIMIT 12");
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
</div>

<?php require 'templates/footer.php'; ?>