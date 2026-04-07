<?php
require_once __DIR__ . '/partials/header.php';

$message = '';
$error = '';
$weekdayOptions = [
    'Mon' => 'Monday',
    'Tue' => 'Tuesday',
    'Wed' => 'Wednesday',
    'Thu' => 'Thursday',
    'Fri' => 'Friday',
    'Sat' => 'Saturday',
    'Sun' => 'Sunday',
];
$availabilityDayOptions = [
    'Mon-Fri' => 'Monday to Friday',
    'Mon-Sat' => 'Monday to Saturday',
    'Sun-Thu' => 'Sunday to Thursday',
    'Fri-Sat' => 'Friday to Saturday',
    'Sat-Sun' => 'Saturday to Sunday',
    'Everyday' => 'Everyday',
];

if (!function_exists('fitgym_next_trainer_code')) {
    function fitgym_next_trainer_code(mysqli $conn): string
    {
        $result = $conn->query("SELECT login_code FROM accounts WHERE role = 'trainer' AND login_code LIKE 'TRN-%' ORDER BY id DESC");
        $maxNumber = 1000;
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $code = (string)($row['login_code'] ?? '');
                if (preg_match('/^TRN-(\d+)$/', $code, $matches)) {
                    $maxNumber = max($maxNumber, (int)$matches[1]);
                }
            }
            $result->free();
        }
        return 'TRN-' . ($maxNumber + 1);
    }
}

if ($conn && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $specialization = trim($_POST['specialization'] ?? '');
        $experienceYears = (int)($_POST['experience_years'] ?? 0);
        $imagePath = trim($_POST['image_path'] ?? '');
        $availabilityDay = trim((string)($_POST['availability_day'] ?? ''));
        $availabilityTime = trim((string)($_POST['availability_time'] ?? ''));
        $availability = '';
        $qualification = trim($_POST['qualification'] ?? '');
        $password = $_POST['password'] ?? '';

        if (isset($availabilityDayOptions[$availabilityDay]) && $availabilityTime !== '') {
            $availability = $availabilityDay . ' ' . $availabilityTime;
        }

        if ($name === '' || $specialization === '' || $qualification === '' || $password === '') {
            $error = 'Please fill in the required trainer details and password.';
        } else {
            $trainerCode = fitgym_next_trainer_code($conn);
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $adminId = (int)($_SESSION['auth_id'] ?? $_SESSION['admin_id'] ?? 0);

            $stmt = $conn->prepare(
                "INSERT INTO accounts
                (role, name, email, login_code, password_hash, specialization, experience_years, image_path, availability,
                 qualification, qualification_status, verified_by_account_id, verified_at, active, legacy_source, legacy_id)
                VALUES ('trainer', ?, NULL, ?, ?, ?, ?, '', ?, ?, 'verified', ?, NOW(), 1, NULL, NULL)"
            );
            if ($stmt) {
                $stmt->bind_param('ssssissi', $name, $trainerCode, $passwordHash, $specialization, $experienceYears, $availability, $qualification, $adminId);
                if ($stmt->execute()) {
                    $message = 'Trainer account created and verified. Trainer ID: ' . $trainerCode . ' | Password: ' . $password;
                } else {
                    $error = 'Unable to create trainer account right now.';
                }
                $stmt->close();
            } else {
                $error = 'Trainer account form is not fully configured yet.';
            }
        }
    }

    if ($action === 'toggle') {
        $stmt = $conn->prepare("UPDATE accounts SET active = IF(active=1,0,1) WHERE id = ? AND role = 'trainer'");
        if ($stmt) {
            $stmt->bind_param('i', $_POST['id']);
            $stmt->execute();
            $stmt->close();
        }
    }

    if ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM accounts WHERE id = ? AND role = 'trainer'");
        if ($stmt) {
            $stmt->bind_param('i', $_POST['id']);
            $stmt->execute();
            $stmt->close();
        }
    }

    if ($action === 'verify') {
        $stmt = $conn->prepare("UPDATE accounts SET qualification_status = 'verified', verified_by_account_id = ?, verified_at = NOW() WHERE id = ? AND role = 'trainer'");
        if ($stmt) {
            $adminId = (int)($_SESSION['auth_id'] ?? $_SESSION['admin_id'] ?? 0);
            $trainerId = (int)($_POST['id'] ?? 0);
            $stmt->bind_param('ii', $adminId, $trainerId);
            $stmt->execute();
            $stmt->close();
        }
    }

    if ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE accounts SET qualification_status = 'rejected', verified_by_account_id = ?, verified_at = NOW() WHERE id = ? AND role = 'trainer'");
        if ($stmt) {
            $adminId = (int)($_SESSION['auth_id'] ?? $_SESSION['admin_id'] ?? 0);
            $trainerId = (int)($_POST['id'] ?? 0);
            $stmt->bind_param('ii', $adminId, $trainerId);
            $stmt->execute();
            $stmt->close();
        }
    }

    if ($action === 'reset_password') {
        $newPassword = $_POST['new_password'] ?? '';
        if ($newPassword !== '') {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE accounts SET password_hash = ? WHERE id = ? AND role = 'trainer'");
            if ($stmt) {
                $trainerId = (int)($_POST['id'] ?? 0);
                $stmt->bind_param('si', $hash, $trainerId);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
}

$trainers = $conn ? $conn->query("SELECT * FROM accounts WHERE role = 'trainer' ORDER BY created_at DESC") : false;
$trainerRows = $trainers ? $trainers->fetch_all(MYSQLI_ASSOC) : [];
$verifiedCount = 0;
$activeCount = 0;
foreach ($trainerRows as $trainer) {
    if (($trainer['qualification_status'] ?? '') === 'verified') {
        $verifiedCount++;
    }
    if ((int)($trainer['active'] ?? 0) === 1) {
        $activeCount++;
    }
}
?>

<div class="page-header-row">
    <h2>Trainer Management</h2>
    <div class="page-actions">
        <button type="button" class="btn-primary panel-toggle" data-panel="trainerAddPanel" aria-expanded="false">Add Trainer</button>
    </div>
</div>

<?php if ($message !== ''): ?>
    <div class="alert"><?= esc($message) ?></div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="alert" style="background:#ffe9e9;color:#7a1212;border-color:#f0b7b7;"><?= esc($error) ?></div>
<?php endif; ?>

<div class="stat-strip">
    <div class="stat-chip">
        <span>Total Trainers</span>
        <strong><?= esc(count($trainerRows)) ?></strong>
    </div>
    <div class="stat-chip">
        <span>Verified</span>
        <strong><?= esc($verifiedCount) ?></strong>
    </div>
    <div class="stat-chip">
        <span>Active Accounts</span>
        <strong><?= esc($activeCount) ?></strong>
    </div>
</div>

<div id="trainerAddPanel" class="card collapsible-panel">
    <div class="card-head">
        <h3>Create Trainer Account</h3>
        <p class="admin-note">Admin creates the trainer login. The system generates the Trainer ID automatically.</p>
    </div>
    <form method="POST" class="form-grid">
        <input type="hidden" name="action" value="add">
        <label>Password <input name="password" type="password" required></label>
        <label>Name <input name="name" required></label>
        <label>Specialization <input name="specialization" required></label>
        <label>Experience (years) <input name="experience_years" type="number" min="0" value="1"></label>
        <div class="full-span">
            <label>Availability</label>
            <div class="inline-actions">
                <select name="availability_day">
                    <option value="">Select day range</option>
                    <?php foreach ($availabilityDayOptions as $value => $label): ?>
                        <option value="<?= esc($value) ?>"><?= esc($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="availability_time" placeholder="5:30 AM - 7:30 AM">
            </div>
        </div>
        <label class="full-span">Qualification
            <textarea name="qualification" rows="4" required></textarea>
        </label>
        <p class="admin-note full-span">After saving, the generated Trainer ID and the password you entered will appear in a message above.</p>
        <div class="full-span">
            <button class="btn-primary" type="submit">Save Trainer</button>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-head">
        <h3>Trainer Directory</h3>
        <p class="admin-note">Verification, account control, and password resets live in one table.</p>
    </div>

    <?php if (empty($trainerRows)): ?>
        <div class="empty-state">No trainer accounts found.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Trainer</th>
                        <th>Profile</th>
                        <th>Qualification</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($trainerRows as $row): ?>
                        <tr>
                            <td>
                                <strong><?= esc($row['name']) ?></strong>
                                <div class="meta-list">
                                    <span><?= esc($row['login_code']) ?></span>
                                    <span><?= esc($row['specialization']) ?></span>
                                </div>
                            </td>
                            <td>
                                <div class="meta-list">
                                    <span><?= esc($row['experience_years']) ?> yrs experience</span>
                                    <span><?= esc($row['availability'] ?: 'Availability not set') ?></span>
                                    <span><?= esc($row['image_path'] ?: 'No image path') ?></span>
                                </div>
                            </td>
                            <td><?= nl2br(esc($row['qualification'])) ?></td>
                            <td>
                                <div class="meta-list">
                                    <span class="badge <?= $row['qualification_status'] === 'verified' ? 'success' : ($row['qualification_status'] === 'rejected' ? 'danger' : 'warning') ?>"><?= esc(ucfirst($row['qualification_status'])) ?></span>
                                    <span class="badge <?= $row['active'] ? 'success' : 'danger' ?>"><?= $row['active'] ? 'Active' : 'Inactive' ?></span>
                                </div>
                            </td>
                            <td class="actions-cell">
                                <div class="inline-actions">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="verify">
                                        <input type="hidden" name="id" value="<?= esc($row['id']) ?>">
                                        <button class="btn-secondary" type="submit">Verify</button>
                                    </form>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="id" value="<?= esc($row['id']) ?>">
                                        <button class="btn-secondary" type="submit">Reject</button>
                                    </form>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?= esc($row['id']) ?>">
                                        <button class="btn-secondary" type="submit"><?= $row['active'] ? 'Disable' : 'Enable' ?></button>
                                    </form>
                                    <form method="POST" onsubmit="return confirm('Delete this trainer account?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= esc($row['id']) ?>">
                                        <button class="btn-danger" type="submit">Delete</button>
                                    </form>
                                </div>
                                <form method="POST" class="password-inline" style="margin-top:8px;">
                                    <input type="hidden" name="action" value="reset_password">
                                    <input type="hidden" name="id" value="<?= esc($row['id']) ?>">
                                    <input type="password" name="new_password" placeholder="New password">
                                    <button class="btn-secondary" type="submit">Reset</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
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
