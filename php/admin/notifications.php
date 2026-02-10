<?php
require_once __DIR__ . '/partials/header.php';

if ($conn && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $conn->prepare("INSERT INTO notifications (title, message, audience) VALUES (?, ?, ?)");
    $stmt->bind_param('sss', $_POST['title'], $_POST['message'], $_POST['audience']);
    $stmt->execute();
    $stmt->close();
}

$notes = $conn ? $conn->query("SELECT * FROM notifications ORDER BY created_at DESC") : false;
?>

<h2>Notifications & Emails</h2>
<div class="card">
    <form method="POST" class="form-grid">
        <label>Title <input name="title" required></label>
        <label>Audience <input name="audience" placeholder="All Users"></label>
        <label>Message <textarea name="message" rows="3"></textarea></label>
        <button class="btn-primary" type="submit">Send Announcement</button>
    </form>
</div>

<table class="table">
    <thead><tr><th>Title</th><th>Audience</th><th>Message</th><th>Date</th></tr></thead>
    <tbody>
        <?php if ($notes): while ($row = $notes->fetch_assoc()): ?>
            <tr>
                <td><?= esc($row['title']) ?></td>
                <td><?= esc($row['audience']) ?></td>
                <td><?= esc($row['message']) ?></td>
                <td><?= esc($row['created_at']) ?></td>
            </tr>
        <?php endwhile; endif; ?>
    </tbody>
</table>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
