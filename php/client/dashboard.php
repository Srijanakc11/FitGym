<?php
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/auth.php';

$userEmail = $_SESSION['user_email'];
$userName = $_SESSION['user_name'] ?? 'Member';

// Fetch user info if available
$user = null;
if ($conn) {
    $stmt = $conn->prepare("SELECT id, name, email, phone, gender FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $userEmail);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Handle booking cancellation
if ($conn && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    $bookingId = (int)($_POST['booking_id'] ?? 0);
    $stmt = $conn->prepare("UPDATE bookings SET status = 'Cancelled' WHERE id = ? AND email = ?");
    $stmt->bind_param('is', $bookingId, $userEmail);
    $stmt->execute();
    $stmt->close();
}

$bookings = [];
if ($conn) {
    $stmt = $conn->prepare("SELECT * FROM bookings WHERE email = ? ORDER BY preferred_date DESC, created_at DESC");
    $stmt->bind_param('s', $userEmail);
    $stmt->execute();
    $bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Dashboard | FitGym</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="/fitgym/pictures/favicon.png">
    <link rel="stylesheet" href="/fitgym/css/header.css">
    <link rel="stylesheet" href="/fitgym/css/footer.css">
    <link rel="stylesheet" href="/fitgym/css/index.css">
    <link rel="stylesheet" href="/fitgym/css/client.css">
</head>
<body>
<?php include "../header.php"; ?>

<main class="client-page">
    <section class="client-hero">
        <div>
            <p class="eyebrow">Welcome back</p>
            <h1><?= htmlspecialchars($userName) ?></h1>
            <p>Manage your bookings and explore new classes.</p>
        </div>
        <div class="hero-actions">
            <a class="btn" href="/fitgym/php/classes.php">Browse Classes</a>
            <a class="btn secondary" href="/fitgym/php/recommend.php">Get Recommendations</a>
        </div>
    </section>

    <section class="client-grid">
        <div class="client-card">
            <h3>My Profile</h3>
            <div class="profile-row"><span>Name</span><strong><?= htmlspecialchars($user['name'] ?? $userName) ?></strong></div>
            <div class="profile-row"><span>Email</span><strong><?= htmlspecialchars($user['email'] ?? $userEmail) ?></strong></div>
            <div class="profile-row"><span>Phone</span><strong><?= htmlspecialchars($user['phone'] ?? '-') ?></strong></div>
            <div class="profile-row"><span>Gender</span><strong><?= htmlspecialchars($user['gender'] ?? '-') ?></strong></div>
            <a class="link" href="/fitgym/php/logout.php">Logout</a>
        </div>

        <div class="client-card">
            <h3>My Bookings</h3>
            <?php if (empty($bookings)): ?>
                <p class="muted">No bookings yet. Book a class to see it here.</p>
            <?php else: ?>
                <div class="booking-list">
                    <?php foreach ($bookings as $b): ?>
                        <div class="booking-item">
                            <div>
                                <strong><?= htmlspecialchars($b['class_name']) ?></strong>
                                <span><?= htmlspecialchars($b['preferred_date']) ?> · <?= htmlspecialchars($b['time_slot']) ?></span>
                                <span class="status <?= strtolower($b['status'] ?? 'pending') ?>"><?= htmlspecialchars($b['status'] ?? 'Pending') ?></span>
                            </div>
                            <?php if (($b['status'] ?? 'Pending') !== 'Cancelled'): ?>
                                <form method="POST">
                                    <input type="hidden" name="action" value="cancel">
                                    <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                                    <button class="btn small" type="submit">Cancel</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php include "../footer.php"; ?>
</body>
</html>
