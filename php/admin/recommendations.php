<?php
require_once __DIR__ . '/partials/header.php';

if ($conn && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $stmt = $conn->prepare("INSERT INTO recommendation_rules (goal, workout_type, duration_weeks, days_per_week, daily_minutes, difficulty_map, active) VALUES (?, ?, ?, ?, ?, ?, 1)");
        $stmt->bind_param('ssiiis', $_POST['goal'], $_POST['workout_type'], $_POST['duration_weeks'], $_POST['days_per_week'], $_POST['daily_minutes'], $_POST['difficulty_map']);
        $stmt->execute();
        $stmt->close();
    }
    if ($action === 'toggle') {
        $stmt = $conn->prepare("UPDATE recommendation_rules SET active = IF(active=1,0,1) WHERE id = ?");
        $stmt->bind_param('i', $_POST['id']);
        $stmt->execute();
        $stmt->close();
    }
}

$rules = $conn ? $conn->query("SELECT * FROM recommendation_rules ORDER BY created_at DESC") : false;
?>

<h2>Recommendation System Management</h2>
<div class="card">
    <form method="POST" class="form-grid">
        <input type="hidden" name="action" value="add">
        <label>Goal <input name="goal" placeholder="Weight loss" required></label>
        <label>Workout Type <input name="workout_type" placeholder="Cardio + HIIT" required></label>
        <label>Duration (weeks) <input type="number" name="duration_weeks" value="6"></label>
        <label>Days / Week <input type="number" name="days_per_week" value="4"></label>
        <label>Daily Minutes <input type="number" name="daily_minutes" value="30"></label>
        <label>Difficulty Map <input name="difficulty_map" placeholder="Beginner=Low, Intermediate=Moderate"></label>
        <button class="btn-primary" type="submit">Add Rule</button>
    </form>
</div>

<table class="table">
    <thead>
        <tr>
            <th>Goal</th>
            <th>Workout Type</th>
            <th>Duration</th>
            <th>Days/Week</th>
            <th>Daily Minutes</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($rules): ?>
            <?php while ($row = $rules->fetch_assoc()): ?>
                <tr>
                    <td><?= esc($row['goal']) ?></td>
                    <td><?= esc($row['workout_type']) ?></td>
                    <td><?= esc($row['duration_weeks']) ?> weeks</td>
                    <td><?= esc($row['days_per_week']) ?></td>
                    <td><?= esc($row['daily_minutes']) ?> min</td>
                    <td><span class="badge <?= $row['active'] ? 'success' : 'danger' ?>"><?= $row['active'] ? 'Active' : 'Inactive' ?></span></td>
                    <td>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= esc($row['id']) ?>">
                            <button class="btn-secondary" type="submit">Toggle</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php endif; ?>
    </tbody>
</table>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
