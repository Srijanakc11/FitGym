<link rel="stylesheet" href="css/index.css">
<link rel="stylesheet" href="css/header.css">
<link rel="stylesheet" href="css/footer.css">


<section>
  <?php
  // Include Header
  include "php/header.php";
  ?>
</section>

<!-- HERO SECTION -->
<section class="hero">
  <div class="container">
    <h1>Welcome to FitGym</h1>
    <p>Your journey toward a healthier lifestyle starts here.</p>
    <a href="/fitgym/php/classes.php" class="btn hero-btn">Explore Classes</a>
  </div>
</section>

<!-- FEATURED CLASSES -->
<section class="featured-classes">
  <div class="container">
    <h2>Featured Classes</h2>

    <div class="class-list">
      <div class="class-item">
        <a href="/fitgym/php/classes.php">
          <img src="pictures/yoga.jpg" alt="Yoga Class" />
        </a>
        <h3>Yoga</h3>
        <p>Relax and strengthen your body with our guided yoga sessions.</p>
      </div>

      <div class="class-item">
        <a href="/php/classes.php">
          <img src="pictures/zumba.jpg" alt="Zumba Class" />
        </a>
        <h3>Zumba</h3>
        <p>Fun and energetic dance-based cardio workout for all fitness levels.</p>
      </div>

      <div class="class-item">
        <a href="/php/classes.php">
          <img src="pictures/workout.jpg" alt="Weight Training Class" />
        </a>
        <h3>Weight Training</h3>
        <p>Build strength and endurance using free weights and machines.</p>
      </div>
    </div>
  </div>
</section>

<!-- BMI CALCULATOR + PERSONALIZED RECOMMENDATIONS -->
<section class="bmi-section">
  <div class="container">
    <h2>Discover Your Ideal Fitness Plan</h2>
    <p class="subtitle">
      Calculate your BMI and receive personalized class recommendations tailored to your fitness goals.
    </p>

    <div class="bmi-wrapper">
      <!-- INPUT FORM -->
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

      <!-- RESULTS DISPLAY -->
      <div class="bmi-result">
        <h3>Your Assessment</h3>
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
  let classes = [];

  if (bmi < 18.5) {
    status = "Underweight";
    classes = ["Yoga", "Strength Training", "Beginner Workout"];
  } else if (bmi < 25) {
    status = "Normal Weight";
    classes = ["HIIT", "Zumba", "Boxing"];
  } else if (bmi < 30) {
    status = "Overweight";
    classes = ["Cardio", "Swimming", "Aerobics"];
  } else {
    status = "Obese";
    classes = ["Low Impact Cardio", "Yoga", "Swimming"];
  }

  document.getElementById("bmiValue").textContent = `BMI: ${bmi}`;
  document.getElementById("bmiStatus").textContent = status;

  // Recommendations removed from home page
}
</script>


<!-- TESTIMONIALS SLIDER -->
<section class="testimonials">
  <div class="container">
    <h2 class="section-title">What Our Members Say</h2>

    <div class="slider-wrapper">
      <div class="slider" id="testimonialSlider">
        <div class="slide">
          <div class="stars">★★★★★</div>
          <p class="message">
            “I’ve been a member of several gyms, but none compare to the
            motivating and professional environment here.”
          </p>
          <p class="author">– Rajesh Hamal, Member for 3+ Years</p>
        </div>

        <div class="slide">
          <div class="stars">★★★★★</div>
          <p class="message">
            “Easily the best mix of equipment and atmosphere in the area.”
          </p>
          <p class="author">– Kabita Nepali, Returning Member</p>
        </div>

        <div class="slide">
          <div class="stars">★★★★★</div>
          <p class="message">
            “Amazing trainers and modern equipment. Best fitness decision ever.”
          </p>
          <p class="author">– Aashika Tamang, Premium Member</p>
        </div>

        <div class="slide">
          <div class="stars">★★★★</div>
          <p class="message">
            “Group classes are energetic and well-structured.”
          </p>
          <p class="author">– Priya Bishwokarma, Group Class Member</p>
        </div>
      </div>

      <div class="dots" id="sliderDots"></div>
    </div>
  </div>
</section>

<section>
  <?php
  // Include Footer
  include "php/footer.php";
  ?>
</section>

<script>
  const slider = document.getElementById("testimonialSlider");
  const dotsContainer = document.getElementById("sliderDots");
  const slides = slider.children;
  let index = 0;

  for (let i = 0; i < slides.length; i++) {
    const dot = document.createElement("span");
    dot.classList.add("dot");
    if (i === 0) dot.classList.add("active");
    dot.dataset.index = i;

    dot.addEventListener("click", () => {
      index = i;
      updateSlider();
    });

    dotsContainer.appendChild(dot);
  }

  function updateSlider() {
    slider.style.transform = `translateX(-${index * 100}%)`;

    const dots = document.querySelectorAll(".dot");
    dots.forEach((dot) => dot.classList.remove("active"));
    dots[index].classList.add("active");
  }

  setInterval(() => {
    index = (index + 1) % slides.length;
    updateSlider();
  }, 4000);
</script>
