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
    <title>Payment सफल | FitGym</title>
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
    <section class="payment-shell">
        <div class="payment-card success">
            <div class="success-icon">✓</div>
            <h1>Payment Successful</h1>
            <p>Your booking is confirmed. We’re excited to see you!</p>

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
                    <span>Paid</span>
                    <strong><?= htmlspecialchars($booking['price']) ?></strong>
                </div>
            </div>

            <div class="next-steps">
                <h3>Next Steps</h3>
                <ul>
                    <li>Arrive 10 minutes early for check-in.</li>
                    <li>Bring a water bottle and a small towel.</li>
                    <li>Need to reschedule? Contact us anytime.</li>
                </ul>
            </div>

            <div class="actions">
                <a class="primary-btn" href="/fitgym/php/classes.php">Browse Classes</a>
            </div>
        </div>
    </section>
</main>

<?php include "footer.php"; ?>
</body>
</html>
