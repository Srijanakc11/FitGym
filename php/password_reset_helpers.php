<?php
require_once __DIR__ . '/auth_common.php';
require_once __DIR__ . '/dynamic_content.php';

if (!function_exists('fitgym_password_reset_token_lifetime_minutes')) {
    function fitgym_password_reset_token_lifetime_minutes(): int
    {
        return 60;
    }
}

if (!function_exists('fitgym_bootstrap_password_reset_tokens')) {
    function fitgym_bootstrap_password_reset_tokens(): void
    {
        static $bootstrapped = false;
        global $conn;

        if ($bootstrapped || !isset($conn) || !($conn instanceof mysqli)) {
            return;
        }

        $conn->query(
            "CREATE TABLE IF NOT EXISTS password_reset_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                account_id INT NOT NULL,
                email VARCHAR(255) NOT NULL,
                token_hash CHAR(64) NOT NULL UNIQUE,
                requested_ip VARCHAR(64) NULL,
                user_agent VARCHAR(255) NULL,
                expires_at DATETIME NOT NULL,
                used_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_password_reset_account (account_id),
                KEY idx_password_reset_email (email),
                KEY idx_password_reset_expiry (expires_at),
                CONSTRAINT fk_password_reset_account
                    FOREIGN KEY (account_id) REFERENCES accounts(id)
                    ON DELETE CASCADE
            )"
        );

        $conn->query("DELETE FROM password_reset_tokens WHERE used_at IS NOT NULL OR expires_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $bootstrapped = true;
    }
}

if (!function_exists('fitgym_password_reset_generate_token')) {
    function fitgym_password_reset_generate_token(): string
    {
        try {
            return bin2hex(random_bytes(32));
        } catch (Throwable $throwable) {
            return hash('sha256', uniqid((string)mt_rand(), true));
        }
    }
}

if (!function_exists('fitgym_password_reset_hash_token')) {
    function fitgym_password_reset_hash_token(string $token): string
    {
        return hash('sha256', trim($token));
    }
}

if (!function_exists('fitgym_password_reset_validation_error')) {
    function fitgym_password_reset_validation_error(string $password, string $confirmPassword): string
    {
        if (trim($password) === '') {
            return 'Please enter a new password.';
        }
        if (strlen($password) < 8) {
            return 'Password must be at least 8 characters long.';
        }
        if ($password !== $confirmPassword) {
            return 'Password confirmation does not match.';
        }

        return '';
    }
}

if (!function_exists('fitgym_password_reset_bcc_addresses')) {
    function fitgym_password_reset_bcc_addresses(): array
    {
        $adminCopy = trim(fitgym_mail_setting('mail_admin_notification_email', ''));
        if ($adminCopy !== '' && filter_var($adminCopy, FILTER_VALIDATE_EMAIL)) {
            return [$adminCopy];
        }

        return [];
    }
}

if (!function_exists('fitgym_password_reset_request')) {
    function fitgym_password_reset_request(string $email): bool
    {
        global $conn;

        fitgym_bootstrap_password_reset_tokens();

        $email = trim($email);
        if (
            $email === ''
            || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || !fitgym_mail_is_configured()
            || !isset($conn)
            || !($conn instanceof mysqli)
        ) {
            return false;
        }

        $account = fitgym_get_account_by_email($email);
        if (!$account || (int)($account['active'] ?? 0) !== 1) {
            return false;
        }

        $token = fitgym_password_reset_generate_token();
        $tokenHash = fitgym_password_reset_hash_token($token);
        $lifetime = max(10, fitgym_password_reset_token_lifetime_minutes());
        $requestedIp = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64);
        $userAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

        $disableStmt = $conn->prepare(
            "UPDATE password_reset_tokens
             SET used_at = NOW()
             WHERE account_id = ?
               AND used_at IS NULL"
        );
        if ($disableStmt) {
            $accountId = (int)$account['id'];
            $disableStmt->bind_param('i', $accountId);
            $disableStmt->execute();
            $disableStmt->close();
        }

        $stmt = $conn->prepare(
            "INSERT INTO password_reset_tokens (account_id, email, token_hash, requested_ip, user_agent, expires_at)
             VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))"
        );
        if (!$stmt) {
            return false;
        }

        $accountId = (int)$account['id'];
        $stmt->bind_param('issssi', $accountId, $email, $tokenHash, $requestedIp, $userAgent, $lifetime);
        $inserted = $stmt->execute();
        $tokenId = (int)$stmt->insert_id;
        $stmt->close();

        if (!$inserted || $tokenId <= 0) {
            return false;
        }

        $resetUrl = fitgym_absolute_url('/php/reset_password.php?token=' . rawurlencode($token));
        $siteName = fitgym_setting('site_name', 'FitGym');
        $accountName = trim((string)($account['name'] ?? 'Member'));
        $html = fitgym_mail_wrap_html(
            'Reset your password',
            'We received a request to reset your FitGym password.',
            '<p style="margin:0 0 16px;line-height:1.7;">Hello ' . htmlspecialchars($accountName !== '' ? $accountName : 'Member', ENT_QUOTES, 'UTF-8') . ',</p>'
            . '<p style="margin:0 0 16px;line-height:1.7;color:#56453c;">Use the button below to choose a new password. This secure link expires in '
            . htmlspecialchars((string)$lifetime, ENT_QUOTES, 'UTF-8') . ' minutes.</p>'
            . '<p style="margin:0 0 18px;"><a href="' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;background:#ff6c1a;color:#fff;text-decoration:none;padding:12px 18px;border-radius:999px;font-weight:700;">Reset Password</a></p>'
            . '<p style="margin:0 0 12px;color:#56453c;line-height:1.7;">If the button does not work, copy this link into your browser:</p>'
            . '<p style="margin:0 0 16px;word-break:break-all;color:#415b9a;">' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p style="margin:0;color:#6d5c52;line-height:1.6;">If you did not request this reset, you can ignore this email and your password will stay unchanged.</p>'
        );

        $text = "Hello {$accountName},\n\n"
            . "We received a request to reset your {$siteName} password.\n"
            . "Use the link below to choose a new password. This link expires in {$lifetime} minutes.\n\n"
            . $resetUrl . "\n\n"
            . "If you did not request this reset, you can ignore this email.";

        $sendResult = fitgym_send_mail([
            'to' => [$email],
            'bcc' => fitgym_password_reset_bcc_addresses(),
            'subject' => $siteName . ' password reset link',
            'html' => $html,
            'text' => $text,
        ]);

        if (!$sendResult['ok']) {
            $deleteStmt = $conn->prepare("DELETE FROM password_reset_tokens WHERE id = ? LIMIT 1");
            if ($deleteStmt) {
                $deleteStmt->bind_param('i', $tokenId);
                $deleteStmt->execute();
                $deleteStmt->close();
            }
            return false;
        }

        return true;
    }
}

if (!function_exists('fitgym_get_password_reset_token_row')) {
    function fitgym_get_password_reset_token_row(string $rawToken): ?array
    {
        global $conn;

        fitgym_bootstrap_password_reset_tokens();

        $rawToken = trim($rawToken);
        if ($rawToken === '' || !isset($conn) || !($conn instanceof mysqli)) {
            return null;
        }

        $tokenHash = fitgym_password_reset_hash_token($rawToken);
        $stmt = $conn->prepare(
            "SELECT prt.*, a.name AS account_name, a.role AS account_role, a.active AS account_active
             FROM password_reset_tokens prt
             INNER JOIN accounts a ON a.id = prt.account_id
             WHERE prt.token_hash = ?
               AND prt.used_at IS NULL
               AND prt.expires_at >= NOW()
             LIMIT 1"
        );
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('s', $tokenHash);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row || (int)($row['account_active'] ?? 0) !== 1) {
            return null;
        }

        return $row;
    }
}

if (!function_exists('fitgym_password_reset_mark_used')) {
    function fitgym_password_reset_mark_used(int $tokenId, int $accountId): void
    {
        global $conn;

        if ($tokenId <= 0 || $accountId <= 0 || !isset($conn) || !($conn instanceof mysqli)) {
            return;
        }

        $stmt = $conn->prepare(
            "UPDATE password_reset_tokens
             SET used_at = NOW()
             WHERE (id = ? OR account_id = ?)
               AND used_at IS NULL"
        );
        if ($stmt) {
            $stmt->bind_param('ii', $tokenId, $accountId);
            $stmt->execute();
            $stmt->close();
        }
    }
}

if (!function_exists('fitgym_password_reset_apply')) {
    function fitgym_password_reset_apply(string $rawToken, string $password): bool
    {
        $tokenRow = fitgym_get_password_reset_token_row($rawToken);
        if (!$tokenRow) {
            return false;
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $updated = fitgym_update_account_password_hash((int)$tokenRow['account_id'], $passwordHash);
        if (!$updated) {
            return false;
        }

        fitgym_password_reset_mark_used((int)$tokenRow['id'], (int)$tokenRow['account_id']);
        return true;
    }
}
