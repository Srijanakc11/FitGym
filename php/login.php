<?php
require_once __DIR__ . '/auth_common.php';
require_once __DIR__ . "/trainer_bootstrap.php";

$loginError = "";
$nextPath = trim((string)($_GET['next'] ?? $_POST['next'] ?? ''));

if (!function_exists('fitgym_login_redirect_target')) {
    function fitgym_login_redirect_target(string $candidate): string
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

$nextPath = fitgym_login_redirect_target($nextPath);

if (fitgym_current_role() !== null) {
    fitgym_redirect(fitgym_post_login_target(fitgym_current_role(), $nextPath));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($db_error) && $conn) {
    $identifier = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $account = fitgym_get_account_by_login($identifier);

    if (!$account || (int)($account['active'] ?? 0) !== 1) {
        $loginError = "Invalid credentials or account disabled.";
    } elseif (($account['role'] ?? '') === 'trainer' && ($account['qualification_status'] ?? 'pending') !== 'verified') {
        $loginError = "Trainer account is not verified yet.";
    } elseif (!password_verify($password, (string)($account['password_hash'] ?? ''))) {
        $loginError = "Invalid credentials or account disabled.";
    } else {
        $role = (string)($account['role'] ?? 'client');
        $email = (string)($account['email'] ?? '');
        fitgym_login_user($role, (int)$account['id'], (string)$account['name'], $email);
        fitgym_update_account_login_timestamp((int)$account['id']);
        fitgym_redirect(fitgym_post_login_target($role, $nextPath));
    }
}

include __DIR__ . "/header.php";
?>

<link rel="stylesheet" href="<?= fitgym_esc(fitgym_asset_url('/css/login.css')) ?>">
<link rel="stylesheet" href="<?= fitgym_esc(fitgym_asset_url('/css/header.css')) ?>">
<link rel="stylesheet" href="<?= fitgym_esc(fitgym_asset_url('/css/footer.css')) ?>">
<link rel="stylesheet" href="<?= fitgym_esc(fitgym_asset_url('/css/index.css')) ?>">

<main class="page-content">

    <div class="login-container">
        <h2 class="login-title">Login</h2>

        <?php if (!empty($loginError)): ?>
            <div class="error-msg">
                <?= htmlspecialchars($loginError) ?>
            </div>
        <?php endif; ?>

        <form action="<?= fitgym_esc(fitgym_url('/php/login.php')) ?>" method="POST" class="simple-login-form">
            <?php if ($nextPath !== ''): ?>
                <input type="hidden" name="next" value="<?= fitgym_esc($nextPath) ?>">
            <?php endif; ?>
            
            <div class="form-group">
                <label for="email">Email or Trainer ID</label>
                <input type="text" id="email" name="email" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-field">
                    <input type="password" id="password" name="password" required>
                    <button type="button" class="password-toggle" id="password-toggle" aria-label="Show password" aria-controls="password" aria-pressed="false">
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path d="M12 5C6 5 2.2 10.1 2 10.4a1 1 0 0 0 0 1.2C2.2 11.9 6 17 12 17s9.8-5.1 10-5.4a1 1 0 0 0 0-1.2C21.8 10.1 18 5 12 5Zm0 10c-4.1 0-7.1-3.1-7.9-4 .8-.9 3.8-4 7.9-4 4.1 0 7.1 3.1 7.9 4-.8.9-3.8 4-7.9 4Zm0-6a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z"></path>
                        </svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-submit">Login</button>
            
            <div class="form-footer">
                <a href="<?= fitgym_esc(fitgym_url('/php/forgot_password.php')) ?>">Forgot Password?</a>
            </div>
        </form>

        <p class="signup-link">
            New here? <a href="<?= fitgym_esc(fitgym_url('/php/signup.php') . ($nextPath !== '' ? '?next=' . rawurlencode($nextPath) : '')) ?>">Create an account</a>
        </p>
    </div>

</main>

<script>
    (function () {
        const passwordInput = document.getElementById('password');
        const toggleBtn = document.getElementById('password-toggle');
        if (!passwordInput || !toggleBtn) return;

        toggleBtn.addEventListener('click', function () {
            const show = passwordInput.type === 'password';
            passwordInput.type = show ? 'text' : 'password';
            toggleBtn.setAttribute('aria-pressed', String(show));
            toggleBtn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            toggleBtn.classList.toggle('is-visible', show);
        });
    })();
</script>

<?php include __DIR__ . "/footer.php"; ?>
