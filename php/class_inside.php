<?php
session_start();
require_once __DIR__ . '/dynamic_content.php';

$classSlug = trim((string)($_GET['class'] ?? ''));
$classData = fitgym_get_class_by_slug($classSlug);

if ($classData === null) {
    $all = fitgym_get_classes();
    $classData = !empty($all) ? $all[0] : null;
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
</head>

<body>
<?php include "header.php"; ?>

<section class="class-detail">
    <div class="detail-container">
        <?php if ($classData !== null): ?>
            <img src="<?= fitgym_esc((string)$classData['image']) ?>" class="detail-img" alt="<?= fitgym_esc((string)$classData['title']) ?>">

            <div class="detail-info">
                <h2><?= fitgym_esc((string)$classData['title']) ?></h2>
                <p><?= fitgym_esc((string)$classData['description']) ?></p>

                <ul>
                    <li><strong>Trainer:</strong> <?= fitgym_esc((string)$classData['trainer']) ?></li>
                    <li><strong>Time:</strong> <?= fitgym_esc((string)$classData['time']) ?></li>
                    <li><strong>Location:</strong> <?= fitgym_esc((string)$classData['location']) ?></li>
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

<?php include "footer.php"; ?>

</body>
</html>
