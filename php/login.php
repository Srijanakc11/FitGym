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

<!-- PAGE-SPECIFIC CSS -->
<link rel="stylesheet" href="/fitgym/css/login.css">
    <!-- GLOBAL -->
    <link rel="stylesheet" href="/fitgym/css/header.css">
    <link rel="stylesheet" href="/fitgym/css/footer.css">
    <link rel="stylesheet" href="../css/index.css">

<main class="page-content">

    <div class="login-buffer"></div> <!-- Spacer if needed, or handled by margin -->

    <div class="login-wrapper">
        <h2 class="login-title">Welcome Back</h2>

        <?php if (!empty($loginError)): ?>
            <div class="error">
                <?= htmlspecialchars($loginError) ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST" class="login-form">
            
            <div class="input-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="example@fitgym.com" required>
            </div>

            <div class="input-group password-group">
                <label for="password">Password</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" placeholder="••••••••" required>
                    <span class="toggle-password" onclick="togglePassword()">
                        <!-- Eye Icon (Open) -->
                        <svg id="eye-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </span>
                </div>
            </div>

            <script>
            function togglePassword() {
                const passwordInput = document.getElementById('password');
                const eyeIcon = document.getElementById('eye-icon');
                
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    // Switch to Eye Off (Crossed out)
                    eyeIcon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>';
                } else {
                    passwordInput.type = 'password';
                    // Switch back to Eye Open
                    eyeIcon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>';
                }
            }
            </script>

            <a href="#" class="forgot-link">Forgot Password?</a>

            <button type="submit" class="login-btn">Sign In</button>
        </form>

        <p class="signup-text">
            New to FitGym?
            <a href="signup.php">Create an account</a>
        </p>
    </div>

</main>

<?php
include("../includes/footer.php");
?>
