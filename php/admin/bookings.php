<?php
require_once __DIR__ . '/partials/header.php';

if ($conn && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'status') {
        $stmt = $conn->prepare("UPDATE bookings SET status = ? WHERE id = ?");
        $stmt->bind_param('si', $_POST['status'], $_POST['id']);
        $stmt->execute();
        $stmt->close();
    }
}

$where = "1=1";
$params = [];
$types = '';

if (!empty($_GET['date'])) {
    $where .= " AND preferred_date = ?";
    $params[] = $_GET['date'];
    $types .= 's';
}
if (!empty($_GET['class'])) {
    $where .= " AND class_name = ?";
    $params[] = $_GET['class'];
    $types .= 's';
}
if (!empty($_GET['trainer'])) {
    $where .= " AND trainer_name = ?";
    $params[] = $_GET['trainer'];
    $types .= 's';
}

$bookings = [];
if ($conn) {
    $sql = "SELECT * FROM bookings WHERE {$where} ORDER BY created_at DESC";
    if ($params) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $bookings = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
    }
}
?>

<h2>Booking Management</h2>
<div class="card">
    <form method="GET" class="form-grid">
        <label>Date <input type="date" name="date" value="<?= esc($_GET['date'] ?? '') ?>"></label>
        <label>Class <input name="class" value="<?= esc($_GET['class'] ?? '') ?>"></label>
        <label>Trainer <input name="trainer" value="<?= esc($_GET['trainer'] ?? '') ?>"></label>
        <button class="btn-primary" type="submit">Filter</button>
    </form>
</div>

<table class="table">
    <thead>
        <tr>
            <th>User</th>
            <th>Class</th>
            <th>Trainer</th>
            <th>Date</th>
            <th>Time</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($bookings as $row): ?>
            <tr>
                <td><?= esc($row['full_name']) ?></td>
                <td><?= esc($row['class_name']) ?></td>
                <td><?= esc($row['trainer_name']) ?></td>
                <td><?= esc($row['preferred_date']) ?></td>
                <td><?= esc($row['time_slot']) ?></td>
                <td><span class="badge <?= $row['status']==='Confirmed' ? 'success' : ($row['status']==='Cancelled' ? 'danger' : 'warning') ?>"><?= esc($row['status']) ?></span></td>
                <td>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="action" value="status">
                        <input type="hidden" name="id" value="<?= esc($row['id']) ?>">
                        <select name="status">
                            <option <?= $row['status']==='Pending'?'selected':'' ?>>Pending</option>
                            <option <?= $row['status']==='Confirmed'?'selected':'' ?>>Confirmed</option>
                            <option <?= $row['status']==='Cancelled'?'selected':'' ?>>Cancelled</option>
                        </select>
                        <button class="btn-secondary" type="submit">Update</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
