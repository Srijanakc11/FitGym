<?php
session_start();
include("database.php");

// $success = "";

// if ($_SERVER["REQUEST_METHOD"] === "POST") {
//     $name = trim($_POST["name"]);
//     $email = trim($_POST["email"]);
//     $subject = trim($_POST["subject"]);
//     $message = trim($_POST["message"]);

//     $stmt = $con1->prepare("INSERT INTO contacts (name, email, subject, message) VALUES (?, ?, ?, ?)");
//     $stmt->bind_param("ssss", $name, $email, $subject, $message);

//     if ($stmt->execute()) {
//         $success = "Message sent successfully!";
//     } else {
//         $success = "Error: " . $con1->error;
//     }

//     $stmt->close();
//     $con1->close();
// }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>FitGym — Contact Us</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSS -->
    <link rel="stylesheet" href="/fitgym/css/header.css">
    <link rel="stylesheet" href="/fitgym/css/footer.css">

    <link rel="stylesheet" href="../css/index.css">
    <link rel="stylesheet" href="/fitgym/css/contact.css">

</head>

<body>

<?php include "header.php"; ?>

<div class="contact-hero">
    <h1>Contact FitGym</h1>
    <p>Your journey begins with a single message. We're here to help!</p>
</div>

<div class="contact-wrapper">

    <!-- LEFT INFO PANEL -->
    <div class="contact-info-box">
        <h2>📞 Get In Touch</h2>
        <p>Our team will respond within 24 hours.</p>

        <ul>
            <li><strong>📍 Location:</strong> Lalitpur, Nepal</li>
            <li><strong>📞 Phone:</strong> +977-9800000000</li>
            <li><strong>📧 Email:</strong> info@fitgymcenter.com</li>
            <li><strong>🕒 Hours:</strong> 5 AM – 10 PM</li>
        </ul>

        <img src="../pictures/contact-vector.png" alt="Contact Illustration" class="info-img">
    </div>

    <!-- FORM -->
    <form action="contact.php" method="POST" id="contactForm" class="contact-form">

        <div class="form-field">
            <input type="text" name="name" id="name" required>
            <label for="name">Full Name</label>
        </div>

        <div class="form-field">
            <input type="email" name="email" id="email" required>
            <label for="email">Email Address</label>
        </div>

        <div class="form-field">
            <input type="text" name="subject" id="subject">
            <label for="subject">Subject</label>
        </div>

        <div class="form-field">
            <textarea name="message" id="message" required></textarea>
            <label for="message">Your Message</label>
        </div>

        <button type="submit" id="submit-c">Send Message</button>

        <?php if ($success): ?>
            <div class="success"><?= $success ?></div>
        <?php endif; ?>
    </form>
</div>

<?php include "footer.php"; ?>

<!-- INTERNAL JS -->
<script>
// enhance floating labels and form UX
(function(){
  // select fields
  const fields = document.querySelectorAll('.form-field');

  fields.forEach(field => {
    const input = field.querySelector('input, textarea');
    if (!input) return;

    // initialize filled state on load
    if (input.value && input.value.trim() !== '') field.classList.add('filled');

    // focus/blur handlers
    input.addEventListener('focus', () => field.classList.add('focused'));
    input.addEventListener('blur', () => {
      field.classList.remove('focused');
      if (input.value && input.value.trim() !== '') field.classList.add('filled');
      else field.classList.remove('filled');
    });

    // also update on input so label floats when typing
    input.addEventListener('input', () => {
      if (input.value && input.value.trim() !== '') field.classList.add('filled');
      else field.classList.remove('filled');
    });
  });

  // basic internal validation (improved UX over alerts)
  const form = document.getElementById('contactForm') || document.querySelector('form[action="contact.php"]');
  if (form) {
    form.addEventListener('submit', function(e){
      const name = form.querySelector('input[name="name"]');
      const email = form.querySelector('input[name="email"]');
      const message = form.querySelector('textarea[name="message"]');

      let valid = true;
      // simple checks
      if (!name.value || name.value.trim().length < 2) valid = false;
      if (!email.value || !email.value.includes('@') || !email.value.includes('.')) valid = false;
      if (!message.value || message.value.trim().length < 8) valid = false;

      if (!valid) {
        e.preventDefault();
        // show inline error (simple)
        alert('Please complete the form correctly. Name (min 2), valid email, and message (min 8) required.');
        return false;
      }

      return true;
    });
  }
})();
</script>


</body>
</html>
