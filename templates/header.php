<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= (isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' : '') ?>ديوان العرب</title>
    <!-- استدعاء الخطوط -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- استدعاء ملف التصميم الرئيسي -->
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="light-mode">
    <header>
        <div class="container">
            <div class="logo">
                <a href="index.php">ديوان العرب</a>
            </div>
            <nav>
                <a href="index.php" class="nav-link">الرئيسية</a>
                <a href="eras.php" class="nav-link">العصور</a>
            </nav>
            <div class="search-container">
                <form method="GET" action="index.php">
                    <button type="submit" class="search-button">🔍</button>
                    <input type="text" name="q" class="search-input" placeholder="ابحث عن شاعر أو قصيدة..." value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
                </form>
            </div>
            <button id="theme-toggle">🌙</button>
        </div>
    </header>
    <main>