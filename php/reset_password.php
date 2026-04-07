<?php
require_once __DIR__ . '/password_reset_helpers.php';

$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$error = '';
$success = '';
$password = '';
$confirmPassword = '';
$tokenRow = $token !== '' ? fitgym_get_password_reset_token_row($token) : null;

if (fitgym_current_role() !== null) {
    fitgym_redirect(fitgym_url('/index.php'));
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $password = (string)($_POST['password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if (!$tokenRow) {
        $error = 'This password reset link is invalid or has expired.';
    } else {
        $validationError = fitgym_password_reset_validation_error($password, $confirmPassword);
        if ($validationError !== '') {
            $error = $validationError;
        } elseif (fitgym_password_reset_apply($token, $password)) {
            $role = (string)($tokenRow['account_role'] ?? 'client');
            $accountId = (int)($tokenRow['account_id'] ?? 0);
            $accountName = (string)($tokenRow['account_name'] ?? 'Member');
            $accountEmail = (string)($tokenRow['email'] ?? '');
            fitgym_login_user($role, $accountId, $accountName, $accountEmail);
            fitgym_update_account_login_timestamp($accountId);
            fitgym_redirect('/index.php');
        } else {
            $error = 'We could not update the password with this reset link. Please request a new one.';
        }
    }
}

include __DIR__ . '/header.php';
?>

<link rel="stylesheet" href="<?= fitgym_esc(fitgym_asset_url('/css/login.css')) ?>">
<link rel="stylesheet" href="<?= fitgym_esc(fitgym_asset_url('/css/header.css')) ?>">
<link rel="stylesheet" href="<?= fitgym_esc(fitgym_asset_url('/css/footer.css')) ?>">
<link rel="stylesheet" href="<?= fitgym_esc(fitgym_asset_url('/css/index.css')) ?>">

<main class="page-content">
    <div class="login-container">
        <h2 class="login-title">Reset Password</h2>

        <?php if ($tokenRow): ?>
            <p class="auth-copy">Choose a new password for <?= fitgym_esc((string)($tokenRow['email'] ?? 'your account')) ?>. This link expires in <?= (int)fitgym_password_reset_token_lifetime_minutes() ?> minutes from the time it was requested.</p>
        <?php elseif ($success === ''): ?>
            <p class="auth-copy">This reset link is no longer valid. Request a fresh password reset email to continue.</p>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="error-msg"><?= fitgym_esc($error) ?></div>
        <?php endif; ?>

        <?php if ($success !== ''): ?>
            <div class="success-msg"><?= fitgym_esc($success) ?></div>
        <?php endif; ?>

        <?php if ($tokenRow): ?>
            <form action="<?= fitgym_esc(fitgym_url('/php/reset_password.php')) ?>" method="POST" class="simple-login-form">
                <input type="hidden" name="token" value="<?= fitgym_esc($token) ?>">

                <div class="form-group">
                    <label for="password">New Password</label>
                    <div class="password-field">
                        <input type="password" id="password" name="password" value="<?= fitgym_esc($password) ?>" required>
                        <button type="button" class="password-toggle" data-password-toggle="password" aria-label="Show password" aria-controls="password" aria-pressed="false">
                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path d="M12 5C6 5 2.2 10.1 2 10.4a1 1 0 0 0 0 1.2C2.2 11.9 6 17 12 17s9.8-5.1 10-5.4a1 1 0 0 0 0-1.2C21.8 10.1 18 5 12 5Zm0 10c-4.1 0-7.1-3.1-7.9-4 .8-.9 3.8-4 7.9-4 4.1 0 7.1 3.1 7.9 4-.8.9-3.8 4-7.9 4Zm0-6a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <div class="password-field">
                        <input type="password" id="confirm_password" name="confirm_password" value="<?= fitgym_esc($confirmPassword) ?>" required>
                        <button type="button" class="password-toggle" data-password-toggle="confirm_password" aria-label="Show password" aria-controls="confirm_password" aria-pressed="false">
                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path d="M12 5C6 5 2.2 10.1 2 10.4a1 1 0 0 0 0 1.2C2.2 11.9 6 17 12 17s9.8-5.1 10-5.4a1 1 0 0 0 0-1.2C21.8 10.1 18 5 12 5Zm0 10c-4.1 0-7.1-3.1-7.9-4 .8-.9 3.8-4 7.9-4 4.1 0 7.1 3.1 7.9 4-.8.9-3.8 4-7.9 4Zm0-6a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <p class="auth-note">Password must be at least 8 characters long.</p>
                <button type="submit" class="btn-submit">Update Password</button>

                <div class="form-footer">
                    <a href="<?= fitgym_esc(fitgym_url('/php/login.php')) ?>">Back to Login</a>
                </div>
            </form>
        <?php else: ?>
            <div class="form-footer auth-center-link">
                <a href="<?= fitgym_esc(fitgym_url('/php/forgot_password.php')) ?>">Request a new reset link</a>
            </div>
            <div class="form-footer auth-center-link">
                <a href="<?= fitgym_esc(fitgym_url('/php/login.php')) ?>">Return to login</a>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
(() => {
    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const targetId = button.getAttribute('data-password-toggle');
            const input = targetId ? document.getElementById(targetId) : null;
            if (!input) {
                return;
            }

            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.setAttribute('aria-pressed', String(show));
            button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            button.classList.toggle('is-visible', show);
        });
    });
})();
</script>

<?php include __DIR__ . '/footer.php'; ?>
