<?php
require_once __DIR__ . '/password_reset_helpers.php';

$error = '';
$success = '';
$email = trim((string)($_POST['email'] ?? ''));

if (fitgym_current_role() !== null) {
    fitgym_redirect(fitgym_url('/index.php'));
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!fitgym_mail_is_configured()) {
        $error = 'Password reset email is not configured yet. Add the SMTP app password in Admin > Settings > Email Settings.';
    } else {
        fitgym_password_reset_request($email);
        $success = 'If an active account exists for that email, a password reset link has been sent.';
        $email = '';
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
        <h2 class="login-title">Forgot Password</h2>
        <p class="auth-copy">Enter your account email and we will send you a secure link to set a new password.</p>

        <?php if ($error !== ''): ?>
            <div class="error-msg"><?= fitgym_esc($error) ?></div>
        <?php endif; ?>

        <?php if ($success !== ''): ?>
            <div class="success-msg"><?= fitgym_esc($success) ?></div>
        <?php endif; ?>

        <form action="<?= fitgym_esc(fitgym_url('/php/forgot_password.php')) ?>" method="POST" class="simple-login-form">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" value="<?= fitgym_esc($email) ?>" required>
            </div>

            <button type="submit" class="btn-submit">Send Reset Link</button>

            <div class="form-footer">
                <a href="<?= fitgym_esc(fitgym_url('/php/login.php')) ?>">Back to Login</a>
            </div>
        </form>

        <p class="signup-link">
            Need an account? <a href="<?= fitgym_esc(fitgym_url('/php/signup.php')) ?>">Create one</a>
        </p>
    </div>
</main>

<?php include __DIR__ . '/footer.php'; ?>
