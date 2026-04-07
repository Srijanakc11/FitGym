<?php
session_start();
require_once __DIR__ . '/dynamic_content.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($name === '' || strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($message) < 3) {
        $error = 'Please complete the form correctly. Name must be at least 2 characters, use a valid email, and write a message with at least 3 characters.';
    } elseif (isset($conn) && $conn instanceof mysqli) {
        $conn->query(
            "CREATE TABLE IF NOT EXISTS contacts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(100) NOT NULL,
                subject VARCHAR(255),
                message TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )"
        );
        $stmt = $conn->prepare('INSERT INTO contacts (name, email, subject, message) VALUES (?, ?, ?, ?)');
        if ($stmt) {
            $stmt->bind_param('ssss', $name, $email, $subject, $message);
            if ($stmt->execute()) {
                $success = fitgym_setting('contact_success_message', 'Message sent successfully!');
                $_POST = [];
            } else {
                $error = 'Unable to send message at the moment.';
            }
            $stmt->close();
        } else {
            $error = 'Contact form is not fully configured yet.';
        }
    } else {
        $error = 'Database connection unavailable.';
    }
}

$siteName = fitgym_setting('site_name', 'FitGym');
$contactHero = fitgym_block('contact_hero', 'Contact ' . $siteName, "Your journey begins with a single message. We're here to help!");
$contactBox = fitgym_block('contact_box', 'Get In Touch', 'Our team will respond within 24 hours.');

$contactAddress = fitgym_setting('contact_address', 'Lalitpur, Nepal');
$contactPhone = fitgym_setting('contact_phone', '+977-9845673217');
$contactEmail = fitgym_setting('contact_email', 'info@fitgymcenter.com');
$contactHours = fitgym_setting('contact_hours', '5 AM-10 PM');
$contactImage = fitgym_setting('contact_image_path', fitgym_url('/pictures/contact-vector.png'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= fitgym_esc($siteName) ?> - Contact Us</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet" href="<?= fitgym_esc(fitgym_asset_url('/css/header.css')) ?>">
    <link rel="stylesheet" href="<?= fitgym_esc(fitgym_asset_url('/css/footer.css')) ?>">
    <link rel="stylesheet" href="<?= fitgym_esc(fitgym_asset_url('/css/index.css')) ?>">
    <link rel="stylesheet" href="<?= fitgym_esc(fitgym_asset_url('/css/contact.css')) ?>">
</head>

<body>
<?php include "header.php"; ?>

<div class="contact-hero">
    <h1><?= fitgym_esc($contactHero['title']) ?></h1>
    <p><?= fitgym_esc($contactHero['body']) ?></p>
</div>

<div class="contact-wrapper">
    <div class="contact-info-box">
        <h2><?= fitgym_esc($contactBox['title']) ?></h2>
        <p><?= fitgym_esc($contactBox['body']) ?></p>

        <ul>
            <li><strong>Location:</strong> <?= fitgym_esc($contactAddress) ?></li>
            <li><strong>Phone:</strong> <?= fitgym_esc($contactPhone) ?></li>
            <li><strong>Email:</strong> <?= fitgym_esc($contactEmail) ?></li>
            <li><strong>Hours:</strong> <?= fitgym_esc($contactHours) ?></li>
        </ul>

        <img src="<?= fitgym_esc($contactImage) ?>" alt="Contact Illustration" class="info-img">
    </div>

    <form action="<?= fitgym_esc(fitgym_url('/php/contact.php')) ?>" method="POST" id="contactForm" class="contact-form">
        <div class="form-field">
            <input type="text" name="name" id="name" value="<?= fitgym_esc($_POST['name'] ?? '') ?>" required>
            <label for="name">Full Name</label>
        </div>

        <div class="form-field">
            <input type="email" name="email" id="email" value="<?= fitgym_esc($_POST['email'] ?? '') ?>" required>
            <label for="email">Email Address</label>
        </div>

        <div class="form-field">
            <input type="text" name="subject" id="subject" value="<?= fitgym_esc($_POST['subject'] ?? '') ?>">
            <label for="subject">Subject</label>
        </div>

        <div class="form-field">
            <textarea name="message" id="message" required><?= fitgym_esc($_POST['message'] ?? '') ?></textarea>
            <label for="message">Your Message</label>
        </div>

        <button type="submit" id="submit-c">Send Message</button>

        <?php if ($success !== ''): ?>
            <div class="success"><?= fitgym_esc($success) ?></div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="success" style="background:#ffe9e9;color:#7a1212;"><?= fitgym_esc($error) ?></div>
        <?php endif; ?>
    </form>
</div>

<?php include "footer.php"; ?>

<script>
(function(){
  const fields = document.querySelectorAll('.form-field');

  fields.forEach(field => {
    const input = field.querySelector('input, textarea');
    if (!input) return;

    if (input.value && input.value.trim() !== '') field.classList.add('filled');

    input.addEventListener('focus', () => field.classList.add('focused'));
    input.addEventListener('blur', () => {
      field.classList.remove('focused');
      if (input.value && input.value.trim() !== '') field.classList.add('filled');
      else field.classList.remove('filled');
    });

    input.addEventListener('input', () => {
      if (input.value && input.value.trim() !== '') field.classList.add('filled');
      else field.classList.remove('filled');
    });
  });

  const form = document.getElementById('contactForm');
  if (form) {
    form.addEventListener('submit', function(e){
      const name = form.querySelector('input[name="name"]');
      const email = form.querySelector('input[name="email"]');
      const message = form.querySelector('textarea[name="message"]');

      let valid = true;
      if (!name.value || name.value.trim().length < 2) valid = false;
      if (!email.value || !email.value.includes('@') || !email.value.includes('.')) valid = false;
      if (!message.value || message.value.trim().length < 3) valid = false;

      if (!valid) {
        e.preventDefault();
        alert('Please complete the form correctly. Name (min 2), valid email, and message (min 3) required.');
        return false;
      }

      return true;
    });
  }
})();
</script>

</body>
</html>
