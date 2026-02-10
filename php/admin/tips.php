<?php
require_once __DIR__ . '/partials/header.php';

if ($conn && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $conn->prepare("INSERT INTO tips (title, body, category) VALUES (?, ?, ?)");
    $stmt->bind_param('sss', $_POST['title'], $_POST['body'], $_POST['category']);
    $stmt->execute();
    $stmt->close();
}

$tips = $conn ? $conn->query("SELECT * FROM tips ORDER BY created_at DESC") : false;
?>

<h2>Nutrition / Tips Management</h2>
<div class="card">
    <form method="POST" class="form-grid">
        <label>Title <input name="title" required></label>
        <label>Category <input name="category" placeholder="Nutrition"></label>
        <label>Tip <textarea name="body" rows="3"></textarea></label>
        <button class="btn-primary" type="submit">Add Tip</button>
    </form>
</div>

<table class="table">
    <thead>
        <tr>
            <th>Title</th>
            <th>Category</th>
            <th>Tip</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($tips): while ($row = $tips->fetch_assoc()): ?>
            <tr>
                <td><?= esc($row['title']) ?></td>
                <td><?= esc($row['category']) ?></td>
                <td><?= esc($row['body']) ?></td>
            </tr>
        <?php endwhile; endif; ?>
    </tbody>
</table>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
