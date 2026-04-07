<?php
require_once __DIR__ . '/partials/header.php';

fitgym_sync_booking_expiry_statuses();
$activeBookingWhere = fitgym_booking_active_sql();

$popularClasses = $conn ? $conn->query("SELECT class_name, COUNT(*) AS total FROM bookings WHERE {$activeBookingWhere} GROUP BY class_name ORDER BY total DESC LIMIT 5") : false;
$trainerPerformance = $conn ? $conn->query("SELECT trainer_name, COUNT(*) AS total FROM bookings WHERE {$activeBookingWhere} GROUP BY trainer_name ORDER BY total DESC LIMIT 5") : false;
?>

<h2>Reports & Analytics</h2>
<div class="grid-4">
    <div class="card"><h3>User Growth</h3><strong><?= $conn ? esc($conn->query("SELECT COUNT(*) AS c FROM accounts WHERE role = 'client' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetch_assoc()['c']) : 0 ?></strong><div class="muted">Last 30 days</div></div>
    <div class="card"><h3>Bookings (7d)</h3><strong><?= $conn ? esc($conn->query("SELECT COUNT(*) AS c FROM bookings WHERE preferred_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND {$activeBookingWhere}")->fetch_assoc()['c']) : 0 ?></strong></div>
    <div class="card"><h3>Bookings (30d)</h3><strong><?= $conn ? esc($conn->query("SELECT COUNT(*) AS c FROM bookings WHERE preferred_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND {$activeBookingWhere}")->fetch_assoc()['c']) : 0 ?></strong></div>
    <div class="card"><h3>Revenue (Est.)</h3><strong>NPR <?= $conn ? esc($conn->query("SELECT COUNT(*) AS c FROM bookings")->fetch_assoc()['c'] * 2000) : 0 ?></strong></div>
</div>

<div class="card">
    <h3>Popular Classes</h3>
    <table class="table">
        <thead><tr><th>Class</th><th>Bookings</th></tr></thead>
        <tbody>
            <?php if ($popularClasses): while ($row = $popularClasses->fetch_assoc()): ?>
                <tr><td><?= esc($row['class_name']) ?></td><td><?= esc($row['total']) ?></td></tr>
            <?php endwhile; endif; ?>
        </tbody>
    </table>
</div>

<div class="card">
    <h3>Trainer Performance</h3>
    <table class="table">
        <thead><tr><th>Trainer</th><th>Bookings</th></tr></thead>
        <tbody>
            <?php if ($trainerPerformance): while ($row = $trainerPerformance->fetch_assoc()): ?>
                <tr><td><?= esc($row['trainer_name']) ?></td><td><?= esc($row['total']) ?></td></tr>
            <?php endwhile; endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
