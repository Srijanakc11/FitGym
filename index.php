<?php
session_start();
require_once __DIR__ . '/php/dynamic_content.php';

$siteName = fitgym_setting('site_name', 'FitGym');

$hero = fitgym_block('home_hero', 'Welcome to ' . $siteName, 'Your journey toward a healthier lifestyle starts here.');
$heroCtaLabel = fitgym_setting('home_hero_cta_label', 'Explore Classes');
$heroCtaUrl = fitgym_setting('home_hero_cta_url', fitgym_url('/php/classes.php'));

$featuredClassesPayload = fitgym_get_home_featured_classes(3);
$recommendedClasses = (array)($featuredClassesPayload['classes'] ?? []);
$featuredMode = (string)($featuredClassesPayload['mode'] ?? 'fallback');
$featuredIntro = $featuredMode === 'popular'
    ? 'Start with the top 3 classes our members pick most often and find the one that inspires your fitness journey.'
    : 'Explore 3 strong classes with complete training profiles while new booking trends are still building.';

$bmi = fitgym_block('home_bmi_intro', 'Discover Your Ideal Fitness Plan', 'Calculate your BMI and receive personalized class recommendations tailored to your fitness goals.');
$bmiAssessment = fitgym_setting('home_bmi_assessment_title', 'Your Assessment');

$testimonialsTitle = fitgym_setting('home_testimonials_title', 'What Our Members Say');
$testimonials = fitgym_get_testimonials();
?>
<link rel="stylesheet" href="<?= fitgym_esc(fitgym_asset_url('/css/index.css')) ?>">
<link rel="stylesheet" href="<?= fitgym_esc(fitgym_asset_url('/css/header.css')) ?>">
<link rel="stylesheet" href="<?= fitgym_esc(fitgym_asset_url('/css/footer.css')) ?>">

<section>
  <?php include "php/header.php"; ?>
</section>

<section class="hero">
  <div class="container">
    <h1><?= fitgym_esc($hero['title']) ?></h1>
    <p><?= fitgym_esc($hero['body']) ?></p>
    <a href="<?= fitgym_esc($heroCtaUrl) ?>" class="btn hero-btn"><?= fitgym_esc($heroCtaLabel) ?></a>
  </div>
</section>

<section class="featured-classes">
  <div class="container">
    <h2><?= fitgym_esc(fitgym_setting('home_featured_title', 'Most Recommended Classes')) ?></h2>
    <p class="section-intro"><?= fitgym_esc($featuredIntro) ?></p>

    <div class="class-list">
      <?php foreach ($recommendedClasses as $index => $item): ?>
        <?php
        $title = (string)($item['title'] ?? '');
        $slug = (string)($item['slug'] ?? '');
        $url = (string)($item['url'] ?? ($slug !== '' ? fitgym_url('/php/class_inside.php') . '?class=' . rawurlencode($slug) : fitgym_url('/php/classes.php')));
        $image = (string)($item['image'] ?? fitgym_url('/pictures/workout.jpg'));
        $description = (string)($item['description'] ?? '');
        $rank = $index + 1;
        $totalBookings = (int)($item['total_bookings'] ?? 0);
        if ($title === '') {
            continue;
        }
        ?>
        <div class="class-item">
          <span class="pick-badge"><?= fitgym_esc($featuredMode === 'popular' ? 'Top ' . $rank . ' Pick' : 'Featured Class') ?></span>
          <a href="<?= fitgym_esc($url) ?>">
            <img src="<?= fitgym_esc($image) ?>" alt="<?= fitgym_esc($title) ?> Class" />
          </a>
          <h3><?= fitgym_esc($title) ?></h3>
          <p><?= fitgym_esc($description) ?></p>
          <?php if ($featuredMode === 'popular' && $totalBookings > 0): ?>
            <p class="class-meta"><?= fitgym_esc((string)$totalBookings) ?> booking<?= $totalBookings === 1 ? '' : 's' ?> from members</p>
          <?php endif; ?>
          <a href="<?= fitgym_esc($url) ?>" class="class-link">View Class</a>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="bmi-section">
  <div class="container">
    <h2><?= fitgym_esc($bmi['title']) ?></h2>
    <p class="subtitle"><?= fitgym_esc($bmi['body']) ?></p>

    <div class="bmi-wrapper">
      <div class="bmi-form">
        <div class="form-group">
          <label for="weight">Weight (kg)</label>
          <input type="number" id="weight" placeholder="Enter your weight" min="0" step="0.1" required>
        </div>

        <div class="form-group">
          <label for="height">Height (cm)</label>
          <input type="number" id="height" placeholder="Enter your height" min="0" step="0.1" required>
        </div>

        <div class="form-group">
          <label for="age">Age (years)</label>
          <input type="number" id="age" placeholder="Enter your age" min="0" required>
        </div>

        <button class="btn" onclick="calculateBMI()">Calculate BMI</button>
      </div>

      <div class="bmi-result">
        <h3><?= fitgym_esc($bmiAssessment) ?></h3>
        <div class="result-value">
          <p id="bmiValue"></p>
          <p id="bmiStatus"></p>
        </div>
      </div>
    </div>
  </div>
</section>

<script>
function calculateBMI() {
  const weight = parseFloat(document.getElementById("weight").value);
  const heightCm = parseFloat(document.getElementById("height").value);
  const age = parseInt(document.getElementById("age").value);

  if (!weight || !heightCm || !age) {
    alert("Please enter all required fields");
    return;
  }

  const heightM = heightCm / 100;
  const bmi = (weight / (heightM * heightM)).toFixed(1);

  let status = "";

  if (bmi < 18.5) {
    status = "Underweight";
  } else if (bmi < 25) {
    status = "Normal Weight";
  } else if (bmi < 30) {
    status = "Overweight";
  } else {
    status = "Obese";
  }

  document.getElementById("bmiValue").textContent = `BMI: ${bmi}`;
  document.getElementById("bmiStatus").textContent = status;
}
</script>

<section class="testimonials">
  <div class="container">
    <h2 class="section-title"><?= fitgym_esc($testimonialsTitle) ?></h2>

    <div class="slider-wrapper">
      <div class="slider" id="testimonialSlider">
        <?php foreach ($testimonials as $t): ?>
          <div class="slide">
            <div class="stars"><?= fitgym_esc((string)($t['stars'] ?? '★★★★★')) ?></div>
            <p class="message">"<?= fitgym_esc((string)($t['message'] ?? '')) ?>"</p>
            <p class="author">- <?= fitgym_esc((string)($t['author'] ?? 'Member')) ?></p>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="dots" id="sliderDots"></div>
    </div>
  </div>
</section>

<section>
  <?php include "php/footer.php"; ?>
</section>

<script>
  const slider = document.getElementById("testimonialSlider");
  const dotsContainer = document.getElementById("sliderDots");
  const slides = slider ? slider.children : [];
  let index = 0;

  for (let i = 0; i < slides.length; i++) {
    const dot = document.createElement("span");
    dot.classList.add("dot");
    if (i === 0) dot.classList.add("active");

    dot.addEventListener("click", () => {
      index = i;
      updateSlider();
    });

    dotsContainer.appendChild(dot);
  }

  function updateSlider() {
    if (!slider || slides.length === 0) return;
    slider.style.transform = `translateX(-${index * 100}%)`;

    const dots = document.querySelectorAll(".dot");
    dots.forEach((dot) => dot.classList.remove("active"));
    if (dots[index]) dots[index].classList.add("active");
  }

  if (slides.length > 1) {
    setInterval(() => {
      index = (index + 1) % slides.length;
      updateSlider();
    }, 4000);
  }
</script>
