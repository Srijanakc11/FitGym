<?php
require_once __DIR__ . '/partials/header.php';

if ($conn && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $conn->prepare("REPLACE INTO content_blocks (block_key, title, body) VALUES (?, ?, ?)");
    $stmt->bind_param('sss', $_POST['block_key'], $_POST['title'], $_POST['body']);
    $stmt->execute();
    $stmt->close();
}

$blocks = $conn ? $conn->query("SELECT * FROM content_blocks") : false;
?>

<h2>Content Management</h2>
<div class="card">
    <form method="POST" class="form-grid">
        <label>Block Key <input name="block_key" placeholder="home_hero" required></label>
        <label>Title <input name="title"></label>
        <label>Body <textarea name="body" rows="4"></textarea></label>
        <button class="btn-primary" type="submit">Save Block</button>
    </form>
</div>

<table class="table">
    <thead>
        <tr>
            <th>Key</th>
            <th>Title</th>
            <th>Body</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($blocks): while ($row = $blocks->fetch_assoc()): ?>
            <tr>
                <td><?= esc($row['block_key']) ?></td>
                <td><?= esc($row['title']) ?></td>
                <td><?= esc($row['body']) ?></td>
            </tr>
        <?php endwhile; endif; ?>
    </tbody>
</table>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
