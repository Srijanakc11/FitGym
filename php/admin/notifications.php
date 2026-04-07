<?php
require_once __DIR__ . '/partials/header.php';

$message = '';
$error = '';
$adminAccountId = (int)($_SESSION['auth_id'] ?? $_SESSION['admin_id'] ?? 0);

if ($conn && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'send';

    if ($action === 'delete') {
        $notificationId = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $notificationId);
            $stmt->execute();
            $stmt->close();
            $message = 'Notification deleted.';
        }
    }

    if ($action === 'send') {
        $title = trim((string)($_POST['title'] ?? ''));
        $audienceLabel = trim((string)($_POST['audience'] ?? 'All Users'));
        $messageBody = trim((string)($_POST['message'] ?? ''));
        $type = trim((string)($_POST['notification_type'] ?? 'announcement'));
        $targetRole = trim((string)($_POST['target_role'] ?? ''));
        $targetAccountId = (int)($_POST['target_account_id'] ?? 0);
        $sendAtRaw = trim((string)($_POST['send_at'] ?? ''));
        $status = $sendAtRaw !== '' ? 'scheduled' : 'sent';
        $sendAt = $sendAtRaw !== '' ? date('Y-m-d H:i:s', strtotime($sendAtRaw)) : null;

        if ($title === '' || $messageBody === '') {
            $error = 'Title and message are required.';
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO notifications
                (title, message, audience, notification_type, status, target_role, target_account_id, send_at, sent_by_account_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            if ($stmt) {
                $targetRoleValue = $targetRole !== '' ? $targetRole : null;
                $targetAccountValue = $targetAccountId > 0 ? $targetAccountId : null;
                $sendAtValue = $sendAt !== null ? $sendAt : null;
                $stmt->bind_param(
                    'ssssssisi',
                    $title,
                    $messageBody,
                    $audienceLabel,
                    $type,
                    $status,
                    $targetRoleValue,
                    $targetAccountValue,
                    $sendAtValue,
                    $adminAccountId
                );
                $stmt->execute();
                $stmt->close();
                $message = $status === 'scheduled' ? 'Notification scheduled.' : 'Notification sent.';
            }
        }
    }
}

$notificationRows = [];
if ($conn) {
    $result = $conn->query(
        "SELECT n.*, sender.name AS sender_name, target.name AS target_name
         FROM notifications n
         LEFT JOIN accounts sender ON n.sent_by_account_id = sender.id
         LEFT JOIN accounts target ON n.target_account_id = target.id
         ORDER BY COALESCE(n.send_at, n.created_at) DESC, n.id DESC"
    );
    if ($result) {
        $notificationRows = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();
    }
}

$accounts = [];
if ($conn) {
    $result = $conn->query("SELECT id, name, role FROM accounts WHERE active = 1 ORDER BY role, name");
    if ($result) {
        $accounts = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();
    }
}

$totalNotifications = count($notificationRows);
$scheduledCount = 0;
$sentCount = 0;
foreach ($notificationRows as $note) {
    if (($note['status'] ?? '') === 'scheduled') {
        $scheduledCount++;
    }
    if (($note['status'] ?? '') === 'sent') {
        $sentCount++;
    }
}
?>

<div class="page-header-row">
    <h2>Notifications & Announcements</h2>
    <div class="page-actions">
        <button type="button" class="btn-primary panel-toggle" data-panel="notificationComposer" aria-expanded="false">Compose Notification</button>
    </div>
</div>

<?php if ($message !== ''): ?>
    <div class="alert"><?= esc($message) ?></div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="alert" style="background:#ffe9e9;color:#7a1212;border-color:#f0b7b7;"><?= esc($error) ?></div>
<?php endif; ?>

<div class="stat-strip">
    <div class="stat-chip">
        <span>Total Notifications</span>
        <strong><?= esc($totalNotifications) ?></strong>
    </div>
    <div class="stat-chip">
        <span>Sent</span>
        <strong><?= esc($sentCount) ?></strong>
    </div>
    <div class="stat-chip">
        <span>Scheduled</span>
        <strong><?= esc($scheduledCount) ?></strong>
    </div>
</div>

<div id="notificationComposer" class="card collapsible-panel">
    <div class="card-head">
        <h3>Send Notification</h3>
        <p class="admin-note">Create announcements for all users, by role, or for a specific account.</p>
    </div>

    <form method="POST" class="form-grid">
        <input type="hidden" name="action" value="send">

        <label>Title
            <input name="title" required>
        </label>

        <label>Audience Label
            <input name="audience" placeholder="All Users / Trainers / Specific User" value="All Users">
        </label>

        <label>Type
            <select name="notification_type">
                <option value="announcement">Announcement</option>
                <option value="reminder">Reminder</option>
                <option value="alert">Alert</option>
                <option value="promotion">Promotion</option>
            </select>
        </label>

        <label>Target Role
            <select name="target_role">
                <option value="">All Roles</option>
                <option value="client">Clients</option>
                <option value="trainer">Trainers</option>
                <option value="admin">Admins</option>
            </select>
        </label>

        <label>Target Account
            <select name="target_account_id">
                <option value="0">No specific account</option>
                <?php foreach ($accounts as $account): ?>
                    <option value="<?= esc($account['id']) ?>"><?= esc($account['name']) ?> (<?= esc($account['role']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>Schedule Send Time
            <input type="datetime-local" name="send_at">
        </label>

        <label class="full-span">Message
            <textarea name="message" rows="5" required></textarea>
        </label>

        <div class="full-span">
            <button class="btn-primary" type="submit">Save Notification</button>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-head">
        <h3>Notification History</h3>
        <p class="admin-note">Review what was sent, who it targeted, and when it was scheduled or created.</p>
    </div>

    <?php if (empty($notificationRows)): ?>
        <div class="empty-state">No notifications have been created yet.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Notification</th>
                        <th>Target</th>
                        <th>Timing</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($notificationRows as $row): ?>
                        <tr>
                            <td>
                                <strong><?= esc($row['title']) ?></strong>
                                <div class="meta-list">
                                    <span><?= esc(ucfirst((string)($row['notification_type'] ?? 'announcement'))) ?></span>
                                    <span><?= esc($row['message']) ?></span>
                                </div>
                            </td>
                            <td>
                                <div class="meta-list">
                                    <span><?= esc($row['audience']) ?></span>
                                    <span>Role: <?= esc($row['target_role'] ?: 'All') ?></span>
                                    <span>Account: <?= esc($row['target_name'] ?: 'None') ?></span>
                                </div>
                            </td>
                            <td>
                                <div class="meta-list">
                                    <span>Created: <?= esc($row['created_at']) ?></span>
                                    <span>Scheduled: <?= esc($row['send_at'] ?: 'Immediate') ?></span>
                                    <span>By: <?= esc($row['sender_name'] ?: 'Admin') ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="badge <?= ($row['status'] ?? '') === 'scheduled' ? 'warning' : 'success' ?>">
                                    <?= esc(ucfirst((string)($row['status'] ?? 'sent'))) ?>
                                </span>
                            </td>
                            <td class="actions-cell">
                                <form method="POST" onsubmit="return confirm('Delete this notification?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= esc($row['id']) ?>">
                                    <button class="btn-danger" type="submit">Delete</button>
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
