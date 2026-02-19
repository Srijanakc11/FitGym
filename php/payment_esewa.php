<?php
session_start();

$booking = $_SESSION['last_booking'] ?? null;

if (!$booking) {
    header('Location: /fitgym/php/book_class.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>eSewa Payment | FitGym</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="/fitgym/pictures/favicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/fitgym/css/header.css">
    <link rel="stylesheet" href="/fitgym/css/footer.css">
    <link rel="stylesheet" href="/fitgym/css/payment.css">
</head>
<body>
<?php include "header.php"; ?>

<main class="payment-page">
    <section class="payment-hero">
        <div class="hero-inner">
            <span class="hero-pill">Secure Checkout</span>
            <h1>Complete Payment with eSewa</h1>
            <p>Confirm your booking and pay online in a few quick steps.</p>
        </div>
    </section>

    <section class="payment-shell">
        <div class="payment-card">
            <div class="provider">
                <div class="provider-logo">
                    <!-- Official eSewa Logo -->
                    <img src="https://upload.wikimedia.org/wikipedia/commons/f/ff/Esewa_logo.webp" alt="eSewa Logo">
                </div>
            </div>

            <div class="summary">
                <h3>Booking Summary</h3>
                <div class="summary-row">
                    <span>Class</span>
                    <strong><?= htmlspecialchars($booking['class_name']) ?></strong>
                </div>
                <div class="summary-row">
                    <span>Trainer</span>
                    <strong><?= htmlspecialchars($booking['trainer_name']) ?></strong>
                </div>
                <div class="summary-row">
                    <span>Date</span>
                    <strong><?= htmlspecialchars($booking['preferred_date']) ?></strong>
                </div>
                <div class="summary-row">
                    <span>Time Slot</span>
                    <strong><?= htmlspecialchars($booking['time_slot']) ?></strong>
                </div>
                <div class="summary-row">
                    <span>Participants</span>
                    <strong><?= htmlspecialchars($booking['participants']) ?></strong>
                </div>
                <div class="summary-row total">
                    <span>Total</span>
                    <strong><?= htmlspecialchars($booking['price']) ?></strong>
                </div>
            </div>

<div class="esewa-flow">
                <div class="qr-panel">
                    <h3>Scan QR in eSewa App</h3>
                    <div class="qr-box">
                        <!-- User's QR Code Image -->
                        <img src="/fitgym/pictures/esewa_qr.jpg" alt="eSewa QR Code">
                    </div>
                    <p class="qr-note">Open eSewa → Scan & Pay</p>
                </div>
            </div>

            <div class="actions">
                <form method="POST" action="payment_success.php" class="confirm-form">
                    <label class="confirm-check">
                        <input type="checkbox" required>
                        I have completed the payment in eSewa
                    </label>
                    <button type="submit" class="primary-btn">Confirm Payment</button>
                </form>
                <a class="secondary-btn" href="/fitgym/php/book_class.php?class=<?= htmlspecialchars($booking['class_slug']) ?>">Cancel</a>
            </div>
        </div>
    </section>
</main>

<?php include "footer.php"; ?>
</body>
</html>
