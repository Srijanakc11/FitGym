<?php
session_start();
define('BASE_URL', '/fitgym');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>About Us | FitGym</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="description" content="Learn more about FitGym — our mission, values, trainers, and commitment to your fitness journey.">

    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/pictures/favicon.png">

    <!-- Page CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/about.css">
        <link rel="stylesheet" href="/fitgym/css/header.css">
    <link rel="stylesheet" href="/fitgym/css/footer.css">

    <link rel="stylesheet" href="../css/index.css">
</head>

<body>

<!-- HEADER -->
<?php include "header.php"; ?>

<main class="about-page">

    <!-- HERO SECTION -->
    <section class="about-hero">
        <div class="hero-content">
            <h1>About FitGym</h1>
            <p>Your trusted partner in strength, health, and transformation.</p>
        </div>
    </section>

    <!-- ABOUT CONTENT -->
    <section class="about-content">
        <div class="container">

            <div class="about-text">
                <h2>Who We Are</h2>
                <p>
                    FitGym is a modern fitness center dedicated to helping individuals
                    achieve their health and fitness goals through structured training,
                    professional coaching, and a motivating environment.
                </p>

                <p>
                    Whether you’re a beginner or an advanced athlete, our programs are
                    designed to deliver real results — safely and sustainably.
                </p>
            </div>

            <div class="about-cards">
                <div class="card">
                    <h3>🏋️ Expert Trainers</h3>
                    <p>Certified professionals focused on personalized training.</p>
                </div>

                <div class="card">
                    <h3>💪 Modern Equipment</h3>
                    <p>State-of-the-art machines for strength & cardio training.</p>
                </div>

                <div class="card">
                    <h3>🔥 Diverse Programs</h3>
                    <p>Zumba, Yoga, HIIT, Boxing, Swimming & more.</p>
                </div>

                <div class="card">
                    <h3>🤝 Community Focused</h3>
                    <p>A supportive space where motivation meets discipline.</p>
                </div>
            </div>

        </div>
    </section>

    <!-- MISSION -->
    <section class="mission-section">
        <div class="container">
            <h2>Our Mission</h2>
            <p>
                To inspire healthier lifestyles by providing world-class fitness programs,
                expert guidance, and a welcoming community that empowers transformation.
            </p>
        </div>
    </section>

</main>

<!-- FOOTER -->
<?php include "footer.php"; ?>

</body>
</html>
