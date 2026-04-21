<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../dynamic_content.php';

$currentPage = basename(__FILE__);
$trainer = null;
$memberRows = [];
$successMessage = '';
$errorMessage = '';

if (isset($conn) && $conn instanceof mysqli) {
    fitgym_sync_booking_expiry_statuses();
    if (function_exists('fitgym_table_has_column') && !fitgym_table_has_column('bookings', 'trainer_status_reason')) {
        $conn->query("ALTER TABLE bookings ADD COLUMN trainer_status_reason TEXT NULL AFTER status");
    }

    $sessionTrainerId = (int)($_SESSION['auth_id'] ?? $_SESSION['trainer_id'] ?? 0);
    $targetTrainerId = $sessionTrainerId;

    $stmt = $conn->prepare("SELECT id, name FROM accounts WHERE id = ? AND role = 'trainer' LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $targetTrainerId);
        $stmt->execute();
        $trainer = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    if ($trainer) {
        $trainerName = (string)$trainer['name'];
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'booking_status') {
            $bookingId = (int)($_POST['booking_id'] ?? 0);
            $nextStatus = trim((string)($_POST['status'] ?? 'Pending'));
            $statusReason = trim((string)($_POST['status_reason'] ?? ''));
            $allowedStatuses = ['Confirmed', 'Cancelled'];

            if ($bookingId <= 0 || !in_array($nextStatus, $allowedStatuses, true)) {
                $errorMessage = 'Invalid booking action.';
            } elseif ($nextStatus === 'Cancelled' && $statusReason === '') {
                $errorMessage = 'Please add a cancellation reason so the client knows what happened.';
            } else {
                $bookingStmt = $conn->prepare(
                    "SELECT id, email, full_name, class_name, preferred_date, time_slot, trainer_name,
                            COALESCE(status, 'Pending') AS status,
                            COALESCE(trainer_status_reason, '') AS trainer_status_reason
                     FROM bookings
                     WHERE id = ? AND trainer_name = ?
                     LIMIT 1"
                );
                if ($bookingStmt) {
                    $bookingStmt->bind_param('is', $bookingId, $trainerName);
                    $bookingStmt->execute();
                    $bookingRow = $bookingStmt->get_result()->fetch_assoc();
                    $bookingStmt->close();

                    if (!$bookingRow) {
                        $errorMessage = 'Booking not found for this trainer.';
                    } elseif (!fitgym_booking_status_is_cancellable((string)($bookingRow['status'] ?? 'Pending')) && $nextStatus === 'Cancelled') {
                        $errorMessage = 'This booking can no longer be cancelled.';
                    } elseif (strtolower((string)($bookingRow['status'] ?? '')) === 'expired') {
                        $errorMessage = 'Expired bookings cannot be updated.';
                    } elseif (strtolower((string)($bookingRow['status'] ?? '')) === 'cancelled' && $nextStatus === 'Confirmed') {
                        $errorMessage = 'Cancelled bookings cannot be reconfirmed from this view.';
                    } else {
                        $updateStmt = $conn->prepare(
                            "UPDATE bookings
                             SET status = ?,
                                 trainer_status_reason = ?,
                                 payment_status = CASE
                                     WHEN ? = 'Cancelled' AND COALESCE(payment_provider, 'cash') = 'khalti' AND COALESCE(payment_status, '') = 'paid' THEN payment_status
                                     WHEN ? = 'Cancelled' THEN 'cancelled'
                                     ELSE payment_status
                                 END
                             WHERE id = ? AND trainer_name = ?"
                        );
                        if ($updateStmt) {
                            $reasonForSave = $nextStatus === 'Cancelled' ? $statusReason : '';
                            $updateStmt->bind_param('ssssis', $nextStatus, $reasonForSave, $nextStatus, $nextStatus, $bookingId, $trainerName);
                            $updateStmt->execute();
                            $updated = $updateStmt->affected_rows > 0;
                            $updateStmt->close();

                            if ($updated || strcasecmp((string)($bookingRow['status'] ?? ''), $nextStatus) === 0) {
                                $bookingRow['status'] = $nextStatus;
                                $bookingRow['trainer_status_reason'] = $nextStatus === 'Cancelled' ? $statusReason : '';
                                fitgym_create_booking_status_notification_for_client($bookingRow, $nextStatus, $trainerName, $nextStatus === 'Cancelled' ? $statusReason : '');
                                $successMessage = $nextStatus === 'Confirmed'
                                    ? 'Booking confirmed and the client has been notified.'
                                    : 'Booking cancelled and the client has been notified.';
                            } else {
                                $errorMessage = 'No booking change was made.';
                            }
                        } else {
                            $errorMessage = 'Unable to update the booking right now.';
                        }
                    }
                } else {
                    $errorMessage = 'Unable to load the booking right now.';
                }
            }
        }

        $memberStmt = $conn->prepare(
            "SELECT b.id, b.full_name, b.email, b.contact_number, b.class_name, b.preferred_date, b.time_slot, b.participants,
                    COALESCE(b.trainer_status_reason, '') AS trainer_status_reason,
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
    <title>Trainer Booking History | Trainer Panel</title>
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
                <h1><?= trainer_esc($trainer['name'] ?? 'Trainer') ?>'s Booking History</h1>
                <p>All bookings assigned to this trainer.</p>
            </div>
            <?php include __DIR__ . '/partials/topbar_notif.php'; ?>
        </header>
        <main class="admin-content">
            <div class="page-actions" style="margin-bottom: 20px;">
                <a href="<?= trainer_esc(fitgym_url('/php/trainer/trainers.php')) ?>" class="btn-secondary">&larr; Back to Directory</a>
            </div>
            <?php if ($successMessage !== ''): ?>
                <div class="alert"><?= trainer_esc($successMessage) ?></div>
            <?php endif; ?>
            <?php if ($errorMessage !== ''): ?>
                <div class="alert" style="background:#ffe9e9;color:#7a1212;border-color:#f0b7b7;"><?= trainer_esc($errorMessage) ?></div>
            <?php endif; ?>
            <div class="card">
                <div class="card-head">
                    <h3>Booking Details</h3>
                    <p class="admin-note">Complete history of students and schedules. Trainers can confirm or cancel their own bookings here.</p>
                </div>
                <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Class</th>
                            <th>Schedule</th>
                            <th>Booking Type</th>
                            <th>Participants</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($memberRows)): ?>
                        <?php foreach ($memberRows as $row): ?>
                            <?php
                            $status = (string)($row['status'] ?? 'Pending');
                            $statusLower = strtolower($status);
                            $canConfirm = !in_array($statusLower, ['cancelled', 'expired'], true);
                            $canCancel = fitgym_booking_status_is_cancellable($status);
                            ?>
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
                                <td>
                                    <span class="badge <?= trainer_esc(fitgym_booking_status_badge_class($status)) ?>"><?= trainer_esc($status) ?></span>
                                    <?php if ($statusLower === 'cancelled' && trim((string)($row['trainer_status_reason'] ?? '')) !== ''): ?>
                                        <div class="meta-list" style="margin-top:8px;">
                                            <span><strong>Reason:</strong> <?= trainer_esc((string)$row['trainer_status_reason']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="actions-cell">
                                    <div class="inline-actions">
                                        <?php if ($canConfirm): ?>
                                            <form method="POST">
                                                <input type="hidden" name="action" value="booking_status">
                                                <input type="hidden" name="booking_id" value="<?= (int)($row['id'] ?? 0) ?>">
                                                <input type="hidden" name="status" value="Confirmed">
                                                <button class="btn-primary" type="submit">Confirm</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($canCancel): ?>
                                            <form method="POST">
                                                <input type="hidden" name="action" value="booking_status">
                                                <input type="hidden" name="booking_id" value="<?= (int)($row['id'] ?? 0) ?>">
                                                <input type="hidden" name="status" value="Cancelled">
                                                <textarea name="status_reason" rows="2" placeholder="Reason for cancellation" required style="min-width:220px; margin-bottom:8px;"><?= trainer_esc((string)($row['trainer_status_reason'] ?? '')) ?></textarea>
                                                <button class="btn-danger" type="submit">Cancel</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if (!$canConfirm && !$canCancel): ?>
                                            <span class="muted">No action available</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7">No bookings found for this trainer.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </main>
    </div>
</div>
</body>
</html>
