<?php
require_once __DIR__ . '/partials/header.php';
require_once __DIR__ . '/../payment_helpers.php';

fitgym_bootstrap_booking_payment_columns();
fitgym_sync_booking_expiry_statuses();

if ($conn && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'status') {
        $status = trim((string)($_POST['status'] ?? 'Pending'));
        $stmt = $conn->prepare(
            "UPDATE bookings
             SET status = ?,
                 payment_status = CASE
                     WHEN ? = 'Cancelled' AND COALESCE(payment_provider, 'cash') = 'khalti' AND COALESCE(payment_status, '') = 'paid' THEN payment_status
                     WHEN ? = 'Cancelled' THEN 'cancelled'
                     ELSE payment_status
                 END
             WHERE id = ?"
        );
        $stmt->bind_param('sssi', $status, $status, $status, $_POST['id']);
        $stmt->execute();
        $stmt->close();
    }
}

$where = "1=1";
$params = [];
$types = '';

if (!empty($_GET['date'])) {
    $where .= " AND preferred_date = ?";
    $params[] = $_GET['date'];
    $types .= 's';
}
if (!empty($_GET['class'])) {
    $where .= " AND class_name = ?";
    $params[] = $_GET['class'];
    $types .= 's';
}
if (!empty($_GET['trainer'])) {
    $where .= " AND trainer_name = ?";
    $params[] = $_GET['trainer'];
    $types .= 's';
}

$bookingRows = [];
if ($conn) {
    $sql = "SELECT * FROM bookings WHERE {$where} ORDER BY created_at DESC";
    if ($params) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $bookingRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $bookingRows = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
    }
}

$confirmedCount = 0;
$pendingCount = 0;
$expiredCount = 0;
foreach ($bookingRows as $booking) {
    $status = (string)($booking['status'] ?? 'Pending');
    if ($status === 'Confirmed') {
        $confirmedCount++;
    } elseif ($status === 'Pending') {
        $pendingCount++;
    } elseif ($status === 'Expired') {
        $expiredCount++;
    }
}
?>

<div class="page-header-row">
    <h2>Booking Management</h2>
    <div class="page-actions">
        <button type="button" class="btn-primary panel-toggle" data-panel="bookingFilterPanel" aria-expanded="false">Open Filters</button>
    </div>
</div>

<div class="stat-strip">
    <div class="stat-chip">
        <span>Total Results</span>
        <strong><?= esc(count($bookingRows)) ?></strong>
    </div>
    <div class="stat-chip">
        <span>Confirmed</span>
        <strong><?= esc($confirmedCount) ?></strong>
    </div>
    <div class="stat-chip">
        <span>Pending</span>
        <strong><?= esc($pendingCount) ?></strong>
    </div>
    <div class="stat-chip">
        <span>Expired</span>
        <strong><?= esc($expiredCount) ?></strong>
    </div>
</div>

<div id="bookingFilterPanel" class="card collapsible-panel">
    <div class="card-head">
        <h3>Filter Bookings</h3>
        <p class="admin-note">Open filters when you want to narrow down the table.</p>
    </div>
    <form method="GET" class="toolbar-form">
        <label>Date <input type="date" name="date" value="<?= esc($_GET['date'] ?? '') ?>"></label>
        <label>Class <input name="class" value="<?= esc($_GET['class'] ?? '') ?>"></label>
        <label>Trainer <input name="trainer" value="<?= esc($_GET['trainer'] ?? '') ?>"></label>
        <button class="btn-primary" type="submit">Apply</button>
        <a class="btn-secondary" href="<?= esc(fitgym_url('/php/admin/bookings.php')) ?>">Reset</a>
    </form>
</div>

<div class="card">
    <div class="card-head">
        <h3>Booking Table</h3>
        <p class="admin-note">Statuses can be updated inline without cluttering the page.</p>
    </div>

    <?php if (empty($bookingRows)): ?>
        <div class="empty-state">No bookings found for the current filter.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Class</th>
                        <th>Trainer Type</th>
                        <th>Schedule</th>
                        <th>Booking Details</th>
                        <th>Status</th>
                        <th>Update</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookingRows as $row): ?>
                        <?php $status = (string)($row['status'] ?? 'Pending'); ?>
                        <?php $statusBadgeClass = fitgym_booking_status_badge_class($status); ?>
                        <?php $paymentSnapshot = fitgym_booking_payment_snapshot($row); ?>
                        <tr>
                            <td>
                                <strong><?= esc($row['full_name']) ?></strong>
                                <div class="meta-list">
                                    <span><?= esc($row['email']) ?></span>
                                    <span><?= esc($row['contact_number'] ?? 'No contact') ?></span>
                                </div>
                            </td>
                            <td>
                                <strong><?= esc($row['class_name']) ?></strong>
                                <div class="meta-list">
                                    <span><?= esc($row['trainer_name']) ?></span>
                                    <span>Slug: <?= esc($row['class_slug']) ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="badge <?= (($row['trainer_type'] ?? 'regular') === 'private') ? 'warning' : 'success' ?>">
                                    <?= esc(ucfirst((string)($row['trainer_type'] ?? 'regular'))) ?>
                                </span>
                            </td>
                            <td>
                                <div class="meta-list">
                                    <span><?= esc($row['preferred_date']) ?></span>
                                    <span><?= esc($row['time_slot']) ?></span>
                                </div>
                            </td>
                            <td>
                                <div class="meta-list">
                                    <span><?= esc($row['participants']) ?> participant(s)</span>
                                    <span><?= esc($paymentSnapshot['method_label']) ?></span>
                                    <span><?= esc($paymentSnapshot['status_label']) ?></span>
                                    <?php if ($paymentSnapshot['transaction_id'] !== ''): ?>
                                        <span>Txn: <?= esc($paymentSnapshot['transaction_id']) ?></span>
                                    <?php elseif ($paymentSnapshot['order_id'] !== ''): ?>
                                        <span>Order: <?= esc($paymentSnapshot['order_id']) ?></span>
                                    <?php endif; ?>
                                    <span><?= esc($row['created_at']) ?></span>
                                </div>
                            </td>
                            <td><span class="badge <?= esc($statusBadgeClass) ?>"><?= esc($status) ?></span></td>
                            <td class="actions-cell">
                                <form method="POST" class="inline-actions">
                                    <input type="hidden" name="action" value="status">
                                    <input type="hidden" name="id" value="<?= esc($row['id']) ?>">
                                    <select name="status" class="status-select">
                                        <option <?= $status === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                        <option <?= $status === 'Confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                        <option <?= $status === 'Expired' ? 'selected' : '' ?>>Expired</option>
                                        <option <?= $status === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                    </select>
                                    <button class="btn-secondary" type="submit">Save</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.panel-toggle').forEach(function (button) {
        button.addEventListener('click', function () {
            const panel = document.getElementById(button.dataset.panel || '');
            if (!panel) return;
            const open = panel.classList.toggle('is-open');
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    });
});
</script>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
