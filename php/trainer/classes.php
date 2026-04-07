<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../dynamic_content.php';

$currentPage = basename(__FILE__);
$trainer = null;
$assignedClasses = [];

if (isset($conn) && $conn instanceof mysqli) {
    $activeBookingWhere = fitgym_booking_active_sql('b');

    $stmt = $conn->prepare("SELECT id, login_code, name FROM accounts WHERE id = ? AND role = 'trainer' LIMIT 1");
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
            $result = $classStmt->get_result();
            $classStmt->close();

            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $scheduleRows = fitgym_schedule_slots_from_json((string)($row['schedule_config'] ?? ''), (string)($row['weekly_schedule'] ?? ''));
                    $row['schedule_summary'] = fitgym_schedule_summary($scheduleRows);
                    $assignedClasses[] = $row;
                }
                $result->free();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Assigned Classes | Trainer Panel</title>
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
                <h1>Assigned Classes</h1>
                <p>Classes currently assigned to you.</p>
            </div>
        </header>
        <main class="admin-content">
            <div class="card">
                <div class="card-head">
                    <h3>My Classes</h3>
                    <p class="admin-note">Review class schedule, capacity, and booking numbers.</p>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Class</th>
                            <th>Level</th>
                            <th>Schedule</th>
                            <th>Capacity</th>
                            <th>Bookings</th>
                            <th>Clients</th>
                            <th>Upcoming</th>
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
                                        <span><?= trainer_esc($row['slug']) ?></span>
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
        </main>
    </div>
</div>
</body>
</html>
