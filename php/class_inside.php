<?php
session_start();
require_once __DIR__ . '/dynamic_content.php';

$classSlug = trim((string)($_GET['class'] ?? ''));
$classData = fitgym_get_class_by_slug($classSlug);

if ($classData === null) {
    $all = fitgym_get_classes();
    $classData = !empty($all) ? $all[0] : null;
}

$recommendedClasses = [];
$isClientComplete = false;

$userId = (int)($_SESSION['auth_id'] ?? $_SESSION['user_id'] ?? 0);
$currentRole = (string)($_SESSION['auth_role'] ?? '');

if ($currentRole === 'client' && $userId > 0) {
    if (!function_exists('fitgym_get_user_fitness_profile')) {
        require_once __DIR__ . '/auth_common.php';
    }
    $fitnessProfile = fitgym_get_user_fitness_profile($userId);
    if ($fitnessProfile !== null && (int)($fitnessProfile['profile_completed'] ?? 0) === 1) {
        $isClientComplete = true;
        require_once __DIR__ . '/class_recommendation_helpers.php';
        
        $profInput = [
            'age' => (string)($fitnessProfile['age'] ?? ''),
            'gender' => (string)($fitnessProfile['gender'] ?? 'female'),
            'height_cm' => (string)($fitnessProfile['height_cm'] ?? ''),
            'weight_kg' => (string)($fitnessProfile['weight_kg'] ?? ''),
            'activity' => (string)($fitnessProfile['activity'] ?? 'moderate'),
            'goal' => (string)($fitnessProfile['goal'] ?? 'maintenance'),
            'training_days_per_week' => (string)($fitnessProfile['training_days_per_week'] ?? '3'),
            'fitness_level' => (string)($fitnessProfile['fitness_level'] ?? 'beginner'),
            'joint_pain' => (string)($fitnessProfile['joint_pain'] ?? 'no'),
            'duration_preference' => (string)($fitnessProfile['duration_preference'] ?? ''),
        ];
        
        $allClasses = array_map('fitgym_normalize_class_row', fitgym_get_classes());
        $recommendableClasses = array_values(array_filter(
            $allClasses,
             fn(array $r): bool => !empty($r['recommendation_ready']) && $r['slug'] !== $classSlug
        ));
        
        $profCalc = fitgym_calculate_tdee_context($profInput);
        if (empty($profCalc['errors'])) {
            $recSet = fitgym_get_recommended_classes($recommendableClasses, $profCalc['context']);
            $recRows = $recSet['has_exact_match'] ? $recSet['exact_matches'] : $recSet['fallback_alternatives'];
            $recommendedClasses = array_slice($recRows, 0, 3);
        }
    }
}

if (empty($recommendedClasses)) {
    $featuredSet = fitgym_get_home_featured_classes(4); 
    $items = $featuredSet['classes'];
    $filtered = array_values(array_filter($items, fn($r) => ($r['slug'] ?? '') !== $classSlug));
    $recommendedClasses = array_slice($filtered, 0, 3);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= fitgym_esc($classData !== null ? (string)$classData['title'] : 'Class Not Found') ?> | <?= fitgym_esc(fitgym_setting('site_name', 'FitGym')) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet" href="<?= fitgym_esc(fitgym_asset_url('/css/header.css')) ?>">
    <link rel="stylesheet" href="<?= fitgym_esc(fitgym_asset_url('/css/footer.css')) ?>">
    <link rel="stylesheet" href="<?= fitgym_esc(fitgym_asset_url('/css/class_inside.css')) ?>">
    <link rel="stylesheet" href="<?= fitgym_esc(fitgym_asset_url('/css/client.css')) ?>">
</head>

<body>
<?php include "header.php"; ?>

<section class="class-detail">
    <div class="detail-container">
        <?php if ($classData !== null): ?>
            <img src="<?= fitgym_esc((string)$classData['image']) ?>" class="detail-img" alt="<?= fitgym_esc((string)$classData['title']) ?>">

            <div class="detail-info">
                <h2><?= fitgym_esc((string)$classData['title']) ?></h2>
                <div class="detail-price-card">
                    <span class="detail-price-label">Class Price</span>
                    <strong><?= fitgym_esc((string)$classData['price_formatted']) ?></strong>
                </div>
                <p><?= fitgym_esc((string)$classData['description']) ?></p>

                <div class="detail-highlights">
                    <div class="detail-highlight">
                        <span>Trainer</span>
                        <strong><?= fitgym_esc((string)$classData['trainer']) ?></strong>
                    </div>
                    <div class="detail-highlight">
                        <span>Schedule</span>
                        <strong><?= fitgym_esc((string)$classData['time']) ?></strong>
                    </div>
                    <div class="detail-highlight">
                        <span>Location</span>
                        <strong><?= fitgym_esc((string)$classData['location']) ?></strong>
                    </div>
                </div>

                <ul>
                    <li><strong>Price:</strong> <?= fitgym_esc((string)$classData['price_formatted']) ?></li>
                    <li><strong>Total booked users:</strong> <?= fitgym_esc((string)($classData['total_clients'] ?? 0)) ?></li>
                    <li><strong>Total bookings:</strong> <?= fitgym_esc((string)($classData['total_bookings'] ?? 0)) ?></li>
                </ul>

                <a class="book-btn" href="<?= fitgym_esc(fitgym_url('/php/book_class.php')) ?>?class=<?= rawurlencode((string)$classData['slug']) ?>">Book Now</a>
            </div>
        <?php else: ?>
            <div class="detail-info">
                <h2>Class Not Found</h2>
                <p>No active classes are available right now. Add a class from the admin panel to display it here.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if (!empty($recommendedClasses)): ?>
<section class="client-rec-section" style="margin-bottom:60px;">
    <div class="client-rec-header container">
        <div>
            <p class="eyebrow"><?= $isClientComplete ? 'Based on your profile' : 'Popular right now' ?></p>
            <h2><?= $isClientComplete ? 'Recommended Classes' : 'You might also like' ?></h2>
        </div>
        <a href="<?= fitgym_esc(fitgym_url($isClientComplete ? '/php/recommend.php' : '/php/classes.php')) ?>" class="btn secondary"><?= $isClientComplete ? 'Full Planner' : 'View All Classes' ?></a>
    </div>
    <div class="client-rec-grid container">
        <?php foreach ($recommendedClasses as $recIndex => $recRow): ?>
            <?php
            $recRow     = fitgym_normalize_class_row($recRow);
            $recTitle   = (string)($recRow['class_name'] ?? $recRow['name'] ?? 'Class');
            $recSlug    = (string)($recRow['slug'] ?? '');
            $recUrl     = fitgym_url('/php/class_inside.php') . ($recSlug !== '' ? '?class=' . rawurlencode($recSlug) : '');
            $recCat     = (string)($recRow['category_label'] ?? 'General');
            $recLevel   = fitgym_labelize_token((string)($recRow['fitness_level'] ?? 'beginner'));
            $recIntens  = fitgym_labelize_token((string)($recRow['intensity_level'] ?? 'medium'));
            $recImage   = (string)($recRow['image'] ?? fitgym_url('/pictures/workout.jpg'));
            $recDesc    = (string)($recRow['description'] ?? '');
            $showScore  = $isClientComplete && isset($recRow['score']);
            $recScore   = $showScore ? max(0, min(100, (int)round(((int)($recRow['score'] ?? 0) / 122) * 100))) : 0;
            ?>
            <div class="client-rec-card">
                <div class="client-rec-img-wrap">
                    <img src="<?= fitgym_esc($recImage) ?>" alt="<?= fitgym_esc($recTitle) ?>">
                    <span class="client-rec-rank">#<?= $recIndex + 1 ?></span>
                </div>
                <div class="client-rec-body">
                    <div class="client-rec-meta">
                        <span><?= fitgym_esc($recCat) ?></span>
                        <span><?= fitgym_esc($recLevel) ?></span>
                        <span><?= fitgym_esc($recIntens) ?> intensity</span>
                    </div>
                    <h3><?= fitgym_esc($recTitle) ?></h3>
                    <?php if ($recDesc !== ''): ?>
                        <p><?= fitgym_esc(mb_strimwidth($recDesc, 0, 100, '…')) ?></p>
                    <?php endif; ?>
                    <p class="rec-price-tag"><strong>Price:</strong> <?= fitgym_esc((string)$recRow['price_formatted']) ?></p>
                    <div class="client-rec-foot">
                        <span class="client-rec-score"><?= $showScore ? ($recScore . '% match') : '' ?></span>
                        <a class="btn small" href="<?= fitgym_esc($recUrl) ?>">View Class</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php include "footer.php"; ?>

</body>
</html>
