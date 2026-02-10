<?php
require_once __DIR__ . '/partials/header.php';

if ($conn && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $conn->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES (?, ?)");
    $stmt->bind_param('ss', $_POST['setting_key'], $_POST['setting_value']);
    $stmt->execute();
    $stmt->close();
}

$settings = $conn ? $conn->query("SELECT * FROM settings") : false;
?>

<h2>Settings & Security</h2>
<div class="card">
    <form method="POST" class="form-grid">
        <label>Setting Key <input name="setting_key" required></label>
        <label>Setting Value <input name="setting_value" required></label>
        <button class="btn-primary" type="submit">Save Setting</button>
    </form>
</div>

<table class="table">
    <thead><tr><th>Key</th><th>Value</th></tr></thead>
    <tbody>
        <?php if ($settings): while ($row = $settings->fetch_assoc()): ?>
            <tr><td><?= esc($row['setting_key']) ?></td><td><?= esc($row['setting_value']) ?></td></tr>
        <?php endwhile; endif; ?>
    </tbody>
</table>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
