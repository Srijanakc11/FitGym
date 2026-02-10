<?php
require_once __DIR__ . '/partials/header.php';

if ($conn && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, phone, gender, active) VALUES (?, ?, ?, ?, ?, 1)");
        $password = password_hash($_POST['password'] ?? 'password', PASSWORD_DEFAULT);
        $stmt->bind_param('sssss', $_POST['name'], $_POST['email'], $password, $_POST['phone'], $_POST['gender']);
        $stmt->execute();
        $stmt->close();
    }
    if ($action === 'toggle') {
        $stmt = $conn->prepare("UPDATE users SET active = IF(active=1,0,1) WHERE id = ?");
        $stmt->bind_param('i', $_POST['id']);
        $stmt->execute();
        $stmt->close();
    }
    if ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param('i', $_POST['id']);
        $stmt->execute();
        $stmt->close();
    }
}

$users = $conn ? $conn->query("SELECT u.*, p.height_cm, p.weight_kg, p.goal, p.level FROM users u LEFT JOIN user_profiles p ON u.id = p.user_id ORDER BY u.created_at DESC") : false;
?>

<h2>User Management</h2>
<div class="card">
    <form method="POST" class="form-grid">
        <input type="hidden" name="action" value="add">
        <label>Name <input name="name" required></label>
        <label>Email <input name="email" type="email" required></label>
        <label>Password <input name="password" type="password" value="password"></label>
        <label>Phone <input name="phone"></label>
        <label>Gender
            <select name="gender">
                <option value="male">Male</option>
                <option value="female">Female</option>
                <option value="other">Other</option>
            </select>
        </label>
        <button class="btn-primary" type="submit">Add User</button>
    </form>
</div>

<table class="table">
    <thead>
        <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Status</th>
            <th>Fitness Details</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($users): ?>
            <?php while ($row = $users->fetch_assoc()): ?>
                <tr>
                    <td><?= esc($row['name']) ?></td>
                    <td><?= esc($row['email']) ?></td>
                    <td><?= esc($row['phone']) ?></td>
                    <td><span class="badge <?= $row['active'] ? 'success' : 'danger' ?>"><?= $row['active'] ? 'Active' : 'Inactive' ?></span></td>
                    <td><?= esc($row['height_cm']) ?> cm, <?= esc($row['weight_kg']) ?> kg, <?= esc($row['goal']) ?>, <?= esc($row['level']) ?></td>
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
