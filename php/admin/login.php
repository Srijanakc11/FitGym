<?php
require_once __DIR__ . '/_bootstrap.php';

$message = '';

if (empty($db_error) && $conn) {
    // Ensure at least one admin exists
    $check = $conn->query("SELECT id FROM admin_users LIMIT 1");
    if ($check && $check->num_rows === 0) {
        $defaultPass = password_hash('admin123', PASSWORD_DEFAULT);
        $conn->query("INSERT INTO admin_users (name, email, password_hash, role, active) VALUES ('Admin', 'admin@fitgym.com', '{$defaultPass}', 'admin', 1)");
        $message = 'Default admin created: admin@fitgym.com / admin123';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($db_error) && $conn) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT id, name, password_hash, active FROM admin_users WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($result && (int)$result['active'] === 1 && password_verify($password, $result['password_hash'])) {
        $_SESSION['admin_id'] = $result['id'];
        $_SESSION['admin_name'] = $result['name'];
        header('Location: /fitgym/php/admin/index.php');
        exit;
    }

    $message = 'Invalid credentials or account disabled.';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Login | FitGym</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="/fitgym/pictures/favicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/fitgym/css/admin.css">
</head>
<body class="auth-page">
    <div class="auth-card">
        <div class="brand">
            <img src="/fitgym/pictures/favicon.png" alt="FitGym">
            <span>FitGym Admin</span>
        </div>
        <h1>Admin Login</h1>
        <?php if (!empty($db_error)): ?>
            <div class="alert">Database not connected. Check database settings.</div>
        <?php elseif (!empty($message)): ?>
            <div class="alert"><?= esc($message) ?></div>
        <?php endif; ?>
        <form method="POST">
            <label>Email
                <input type="email" name="email" required>
            </label>
            <label>Password
                <input type="password" name="password" required>
            </label>
            <button type="submit" class="btn-primary">Sign In</button>
        </form>
    </div>
</body>
</html>
