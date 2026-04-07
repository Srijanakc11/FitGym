<?php
session_start();
require_once __DIR__ . '/dynamic_content.php';

$classes = fitgym_get_classes();

$searchQuery = trim($_GET['q'] ?? '');
$filterCategory = trim($_GET['filter'] ?? 'all');

$categories = [];
foreach ($classes as $item) {
    $cat = trim((string)($item['category'] ?? ''));
    if ($cat !== '') {
        $categories[$cat] = (string)($item['category_label'] ?? fitgym_labelize_token($cat));
    }
}
$categoryMap = $categories;
$categories = array_keys($categoryMap);
sort($categories);

$visibleClasses = array_values(array_filter($classes, static function ($item) use ($searchQuery, $filterCategory) {
    $title = mb_strtolower((string)($item['title'] ?? ''));
    $category = mb_strtolower((string)($item['category'] ?? ''));
    $description = mb_strtolower((string)($item['description'] ?? ''));

    $filter = mb_strtolower($filterCategory);
    if ($filter !== 'all' && $filter !== '' && $category !== $filter) {
        return false;
    }

    $search = mb_strtolower($searchQuery);
    if ($search === '') {
        return true;
    }

    return mb_stripos($title, $search) !== false
        || mb_stripos($category, $search) !== false
        || mb_stripos($description, $search) !== false;
}));

$pageHeader = fitgym_block('classes_header', 'Our Fitness Classes', 'Choose from multiple programs designed for strength, cardio, flexibility, weight loss and recovery.');
$recoTeaser = fitgym_block('classes_recommend_teaser', 'Smart Class Recommendations', 'Get quick TDEE-based suggestions for class intensity and weekly frequency.');
$recoBtnLabel = fitgym_setting('classes_recommend_cta_label', 'Get Recommendations');
$recoBtnUrl = fitgym_setting('classes_recommend_cta_url', fitgym_url('/php/recommend.php'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= fitgym_esc(fitgym_setting('site_name', 'FitGym')) ?> - Classes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="icon" href="<?= fitgym_esc(fitgym_asset_url('/pictures/favicon.png')) ?>">
    <link rel="stylesheet" href="<?= fitgym_esc(fitgym_asset_url('/css/header.css')) ?>">
    <link rel="stylesheet" href="<?= fitgym_esc(fitgym_asset_url('/css/footer.css')) ?>">
    <link rel="stylesheet" href="<?= fitgym_esc(fitgym_asset_url('/css/classes.css')) ?>">
    <link rel="stylesheet" href="<?= fitgym_esc(fitgym_asset_url('/css/index.css')) ?>">
</head>

<body>
    <?php include "header.php"; ?>

    <main class="content">
        <section class="page-header">
            <h2><?= fitgym_esc($pageHeader['title']) ?></h2>
            <p class="lead"><?= fitgym_esc($pageHeader['body']) ?></p>
        </section>

        <section class="recommend-teaser simple">
            <div class="container">
                <h2><?= fitgym_esc($recoTeaser['title']) ?></h2>
                <p><?= fitgym_esc($recoTeaser['body']) ?></p>
                <a href="<?= fitgym_esc($recoBtnUrl) ?>" class="btn"><?= fitgym_esc($recoBtnLabel) ?></a>
            </div>
        </section>

        <section class="class-controls" style="margin-top:15px;">
            <div class="container">
                <form class="search-form" method="GET" action="">
                    <select id="activitiesDropdownList" name="filter" class="filter-dropdown">
                        <option value="all" <?= strcasecmp($filterCategory, 'all') === 0 ? 'selected' : '' ?>>All Activities</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= fitgym_esc($category) ?>" <?= strcasecmp($filterCategory, $category) === 0 ? 'selected' : '' ?>><?= fitgym_esc($categoryMap[$category] ?? $category) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <input type="text" id="searchInput" class="searchInput" name="q"
                           value="<?= fitgym_esc($searchQuery) ?>"
                           placeholder="Search class by name, category or keyword...">

                    <button type="submit" class="btn">Search</button>
                    <button type="button" class="btn secondary-btn" onclick="location.href='<?= fitgym_esc(fitgym_url('/php/classes.php')) ?>'">Reset</button>
                </form>
            </div>
        </section>

        <section class="classes-section">
            <div class="class-grid">
                <?php if (empty($visibleClasses)): ?>
                    <div class="no-results">No classes match your search.</div>
                <?php else: ?>
                    <?php foreach ($visibleClasses as $c): ?>
                        <a href="<?= fitgym_esc(fitgym_url('/php/class_inside.php')) ?>?class=<?= rawurlencode((string)$c['slug']) ?>" class="class-card">
                            <img src="<?= fitgym_esc((string)$c['image']) ?>" alt="<?= fitgym_esc((string)$c['title']) ?>">
                            <div class="card-content">
                                <h3><?= fitgym_esc((string)$c['title']) ?></h3>
                                <p class="category"><?= fitgym_esc((string)($c['category_label'] ?? $c['category'])) ?></p>
                                <p class="desc"><?= fitgym_esc((string)$c['description']) ?></p>
                                <p class="desc"><?= fitgym_esc((string)($c['total_clients'] ?? 0)) ?> users booked this class</p>
                                <span class="view-btn">View Details</span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <?php include "footer.php"; ?>
</body>
</html>
