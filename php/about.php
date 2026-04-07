<?php
session_start();
require_once __DIR__ . '/dynamic_content.php';

$siteName = fitgym_setting('site_name', 'FitGym');

$hero = fitgym_block('about_hero', 'About ' . $siteName, 'Your trusted partner in strength, health, and transformation.');
$whoWeAre = fitgym_block(
    'about_who_we_are',
    'Who We Are',
    'FitGym is a modern fitness center dedicated to helping individuals achieve their health and fitness goals through structured training, professional coaching, and a motivating environment.'
);
$whoWeAreExtra = fitgym_block(
    'about_who_we_are_extra',
    '',
    'Whether you are a beginner or an advanced athlete, our programs are designed to deliver real results safely and sustainably.'
);
$mission = fitgym_block(
    'about_mission',
    'Our Mission',
    'To inspire healthier lifestyles by providing world-class fitness programs, expert guidance, and a welcoming community that empowers transformation.'
);

$aboutCards = fitgym_json_block('about_cards', [
    ['title' => 'Expert Trainers', 'body' => 'Certified professionals focused on personalized training.'],
    ['title' => 'Modern Equipment', 'body' => 'State-of-the-art machines for strength and cardio training.'],
    ['title' => 'Diverse Programs', 'body' => 'Zumba, Yoga, HIIT, Boxing, Swimming and more.'],
    ['title' => 'Community Focused', 'body' => 'A supportive space where motivation meets discipline.'],
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>About Us | <?= fitgym_esc($siteName) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="description" content="Learn more about <?= fitgym_esc($siteName) ?>, our mission, values, trainers, and commitment to your fitness journey.">

    <link rel="icon" type="image/png" href="<?= fitgym_esc(fitgym_asset_url('/pictures/favicon.png')) ?>">
    <link rel="stylesheet" href="<?= fitgym_esc(fitgym_asset_url('/css/about.css')) ?>">
    <link rel="stylesheet" href="<?= fitgym_esc(fitgym_asset_url('/css/header.css')) ?>">
    <link rel="stylesheet" href="<?= fitgym_esc(fitgym_asset_url('/css/footer.css')) ?>">
    <link rel="stylesheet" href="<?= fitgym_esc(fitgym_asset_url('/css/index.css')) ?>">
</head>

<body>
<?php include "header.php"; ?>

<main class="about-page">
    <section class="about-hero">
        <div class="hero-content">
            <h1><?= fitgym_esc($hero['title']) ?></h1>
            <p><?= fitgym_esc($hero['body']) ?></p>
        </div>
    </section>

    <section class="about-content">
        <div class="container">
            <div class="about-text">
                <h2><?= fitgym_esc($whoWeAre['title']) ?></h2>
                <p><?= fitgym_esc($whoWeAre['body']) ?></p>
                <p><?= fitgym_esc($whoWeAreExtra['body']) ?></p>
            </div>

            <div class="about-cards">
                <?php foreach ($aboutCards as $card): ?>
                    <?php
                    $title = trim((string)($card['title'] ?? ''));
                    $body = trim((string)($card['body'] ?? ''));
                    if ($title === '' && $body === '') {
                        continue;
                    }
                    ?>
                    <div class="card">
                        <h3><?= fitgym_esc($title) ?></h3>
                        <p><?= fitgym_esc($body) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="mission-section">
        <div class="container">
            <h2><?= fitgym_esc($mission['title']) ?></h2>
            <p><?= fitgym_esc($mission['body']) ?></p>
        </div>
    </section>
</main>

<?php include "footer.php"; ?>
</body>
</html>
