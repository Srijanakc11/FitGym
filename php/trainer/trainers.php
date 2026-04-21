<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../dynamic_content.php';

$currentPage = basename(__FILE__);
$trainerRows = [];

if (isset($conn) && $conn instanceof mysqli) {
    // Fetch all active trainers
    $query = "SELECT id, name, email, login_code, specialization, experience_years, image_path, availability, qualification, qualification_status, active, created_at FROM accounts WHERE role = 'trainer' AND active = 1 ORDER BY created_at DESC";
    $result = $conn->query($query);
    if ($result) {
        $trainerRows = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Trainer Directory | Trainer Panel</title>
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
            <a class="<?= $currentPage === 'trainers.php' ? 'active' : '' ?>" href="<?= trainer_esc(fitgym_url('/php/trainer/trainers.php')) ?>">Trainer Directory</a>
            <a href="<?= trainer_esc(fitgym_url('/php/trainer/logout.php')) ?>">Logout</a>
        </nav>
    </aside>
    <div class="admin-main">
        <header class="admin-topbar">
            <div>
                <h1>Trainer Directory</h1>
                <p>View details of all trainers available at the gym.</p>
            </div>
            <?php include __DIR__ . '/partials/topbar_notif.php'; ?>
        </header>
        <main class="admin-content">
            <div class="card">
                <div class="card-head">
                    <h3>All Trainers</h3>
                    <p class="admin-note">List of active trainers, their specializations, and availability.</p>
                </div>
                
                <?php if (empty($trainerRows)): ?>
                    <div class="empty-state">No other trainers found.</div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Trainer</th>
                                    <th>Profile</th>
                                    <th>Qualification</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($trainerRows as $row): ?>
                                    <tr>
                                        <td>
                                            <a href="<?= trainer_esc(fitgym_url('/php/trainer/trainer_bookings.php?id=' . $row['id'])) ?>" style="text-decoration: none; color: var(--orange);">
                                                <strong><?= trainer_esc($row['name']) ?> &rarr;</strong>
                                            </a>
                                            <div class="meta-list">
                                                <span><?= trainer_esc($row['login_code']) ?></span>
                                                <span><?= trainer_esc($row['specialization']) ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="meta-list">
                                                <span><?= trainer_esc($row['experience_years']) ?> yrs experience</span>
                                                <span><?= trainer_esc($row['availability'] ?: 'Availability not set') ?></span>
                                            </div>
                                        </td>
                                        <td><?= nl2br(trainer_esc($row['qualification'])) ?></td>
                                        <td>
                                            <div class="meta-list">
                                                <span class="badge <?= $row['qualification_status'] === 'verified' ? 'success' : ($row['qualification_status'] === 'rejected' ? 'danger' : 'warning') ?>">
                                                    <?= trainer_esc(ucfirst($row['qualification_status'])) ?>
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>
</body>
</html>
