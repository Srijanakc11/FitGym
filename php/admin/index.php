<?php
require_once __DIR__ . '/partials/header.php';

$totalUsers = $conn ? (int)$conn->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc()['c'] : 0;
$totalTrainers = $conn ? (int)$conn->query("SELECT COUNT(*) AS c FROM trainers")->fetch_assoc()['c'] : 0;
$totalClasses = $conn ? (int)$conn->query("SELECT COUNT(*) AS c FROM classes_admin")->fetch_assoc()['c'] : 0;
$totalBookings = $conn ? (int)$conn->query("SELECT COUNT(*) AS c FROM bookings")->fetch_assoc()['c'] : 0;
$activePrograms = $conn ? (int)$conn->query("SELECT COUNT(*) AS c FROM classes_admin WHERE active = 1")->fetch_assoc()['c'] : 0;
$todayBookings = $conn ? (int)$conn->query("SELECT COUNT(*) AS c FROM bookings WHERE preferred_date = CURDATE()")->fetch_assoc()['c'] : 0;
$weeklyBookings = $conn ? (int)$conn->query("SELECT COUNT(*) AS c FROM bookings WHERE preferred_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetch_assoc()['c'] : 0;
$monthlyBookings = $conn ? (int)$conn->query("SELECT COUNT(*) AS c FROM bookings WHERE preferred_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetch_assoc()['c'] : 0;
?>

<div class="grid-4">
    <a class="card card-link" href="/fitgym/php/admin/users.php"><h3>Total Users</h3><strong><?= esc($totalUsers) ?></strong></a>
    <a class="card card-link" href="/fitgym/php/admin/trainers.php"><h3>Total Trainers</h3><strong><?= esc($totalTrainers) ?></strong></a>
    <a class="card card-link" href="/fitgym/php/admin/classes.php"><h3>Total Classes</h3><strong><?= esc($totalClasses) ?></strong></a>
    <a class="card card-link" href="/fitgym/php/admin/bookings.php"><h3>Total Bookings</h3><strong><?= esc($totalBookings) ?></strong></a>
</div>

<div class="grid-4">
    <a class="card card-link" href="/fitgym/php/admin/classes.php"><h3>Active Programs</h3><strong><?= esc($activePrograms) ?></strong></a>
    <a class="card card-link" href="/fitgym/php/admin/bookings.php"><h3>Today's Bookings</h3><strong><?= esc($todayBookings) ?></strong></a>
    <a class="card card-link" href="/fitgym/php/admin/reports.php"><h3>Weekly Bookings</h3><strong><?= esc($weeklyBookings) ?></strong></a>
    <a class="card card-link" href="/fitgym/php/admin/reports.php"><h3>Monthly Bookings</h3><strong><?= esc($monthlyBookings) ?></strong></a>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
