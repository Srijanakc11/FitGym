<?php
require_once __DIR__ . '/partials/header.php';

if ($conn && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $stmt = $conn->prepare("INSERT INTO trainers (name, specialization, experience_years, image_path, availability, active) VALUES (?, ?, ?, ?, ?, 1)");
        $stmt->bind_param('ssiss', $_POST['name'], $_POST['specialization'], $_POST['experience_years'], $_POST['image_path'], $_POST['availability']);
        $stmt->execute();
        $stmt->close();
    }
    if ($action === 'toggle') {
        $stmt = $conn->prepare("UPDATE trainers SET active = IF(active=1,0,1) WHERE id = ?");
        $stmt->bind_param('i', $_POST['id']);
        $stmt->execute();
        $stmt->close();
    }
    if ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM trainers WHERE id = ?");
        $stmt->bind_param('i', $_POST['id']);
        $stmt->execute();
        $stmt->close();
    }
}

$trainers = $conn ? $conn->query("SELECT * FROM trainers ORDER BY created_at DESC") : false;
?>

<h2>Trainer Management</h2>
<div class="card">
    <form method="POST" class="form-grid">
        <input type="hidden" name="action" value="add">
        <label>Name <input name="name" required></label>
        <label>Specialization <input name="specialization" required></label>
        <label>Experience (years) <input name="experience_years" type="number" min="0" value="1"></label>
        <label>Image Path <input name="image_path" placeholder="/fitgym/pictures/trainer.jpg"></label>
        <label>Availability <input name="availability" placeholder="Mon-Fri 6am-8pm"></label>
        <button class="btn-primary" type="submit">Add Trainer</button>
    </form>
</div>

<table class="table">
    <thead>
        <tr>
            <th>Name</th>
            <th>Specialization</th>
            <th>Experience</th>
            <th>Availability</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($trainers): ?>
            <?php while ($row = $trainers->fetch_assoc()): ?>
                <tr>
                    <td><?= esc($row['name']) ?></td>
                    <td><?= esc($row['specialization']) ?></td>
                    <td><?= esc($row['experience_years']) ?> yrs</td>
                    <td><?= esc($row['availability']) ?></td>
                    <td><span class="badge <?= $row['active'] ? 'success' : 'danger' ?>"><?= $row['active'] ? 'Active' : 'Inactive' ?></span></td>
                    <td>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= esc($row['id']) ?>">
                            <button class="btn-secondary" type="submit">Toggle</button>
                        </form>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= esc($row['id']) ?>">
                            <button class="btn-secondary" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php endif; ?>
    </tbody>
</table>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
