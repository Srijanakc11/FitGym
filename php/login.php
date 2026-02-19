<?php
// login.php
session_start();
require_once __DIR__ . "/database.php";

$loginError = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($db_error) && $conn) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // 1) Admin login
    $stmt = $conn->prepare("SELECT id, name, password_hash, active FROM admin_users WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($admin && (int)$admin['active'] === 1 && password_verify($password, $admin['password_hash'])) {
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_name'] = $admin['name'];
        header('Location: /fitgym/php/admin/index.php');
        exit;
    }

    // 2) Client login
    $stmt = $conn->prepare("SELECT id, name, email, password, active FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user && (int)$user['active'] === 1 && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        header('Location: /fitgym/php/client/dashboard.php');
        exit;
    }

    $loginError = "Invalid credentials or account disabled.";
}

include("../includes/header.php");
?>

<link rel="stylesheet" href="/fitgym/css/login.css">
<link rel="stylesheet" href="/fitgym/css/header.css">
<link rel="stylesheet" href="/fitgym/css/footer.css">
<link rel="stylesheet" href="../css/index.css">

<main class="page-content">

    <div class="login-container">
        <h2 class="login-title">Login</h2>

        <?php if (!empty($loginError)): ?>
            <div class="error-msg">
                <?= htmlspecialchars($loginError) ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST" class="simple-login-form">
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="btn-submit">Login</button>
            
            <div class="form-footer">
                <a href="#">Forgot Password?</a>
            </div>
        </form>

        <p class="signup-link">
            New here? <a href="signup.php">Create an account</a>
        </p>
    </div>

</main>

<?php
include("../includes/footer.php");
?>
