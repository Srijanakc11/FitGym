<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../dynamic_content.php';

$currentPage = basename(__FILE__);
$trainer = null;
$assignedClasses = [];
$recentClients = [];
$summary = [
    'total_classes' => 0,
    'total_bookings' => 0,
    'total_clients' => 0,
    'upcoming_clients' => 0,
];

if (isset($conn) && $conn instanceof mysqli) {
    fitgym_sync_booking_expiry_statuses();
    $activeBookingWhere = fitgym_booking_active_sql('b');

    $stmt = $conn->prepare("SELECT id, login_code, name, specialization, experience_years, qualification, qualification_status, availability FROM accounts WHERE id = ? AND role = 'trainer' LIMIT 1");
    if ($stmt) {
        $trainerId = (int)($_SESSION['auth_id'] ?? $_SESSION['trainer_id'] ?? 0);
        $stmt->bind_param('i', $trainerId);
        $stmt->execute();
        $trainer = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    if ($trainer) {
        $trainerName = (string)$trainer['name'];

        $classStmt = $conn->prepare(
            "SELECT
                c.id,
                c.name,
                c.slug,
                c.weekly_schedule,
                c.schedule_config,
                c.fitness_level,
                c.max_participants,
                c.active,
                COUNT(b.id) AS total_bookings,
                COALESCE(SUM(b.participants), 0) AS total_clients,
                COALESCE(SUM(CASE WHEN b.preferred_date >= CURDATE() AND {$activeBookingWhere} THEN b.participants ELSE 0 END), 0) AS upcoming_clients
             FROM classes_admin c
             LEFT JOIN bookings b ON b.class_slug = c.slug AND b.trainer_name = ? AND {$activeBookingWhere}
             WHERE c.trainer_account_id = ?
             GROUP BY c.id
             ORDER BY c.created_at DESC"
        );
        if ($classStmt) {
            $trainerId = (int)($_SESSION['auth_id'] ?? $_SESSION['trainer_id'] ?? 0);
            $classStmt->bind_param('si', $trainerName, $trainerId);
            $classStmt->execute();
            $assignedClassResult = $classStmt->get_result();
            $classStmt->close();

            if ($assignedClassResult) {
                while ($classRow = $assignedClassResult->fetch_assoc()) {
                    $scheduleRows = fitgym_schedule_slots_from_json((string)($classRow['schedule_config'] ?? ''), (string)($classRow['weekly_schedule'] ?? ''));
                    $classRow['schedule_summary'] = fitgym_schedule_summary($scheduleRows);
                    $assignedClasses[] = $classRow;
                    $summary['total_classes']++;
                    $summary['total_bookings'] += (int)$classRow['total_bookings'];
                    $summary['total_clients'] += (int)$classRow['total_clients'];
                    $summary['upcoming_clients'] += (int)$classRow['upcoming_clients'];
                }
                $assignedClassResult->free();
            }
        }

        $recentStmt = $conn->prepare(
            "SELECT b.full_name, b.email, b.contact_number, b.class_name, b.preferred_date, b.time_slot, b.participants,
                    COALESCE(b.trainer_type, 'regular') AS trainer_type, COALESCE(b.status, 'Pending') AS status
             FROM bookings b
             WHERE b.trainer_name = ?
             ORDER BY b.preferred_date DESC, b.created_at DESC
             LIMIT 20"
        );
        if ($recentStmt) {
            $recentStmt->bind_param('s', $trainerName);
            $recentStmt->execute();
            $recentClientResult = $recentStmt->get_result();
            $recentStmt->close();
            if ($recentClientResult) {
                $recentClients = $recentClientResult->fetch_all(MYSQLI_ASSOC);
                $recentClientResult->free();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Trainer Dashboard | FitGym</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="<?= trainer_esc(fitgym_asset_url('/pictures/favicon.png')) ?>">
    <link rel="stylesheet" href="<?= trainer_esc(fitgym_asset_url('/css/admin.css')) ?>">
</head>
<body>
<div class="admin-shell">
    <aside class="admin-sidebar">
        <div class="brand">
            <img src="<?= trainer_esc(fitgym_asset_url('/pictures/favicon.png')) ?>" alt="FitGym">
            <span>Trainer Panel</span>
        </div>
        <nav class="admin-nav">
            <a class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>" href="<?= trainer_esc(fitgym_url('/php/trainer/dashboard.php')) ?>">Dashboard</a>
            <a class="<?= $currentPage === 'classes.php' ? 'active' : '' ?>" href="<?= trainer_esc(fitgym_url('/php/trainer/classes.php')) ?>">Assigned Classes</a>
            <a class="<?= $currentPage === 'members.php' ? 'active' : '' ?>" href="<?= trainer_esc(fitgym_url('/php/trainer/members.php')) ?>">Member List</a>
            <a href="<?= trainer_esc(fitgym_url('/php/trainer/logout.php')) ?>">Logout</a>
        </nav>
    </aside>
    <div class="admin-main">
        <header class="admin-topbar">
            <div>
                <h1>Trainer Dashboard</h1>
                <p>Welcome <?= trainer_esc($_SESSION['trainer_name'] ?? 'Trainer') ?></p>
            </div>
        </header>
        <main class="admin-content">
            <div class="grid-4">
                <div class="card"><h3>Assigned Classes</h3><strong><?= trainer_esc($summary['total_classes']) ?></strong></div>
                <div class="card"><h3>Total Bookings</h3><strong><?= trainer_esc($summary['total_bookings']) ?></strong></div>
                <div class="card"><h3>Total Clients</h3><strong><?= trainer_esc($summary['total_clients']) ?></strong></div>
                <div class="card"><h3>Upcoming Clients</h3><strong><?= trainer_esc($summary['upcoming_clients']) ?></strong></div>
            </div>

            <div class="grid-2">
                <a class="card card-link" href="<?= trainer_esc(fitgym_url('/php/trainer/classes.php')) ?>">
                    <h3>View Assigned Classes</h3>
                    <p>Check your classes, schedules, booking totals, and slot capacity.</p>
                </a>
                <a class="card card-link" href="<?= trainer_esc(fitgym_url('/php/trainer/members.php')) ?>">
                    <h3>View Member List</h3>
                    <p>See students booked under you with schedule, contact, and booking type.</p>
                </a>
            </div>

            <?php if ($trainer): ?>
                <div class="card">
                    <h3>Profile</h3>
                    <p><strong>Trainer ID:</strong> <?= trainer_esc($trainer['login_code']) ?></p>
                    <p><strong>Name:</strong> <?= trainer_esc($trainer['name']) ?></p>
                    <p><strong>Specialization:</strong> <?= trainer_esc($trainer['specialization']) ?></p>
                    <p><strong>Experience:</strong> <?= trainer_esc($trainer['experience_years']) ?> years</p>
                    <p><strong>Availability:</strong> <?= trainer_esc($trainer['availability']) ?></p>
                    <p><strong>Qualification Status:</strong> <?= trainer_esc(ucfirst($trainer['qualification_status'])) ?></p>
                    <p><strong>Qualification:</strong><br><?= nl2br(trainer_esc($trainer['qualification'])) ?></p>
                </div>
            <?php endif; ?>

            <div class="card">
                <h3>Assigned Classes</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Class</th>
                            <th>Level</th>
                            <th>Schedule</th>
                            <th>Capacity</th>
                            <th>Bookings</th>
                            <th>Clients</th>
                            <th>Upcoming Clients</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($assignedClasses)): ?>
                        <?php foreach ($assignedClasses as $row): ?>
                            <tr>
                                <td>
                                    <strong><?= trainer_esc($row['name']) ?></strong>
                                    <div class="meta-list">
                                        <span>Slug: <?= trainer_esc($row['slug']) ?></span>
                                    </div>
                                </td>
                                <td><?= trainer_esc(ucfirst((string)($row['fitness_level'] ?: 'unspecified'))) ?></td>
                                <td><?= trainer_esc($row['schedule_summary'] ?: $row['weekly_schedule']) ?></td>
                                <td><?= trainer_esc($row['max_participants']) ?> / slot</td>
                                <td><?= trainer_esc($row['total_bookings']) ?></td>
                                <td><?= trainer_esc($row['total_clients']) ?></td>
                                <td><?= trainer_esc($row['upcoming_clients']) ?></td>
                                <td><span class="badge <?= (int)$row['active'] === 1 ? 'success' : 'danger' ?>"><?= (int)$row['active'] === 1 ? 'Active' : 'Inactive' ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8">No classes assigned yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="card">
                <h3>Student Bookings</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Class</th>
                            <th>Schedule</th>
                            <th>Booking Type</th>
                            <th>Participants</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($recentClients)): ?>
                        <?php foreach ($recentClients as $client): ?>
                            <tr>
                                <td>
                                    <strong><?= trainer_esc($client['full_name']) ?></strong>
                                    <div class="meta-list">
                                        <span><?= trainer_esc($client['email']) ?></span>
                                        <span><?= trainer_esc($client['contact_number'] ?: 'No contact number') ?></span>
                                    </div>
                                </td>
                                <td><?= trainer_esc($client['class_name']) ?></td>
                                <td>
                                    <div class="meta-list">
                                        <span><?= trainer_esc($client['preferred_date']) ?></span>
                                        <span><?= trainer_esc($client['time_slot']) ?></span>
                                    </div>
                                </td>
                                <td><span class="badge <?= ($client['trainer_type'] ?? 'regular') === 'private' ? 'warning' : 'success' ?>"><?= trainer_esc(ucfirst((string)($client['trainer_type'] ?? 'regular'))) ?></span></td>
                                <td><?= trainer_esc($client['participants']) ?></td>
                                <td><span class="badge <?= trainer_esc(fitgym_booking_status_badge_class((string)($client['status'] ?? 'Pending'))) ?>"><?= trainer_esc((string)($client['status'] ?? 'Pending')) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6">No student bookings found for your classes.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</div>
</body>
</html>
