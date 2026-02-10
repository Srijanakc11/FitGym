<?php
require_once __DIR__ . '/partials/header.php';

if ($conn && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $stmt = $conn->prepare("INSERT INTO classes_admin (name, slug, duration_min, level, kcal_min, kcal_max, weekly_schedule, max_participants, trainer_id, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
        $stmt->bind_param('ssissssii', $_POST['name'], $_POST['slug'], $_POST['duration_min'], $_POST['level'], $_POST['kcal_min'], $_POST['kcal_max'], $_POST['weekly_schedule'], $_POST['max_participants'], $_POST['trainer_id']);
        $stmt->execute();
        $stmt->close();
    }
    if ($action === 'toggle') {
        $stmt = $conn->prepare("UPDATE classes_admin SET active = IF(active=1,0,1) WHERE id = ?");
        $stmt->bind_param('i', $_POST['id']);
        $stmt->execute();
        $stmt->close();
    }
    if ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM classes_admin WHERE id = ?");
        $stmt->bind_param('i', $_POST['id']);
        $stmt->execute();
        $stmt->close();
    }
}

$classes = $conn ? $conn->query("SELECT c.*, t.name AS trainer_name FROM classes_admin c LEFT JOIN trainers t ON c.trainer_id = t.id ORDER BY c.created_at DESC") : false;
$trainers = $conn ? $conn->query("SELECT id, name FROM trainers WHERE active = 1") : false;
?>

<h2>Class / Program Management</h2>
<div class="card">
    <form method="POST" class="form-grid">
        <input type="hidden" name="action" value="add">
        <label>Class Name <input name="name" required></label>
        <label>Slug <input name="slug" required></label>
        <label>Duration (min) <input type="number" name="duration_min" value="45"></label>
        <label>Level
            <select name="level">
                <option value="Beginner">Beginner</option>
                <option value="Intermediate">Intermediate</option>
                <option value="Advanced">Advanced</option>
            </select>
        </label>
        <label>kcal Min <input type="number" name="kcal_min" value="200"></label>
        <label>kcal Max <input type="number" name="kcal_max" value="500"></label>
        <label>Weekly Schedule <input name="weekly_schedule" placeholder="Mon, Wed, Fri"></label>
        <label>Max Participants <input type="number" name="max_participants" value="20"></label>
        <label>Trainer
            <select name="trainer_id">
                <?php if ($trainers): while ($t = $trainers->fetch_assoc()): ?>
                    <option value="<?= esc($t['id']) ?>"><?= esc($t['name']) ?></option>
                <?php endwhile; endif; ?>
            </select>
        </label>
        <button class="btn-primary" type="submit">Add Class</button>
    </form>
</div>

<table class="table">
    <thead>
        <tr>
            <th>Class</th>
            <th>Level</th>
            <th>Duration</th>
            <th>Trainer</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($classes): ?>
            <?php while ($row = $classes->fetch_assoc()): ?>
                <tr>
                    <td><?= esc($row['name']) ?></td>
                    <td><?= esc($row['level']) ?></td>
                    <td><?= esc($row['duration_min']) ?> min</td>
                    <td><?= esc($row['trainer_name']) ?></td>
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
