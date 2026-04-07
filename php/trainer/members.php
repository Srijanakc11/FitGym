<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../dynamic_content.php';

$currentPage = basename(__FILE__);
$trainer = null;
$memberRows = [];

if (isset($conn) && $conn instanceof mysqli) {
    fitgym_sync_booking_expiry_statuses();

    $stmt = $conn->prepare("SELECT id, name FROM accounts WHERE id = ? AND role = 'trainer' LIMIT 1");
    if ($stmt) {
        $trainerId = (int)($_SESSION['auth_id'] ?? $_SESSION['trainer_id'] ?? 0);
        $stmt->bind_param('i', $trainerId);
        $stmt->execute();
        $trainer = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    if ($trainer) {
        $trainerName = (string)$trainer['name'];
        $memberStmt = $conn->prepare(
            "SELECT b.full_name, b.email, b.contact_number, b.class_name, b.preferred_date, b.time_slot, b.participants,
                    COALESCE(b.trainer_type, 'regular') AS trainer_type, COALESCE(b.status, 'Pending') AS status
             FROM bookings b
             WHERE b.trainer_name = ?
             ORDER BY b.preferred_date DESC, b.created_at DESC"
        );
        if ($memberStmt) {
            $memberStmt->bind_param('s', $trainerName);
            $memberStmt->execute();
            $result = $memberStmt->get_result();
            $memberStmt->close();
            if ($result) {
                $memberRows = $result->fetch_all(MYSQLI_ASSOC);
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
    <title>Member List | Trainer Panel</title>
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
                <h1>Member List</h1>
                <p>Students booked under your classes.</p>
            </div>
        </header>
        <main class="admin-content">
            <div class="card">
                <div class="card-head">
                    <h3>My Members</h3>
                    <p class="admin-note">Basic student information, booking type, and schedule.</p>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Class</th>
                            <th>Schedule</th>
                            <th>Booking Type</th>
                            <th>Participants</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($memberRows)): ?>
                        <?php foreach ($memberRows as $row): ?>
                            <tr>
                                <td>
                                    <strong><?= trainer_esc($row['full_name']) ?></strong>
                                    <div class="meta-list">
                                        <span><?= trainer_esc($row['email']) ?></span>
                                        <span><?= trainer_esc($row['contact_number'] ?: 'No contact number') ?></span>
                                    </div>
                                </td>
                                <td><?= trainer_esc($row['class_name']) ?></td>
                                <td>
                                    <div class="meta-list">
                                        <span><?= trainer_esc($row['preferred_date']) ?></span>
                                        <span><?= trainer_esc($row['time_slot']) ?></span>
                                    </div>
                                </td>
                                <td><span class="badge <?= ($row['trainer_type'] ?? 'regular') === 'private' ? 'warning' : 'success' ?>"><?= trainer_esc(ucfirst((string)($row['trainer_type'] ?? 'regular'))) ?></span></td>
                                <td><?= trainer_esc($row['participants']) ?></td>
                                <td><span class="badge <?= trainer_esc(fitgym_booking_status_badge_class((string)($row['status'] ?? 'Pending'))) ?>"><?= trainer_esc((string)($row['status'] ?? 'Pending')) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6">No members found for your assigned classes yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</div>
</body>
</html>
