<?php
require_once __DIR__ . '/partials/header.php';

if ($conn && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $conn->prepare("UPDATE reviews SET status = ? WHERE id = ?");
    $stmt->bind_param('si', $_POST['status'], $_POST['id']);
    $stmt->execute();
    $stmt->close();
}

$reviews = $conn ? $conn->query("SELECT * FROM reviews ORDER BY created_at DESC") : false;
?>

<h2>Feedback & Reviews</h2>
<table class="table">
    <thead><tr><th>User</th><th>Rating</th><th>Comment</th><th>Status</th><th>Action</th></tr></thead>
    <tbody>
        <?php if ($reviews): while ($row = $reviews->fetch_assoc()): ?>
            <tr>
                <td><?= esc($row['user_name']) ?></td>
                <td><?= esc($row['rating']) ?></td>
                <td><?= esc($row['comment']) ?></td>
                <td><span class="badge <?= $row['status']==='Approved' ? 'success' : 'warning' ?>"><?= esc($row['status']) ?></span></td>
                <td>
                    <form method="POST">
                        <input type="hidden" name="id" value="<?= esc($row['id']) ?>">
                        <select name="status">
                            <option <?= $row['status']==='Pending'?'selected':'' ?>>Pending</option>
                            <option <?= $row['status']==='Approved'?'selected':'' ?>>Approved</option>
                            <option <?= $row['status']==='Rejected'?'selected':'' ?>>Rejected</option>
                        </select>
                        <button class="btn-secondary" type="submit">Update</button>
                    </form>
                </td>
            </tr>
        <?php endwhile; endif; ?>
    </tbody>
</table>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
