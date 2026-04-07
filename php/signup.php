<?php
require_once __DIR__ . '/auth_common.php';
require_once __DIR__ . '/dynamic_content.php';

$error = "";
$success = "";
$nextPath = trim((string)($_GET['next'] ?? $_POST['next'] ?? ''));

if (!function_exists('fitgym_signup_redirect_target')) {
    function fitgym_signup_redirect_target(string $candidate): string
    {
        $candidate = trim($candidate);
        if ($candidate === '' || str_contains($candidate, "\r") || str_contains($candidate, "\n")) {
            return '';
        }

        $parts = parse_url($candidate);
        if ($parts === false) {
            return '';
        }
        if (isset($parts['scheme']) || isset($parts['host'])) {
            return '';
        }
        if (!str_starts_with($candidate, '/')) {
            return '';
        }

        return $candidate;
    }
}

$nextPath = fitgym_signup_redirect_target($nextPath);

if (fitgym_current_role() !== null) {
    fitgym_redirect($nextPath !== '' ? $nextPath : fitgym_url('/index.php'));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name     = trim($_POST["name"]);
    $email    = trim($_POST["email"]);
    $password = $_POST["password"];
    $phone    = trim($_POST["phone"]);
    $gender   = $_POST["gender"];

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $check = $conn->prepare("SELECT id FROM accounts WHERE email = ? LIMIT 1");
    $check->bind_param("s", $email);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $error = "Email already exists!";
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO accounts (role, name, email, login_code, password_hash, phone, gender, qualification_status, active, legacy_source, legacy_id)
             VALUES ('client', ?, ?, ?, ?, ?, ?, 'verified', 1, NULL, NULL)"
        );
        $stmt->bind_param(
            "ssssss",
            $name,
            $email,
            $email,
            $hashedPassword,
            $phone,
            $gender
        );

        if ($stmt->execute()) {
            $accountId = (int)$stmt->insert_id;
            fitgym_login_user('client', $accountId, $name, $email);
            fitgym_redirect($nextPath !== '' ? $nextPath : fitgym_url('/index.php'));
        } else {
            $error = "Something went wrong. Try again.";
        }
        $stmt->close();
    }
    $check->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Account | FitGym</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="icon" type="image/png" href="<?= fitgym_esc(fitgym_asset_url('/pictures/favicon.png')) ?>">


    <link rel="stylesheet" href="<?= fitgym_esc(fitgym_asset_url('/css/signup.css')) ?>">
    <link rel="stylesheet" href="<?= fitgym_esc(fitgym_asset_url('/css/header.css')) ?>">
    <link rel="stylesheet" href="<?= fitgym_esc(fitgym_asset_url('/css/footer.css')) ?>">
    <link rel="stylesheet" href="<?= fitgym_esc(fitgym_asset_url('/css/index.css')) ?>">
</head>

<body>


<?php include "header.php"; ?>

<main class="page-content">
    <div class="signup-buffer"></div>

    <div class="signup-wrapper">
        <h2>Create Account</h2>

        <?php if ($error): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success"><?= $success ?></div>
        <?php endif; ?>

        <form method="POST" action="<?= fitgym_esc(fitgym_url('/php/signup.php')) ?>" class="signup-form">
            <?php if ($nextPath !== ''): ?>
                <input type="hidden" name="next" value="<?= fitgym_esc($nextPath) ?>">
            <?php endif; ?>

            <div class="input-group">
                <label>Name</label>
                <input type="text" name="name" placeholder="John Doe" required>
            </div>

            <div class="input-group">
                <label>Email</label>
                <input type="email" name="email" placeholder="example@fitgym.com" required>
            </div>

            <div class="input-group password-group">
                <label>Password</label>
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

            <div class="input-group">
                <label>Phone</label>
                <input type="text" name="phone" placeholder="+1 234 567 890">
            </div>

            <div class="input-group">
                <label>Gender</label>
                <select name="gender">
                    <option value="">Select Gender</option>
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                    <option value="other">Other</option>
                </select>
            </div>

            <button type="submit" class="signup-btn">Create Account</button>
        </form>

        <p class="login-text">
            Already have an account?
            <a href="<?= fitgym_esc(fitgym_url('/php/login.php') . ($nextPath !== '' ? '?next=' . rawurlencode($nextPath) : '')) ?>">Login</a>
        </p>
    </div>
</main>

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


<?php include "footer.php"; ?>

</body>
</html>
