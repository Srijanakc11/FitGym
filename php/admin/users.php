<?php
require_once __DIR__ . '/partials/header.php';

if ($conn && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $stmt = $conn->prepare(
            "INSERT INTO accounts (role, name, email, login_code, password_hash, phone, gender, qualification_status, active, legacy_source, legacy_id)
             VALUES ('client', ?, ?, ?, ?, ?, ?, 'verified', 1, NULL, NULL)"
        );
        if ($stmt) {
            $password = password_hash($_POST['password'] ?? 'password', PASSWORD_DEFAULT);
            $email = trim((string)($_POST['email'] ?? ''));
            $stmt->bind_param('ssssss', $_POST['name'], $email, $email, $password, $_POST['phone'], $_POST['gender']);
            $stmt->execute();
            $stmt->close();
        }
    }

    if ($action === 'toggle') {
        $stmt = $conn->prepare("UPDATE accounts SET active = IF(active=1,0,1) WHERE id = ? AND role = 'client'");
        if ($stmt) {
            $stmt->bind_param('i', $_POST['id']);
            $stmt->execute();
            $stmt->close();
        }
    }

    if ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM accounts WHERE id = ? AND role = 'client'");
        if ($stmt) {
            $stmt->bind_param('i', $_POST['id']);
            $stmt->execute();
            $stmt->close();
        }
    }
}

$users = $conn ? $conn->query(
    "SELECT a.*, p.height_cm, p.weight_kg, p.goal, p.level
     FROM accounts a
     LEFT JOIN user_profiles p ON a.legacy_source = 'users' AND a.legacy_id = p.user_id
     WHERE a.role = 'client'
     ORDER BY a.created_at DESC"
) : false;

$totalUsers = 0;
$activeUsers = 0;
if ($users) {
    $userRows = $users->fetch_all(MYSQLI_ASSOC);
    $totalUsers = count($userRows);
    foreach ($userRows as $item) {
        if ((int)($item['active'] ?? 0) === 1) {
            $activeUsers++;
        }
    }
} else {
    $userRows = [];
}
?>

<div class="page-header-row">
    <h2>User Management</h2>
    <div class="page-actions">
        <button type="button" class="btn-primary panel-toggle" data-panel="userAddPanel" aria-expanded="false">Add User</button>
    </div>
</div>

<div class="stat-strip">
    <div class="stat-chip">
        <span>Total Accounts</span>
        <strong><?= esc($totalUsers) ?></strong>
    </div>
    <div class="stat-chip">
        <span>Active Users</span>
        <strong><?= esc($activeUsers) ?></strong>
    </div>
    <div class="stat-chip">
        <span>Inactive Users</span>
        <strong><?= esc(max(0, $totalUsers - $activeUsers)) ?></strong>
    </div>
</div>

<div id="userAddPanel" class="card collapsible-panel">
    <div class="card-head">
        <h3>Create Client Account</h3>
        <p class="admin-note">Open this form only when you need to add a new user manually.</p>
    </div>
    <form method="POST" class="form-grid">
        <input type="hidden" name="action" value="add">
        <label>Name <input name="name" required></label>
        <label>Email <input name="email" type="email" required></label>
        <label>Password <input name="password" type="password" value="password"></label>
        <label>Phone <input name="phone"></label>
        <label class="full-span">Gender
            <select name="gender">
                <option value="male">Male</option>
                <option value="female">Female</option>
                <option value="other">Other</option>
            </select>
        </label>
        <div class="full-span">
            <button class="btn-primary" type="submit">Save User</button>
        </div>
    </form>
</div>

<div class="section-stack">
    <div class="card">
        <div class="card-head">
            <h3>User Directory</h3>
            <p class="admin-note">All client accounts in one place with quick status actions.</p>
        </div>

        <?php if (empty($userRows)): ?>
            <div class="empty-state">No user accounts found.</div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Contact</th>
                            <th>Fitness Profile</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($userRows as $row): ?>
                            <tr>
                                <td>
                                    <strong><?= esc($row['name']) ?></strong>
                                    <div class="meta-list">
                                        <span>ID #<?= esc($row['id']) ?></span>
                                        <span>Joined <?= esc($row['created_at']) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="meta-list">
                                        <span><?= esc($row['email']) ?></span>
                                        <span><?= esc($row['phone'] ?: 'No phone added') ?></span>
                                        <span><?= esc(ucfirst((string)($row['gender'] ?: 'unspecified'))) ?></span>
                                    </div>
                                </td>
                                <td><?= esc($row['height_cm']) ?> cm, <?= esc($row['weight_kg']) ?> kg, <?= esc($row['goal']) ?>, <?= esc($row['level']) ?></td>
                                <td><span class="badge <?= $row['active'] ? 'success' : 'danger' ?>"><?= $row['active'] ? 'Active' : 'Inactive' ?></span></td>
                                <td class="actions-cell">
                                    <div class="inline-actions">
                                        <form method="POST">
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="id" value="<?= esc($row['id']) ?>">
                                            <button class="btn-secondary" type="submit"><?= $row['active'] ? 'Disable' : 'Enable' ?></button>
                                        </form>
                                        <form method="POST" onsubmit="return confirm('Delete this user account?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= esc($row['id']) ?>">
                                            <button class="btn-danger" type="submit">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.panel-toggle').forEach(function (button) {
        button.addEventListener('click', function () {
            const panel = document.getElementById(button.dataset.panel || '');
            if (!panel) return;
            const open = panel.classList.toggle('is-open');
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    });
});
</script>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
