<?php
require_once __DIR__ . '/partials/header.php';

if ($conn && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';
    if ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM content_blocks WHERE block_key = ?");
        $stmt->bind_param('s', $_POST['block_key']);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $conn->prepare("REPLACE INTO content_blocks (block_key, title, body) VALUES (?, ?, ?)");
        $stmt->bind_param('sss', $_POST['block_key'], $_POST['title'], $_POST['body']);
        $stmt->execute();
        $stmt->close();
    }
}

$blocks = $conn ? $conn->query("SELECT * FROM content_blocks") : false;
?>

<h2>Content Management</h2>
<div class="card">
    <form method="POST" class="form-grid">
        <input type="hidden" name="action" value="save">
        <label>Block Key <input name="block_key" placeholder="home_hero" required></label>
        <label>Title <input name="title"></label>
        <label>Body <textarea name="body" rows="4"></textarea></label>
        <button class="btn-primary" type="submit">Save Block</button>
    </form>
</div>

<div class="card">
    <h3>Common Block Keys</h3>
    <p><strong>Home:</strong> home_hero, home_bmi_intro, home_featured_classes (JSON), home_testimonials (JSON)</p>
    <p><strong>About:</strong> about_hero, about_who_we_are, about_who_we_are_extra, about_mission, about_cards (JSON)</p>
    <p><strong>Contact:</strong> contact_hero, contact_box</p>
    <p><strong>Classes:</strong> classes_header, classes_recommend_teaser, classes_fallback (JSON)</p>
</div>

<table class="table">
    <thead>
        <tr>
            <th>Key</th>
            <th>Title</th>
            <th>Body</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($blocks): while ($row = $blocks->fetch_assoc()): ?>
            <tr>
                <td><?= esc($row['block_key']) ?></td>
                <td><?= esc($row['title']) ?></td>
                <td><?= esc($row['body']) ?></td>
                <td>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="block_key" value="<?= esc($row['block_key']) ?>">
                        <button class="btn-secondary" type="submit">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endwhile; endif; ?>
    </tbody>
</table>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
