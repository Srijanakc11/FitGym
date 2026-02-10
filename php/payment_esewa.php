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
                    <svg viewBox="0 0 120 120" aria-hidden="true">
                        <rect x="0" y="0" width="120" height="120" rx="24" fill="#00A651"/>
                        <text x="60" y="72" text-anchor="middle" font-family="Poppins, sans-serif" font-size="44" fill="#fff" font-weight="700">eS</text>
                        <text x="86" y="72" text-anchor="middle" font-family="Poppins, sans-serif" font-size="44" fill="#fff" font-weight="700">e</text>
                    </svg>
                </div>
                <div>
                    <h2>eSewa Payment</h2>
                    <p>Trusted Nepali digital wallet</p>
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
                    <div class="qr-box" aria-hidden="true">
                        <svg viewBox="0 0 120 120">
                            <rect width="120" height="120" fill="#fff"/>
                            <rect x="6" y="6" width="36" height="36" fill="#000"/>
                            <rect x="12" y="12" width="24" height="24" fill="#fff"/>
                            <rect x="18" y="18" width="12" height="12" fill="#000"/>
                            <rect x="78" y="6" width="36" height="36" fill="#000"/>
                            <rect x="84" y="12" width="24" height="24" fill="#fff"/>
                            <rect x="90" y="18" width="12" height="12" fill="#000"/>
                            <rect x="6" y="78" width="36" height="36" fill="#000"/>
                            <rect x="12" y="84" width="24" height="24" fill="#fff"/>
                            <rect x="18" y="90" width="12" height="12" fill="#000"/>
                            <rect x="52" y="52" width="12" height="12" fill="#000"/>
                            <rect x="64" y="64" width="10" height="10" fill="#000"/>
                            <rect x="50" y="70" width="10" height="10" fill="#000"/>
                            <rect x="70" y="50" width="10" height="10" fill="#000"/>
                        </svg>
                    </div>
                    <p class="qr-note">Open eSewa → Scan & Pay</p>
                </div>

                <div class="test-credentials">
                    <h3>Demo eSewa Credentials</h3>
                    <div class="credential-row">
                        <span>eSewa ID</span>
                        <strong>demo_user</strong>
                    </div>
                    <div class="credential-row">
                        <span>Password</span>
                        <strong>demo_pass</strong>
                    </div>
                    <div class="credential-row">
                        <span>OTP</span>
                        <strong>123456</strong>
                    </div>
                    <p class="note">Demo only. No real money is processed.</p>
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
