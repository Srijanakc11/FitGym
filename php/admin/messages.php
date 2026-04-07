<?php
require_once __DIR__ . '/partials/header.php';

$message = '';
$error = '';

if ($conn) {
    $conn->query(
        "CREATE TABLE IF NOT EXISTS contacts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL,
            subject VARCHAR(255),
            message TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )"
    );
}

if ($conn && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM contacts WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $message = 'Message deleted.';
        } else {
            $error = 'Unable to delete the message.';
        }
    }
}

$contactRows = [];
if ($conn) {
    $result = $conn->query("SELECT * FROM contacts ORDER BY created_at DESC, id DESC");
    if ($result) {
        $contactRows = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();
    }
}

$totalMessages = count($contactRows);
$todayMessages = 0;
$today = date('Y-m-d');
foreach ($contactRows as $row) {
    if (strpos((string)($row['created_at'] ?? ''), $today) === 0) {
        $todayMessages++;
    }
}
?>

<div class="page-header-row">
    <h2>Contact Messages</h2>
    <div class="page-actions">
        <span class="badge success">Inbox</span>
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
        <span>Total Messages</span>
        <strong><?= esc($totalMessages) ?></strong>
    </div>
    <div class="stat-chip">
        <span>Received Today</span>
        <strong><?= esc($todayMessages) ?></strong>
    </div>
</div>

<div class="card">
    <div class="card-head">
        <h3>Inbox</h3>
        <p class="admin-note">Messages sent from the website contact form appear here for admin review.</p>
    </div>

    <?php if (empty($contactRows)): ?>
        <div class="empty-state">No contact messages have been received yet.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Sender</th>
                        <th>Subject</th>
                        <th>Message</th>
                        <th>Received</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($contactRows as $row): ?>
                        <tr>
                            <td>
                                <strong><?= esc($row['name']) ?></strong>
                                <div class="meta-list">
                                    <span><?= esc($row['email']) ?></span>
                                </div>
                            </td>
                            <td><?= esc($row['subject'] ?: 'No subject') ?></td>
                            <td><?= nl2br(esc($row['message'])) ?></td>
                            <td><?= esc($row['created_at']) ?></td>
                            <td class="actions-cell">
                                <div class="inline-actions">
                                    <a class="btn-secondary" href="mailto:<?= esc($row['email']) ?>?subject=<?= rawurlencode('Re: ' . ((string)($row['subject'] ?: 'FitGym contact message'))) ?>">Reply</a>
                                    <form method="POST" onsubmit="return confirm('Delete this message?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= esc($row['id']) ?>">
                                        <button class="btn-danger" type="submit">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
