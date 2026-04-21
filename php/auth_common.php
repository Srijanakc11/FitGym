<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/database.php';

if (!function_exists('fitgym_base_url')) {
    function fitgym_base_url(): string
    {
        $scriptDir = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '')));
        $baseUrl = preg_replace('#/php(?:/admin|/client|/trainer)?/?$#', '', $scriptDir);
        $baseUrl = $baseUrl === null ? '' : rtrim($baseUrl, '/');
        return ($baseUrl === '.' || $baseUrl === '/') ? '' : $baseUrl;
    }
}

if (!function_exists('fitgym_url')) {
    function fitgym_url(string $path = ''): string
    {
        $path = '/' . ltrim($path, '/');
        return fitgym_base_url() . $path;
    }
}

if (!function_exists('fitgym_asset_url')) {
    function fitgym_asset_url(string $path = ''): string
    {
        $path = '/' . ltrim($path, '/');
        $url = fitgym_url($path);
        $filePath = dirname(__DIR__) . str_replace('/', DIRECTORY_SEPARATOR, $path);

        if (!is_file($filePath)) {
            return $url;
        }

        $version = filemtime($filePath);
        if ($version === false) {
            return $url;
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . 'v=' . rawurlencode((string)$version);
    }
}

if (!function_exists('fitgym_redirect')) {
    function fitgym_redirect(string $path): void
    {
        $location = str_starts_with($path, '/') ? fitgym_url($path) : $path;
        if (str_starts_with($path, fitgym_base_url() . '/')) {
            $location = $path;
        }
        header('Location: ' . $location);
        exit;
    }
}

if (!function_exists('fitgym_table_exists')) {
    function fitgym_table_exists(string $tableName): bool
    {
        global $conn;

        if (!isset($conn) || !($conn instanceof mysqli)) {
            return false;
        }

        $safeTableName = $conn->real_escape_string($tableName);
        $result = $conn->query("SHOW TABLES LIKE '{$safeTableName}'");
        if (!$result) {
            return false;
        }

        $exists = $result->num_rows > 0;
        $result->free();
        return $exists;
    }
}

if (!function_exists('fitgym_bootstrap_fitness_profile_table')) {
    function fitgym_bootstrap_fitness_profile_table(): void
    {
        global $conn;

        if (!isset($conn) || !($conn instanceof mysqli)) {
            return;
        }

        $conn->query(
            "CREATE TABLE IF NOT EXISTS user_fitness_profiles (
                account_id INT PRIMARY KEY,
                age INT NULL,
                gender VARCHAR(10) NULL,
                height_cm FLOAT NULL,
                weight_kg FLOAT NULL,
                activity VARCHAR(20) NULL,
                goal VARCHAR(30) NULL,
                training_days_per_week INT NULL,
                fitness_level VARCHAR(20) NULL,
                joint_pain VARCHAR(5) NULL,
                duration_preference INT NULL,
                profile_completed TINYINT(1) NOT NULL DEFAULT 0,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )"
        );
    }
}

if (!function_exists('fitgym_bootstrap_accounts')) {
    function fitgym_bootstrap_accounts(): void
    {
        global $conn;

        if (!isset($conn) || !($conn instanceof mysqli)) {
            return;
        }

        $conn->query(
            "CREATE TABLE IF NOT EXISTS accounts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                role VARCHAR(20) NOT NULL,
                name VARCHAR(120) NOT NULL,
                email VARCHAR(255) NULL UNIQUE,
                login_code VARCHAR(120) NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                phone VARCHAR(50) NULL,
                gender VARCHAR(25) NULL,
                specialization VARCHAR(150) NULL,
                experience_years INT DEFAULT 0,
                image_path VARCHAR(255) NULL,
                availability VARCHAR(255) NULL,
                qualification TEXT NULL,
                qualification_status VARCHAR(20) DEFAULT 'pending',
                verified_by_account_id INT NULL,
                verified_at TIMESTAMP NULL,
                last_login_at TIMESTAMP NULL,
                active TINYINT(1) DEFAULT 1,
                legacy_source VARCHAR(20) NULL,
                legacy_id INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_legacy_source_id (legacy_source, legacy_id)
            )"
        );

        $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS role VARCHAR(20) NOT NULL DEFAULT 'client'");
        $conn->query("ALTER TABLE trainers ADD COLUMN IF NOT EXISTS role VARCHAR(20) NOT NULL DEFAULT 'trainer'");
        $conn->query("ALTER TABLE classes_admin ADD COLUMN IF NOT EXISTS trainer_account_id INT NULL");
        $conn->query("ALTER TABLE notifications ADD COLUMN IF NOT EXISTS notification_type VARCHAR(30) NOT NULL DEFAULT 'announcement'");
        $conn->query("ALTER TABLE notifications ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'sent'");
        $conn->query("ALTER TABLE notifications ADD COLUMN IF NOT EXISTS target_role VARCHAR(20) NULL");
        $conn->query("ALTER TABLE notifications ADD COLUMN IF NOT EXISTS target_account_id INT NULL");
        $conn->query("ALTER TABLE notifications ADD COLUMN IF NOT EXISTS send_at DATETIME NULL");
        $conn->query("ALTER TABLE notifications ADD COLUMN IF NOT EXISTS sent_by_account_id INT NULL");

        fitgym_sync_accounts_from_admins();
        fitgym_sync_accounts_from_users();
        fitgym_sync_accounts_from_trainers();
        fitgym_backfill_class_trainer_accounts();
        fitgym_ensure_default_admin_account();
        fitgym_bootstrap_fitness_profile_table();
    }
}

if (!function_exists('fitgym_ensure_default_admin_account')) {
    function fitgym_ensure_default_admin_account(): void
    {
        global $conn;

        $result = $conn->query("SELECT id FROM accounts WHERE role = 'admin' LIMIT 1");
        if ($result && $result->num_rows > 0) {
            $result->free();
            return;
        }
        if ($result) {
            $result->free();
        }

        $passwordHash = password_hash('admin123', PASSWORD_DEFAULT);
        $email = 'admin@fitgym.com';
        $stmt = $conn->prepare(
            "INSERT INTO accounts (role, name, email, login_code, password_hash, qualification_status, active, legacy_source, legacy_id)
             VALUES ('admin', 'Admin', ?, ?, ?, 'verified', 1, NULL, NULL)"
        );
        if ($stmt) {
            $stmt->bind_param('sss', $email, $email, $passwordHash);
            $stmt->execute();
            $stmt->close();
        }
    }
}

if (!function_exists('fitgym_sync_accounts_from_admins')) {
    function fitgym_sync_accounts_from_admins(): void
    {
        global $conn;

        if (!fitgym_table_exists('admin_users')) {
            return;
        }

        $result = $conn->query("SELECT id, name, email, password_hash, active, created_at FROM admin_users");
        if (!$result) {
            return;
        }

        $stmt = $conn->prepare(
            "INSERT INTO accounts
            (role, name, email, login_code, password_hash, active, qualification_status, legacy_source, legacy_id, created_at)
            VALUES ('admin', ?, ?, ?, ?, ?, 'verified', 'admin_users', ?, ?)
            ON DUPLICATE KEY UPDATE
                role = VALUES(role),
                name = VALUES(name),
                email = VALUES(email),
                login_code = VALUES(login_code),
                password_hash = VALUES(password_hash),
                active = VALUES(active)"
        );
        if (!$stmt) {
            $result->free();
            return;
        }

        while ($row = $result->fetch_assoc()) {
            $email = (string)$row['email'];
            $stmt->bind_param(
                'sssssis',
                $row['name'],
                $email,
                $email,
                $row['password_hash'],
                $row['active'],
                $row['id'],
                $row['created_at']
            );
            $stmt->execute();
        }

        $stmt->close();
        $result->free();
    }
}

if (!function_exists('fitgym_sync_accounts_from_users')) {
    function fitgym_sync_accounts_from_users(): void
    {
        global $conn;

        if (!fitgym_table_exists('users')) {
            return;
        }

        $result = $conn->query("SELECT id, name, email, password, phone, gender, active, created_at FROM users");
        if (!$result) {
            return;
        }

        $stmt = $conn->prepare(
            "INSERT INTO accounts
            (role, name, email, login_code, password_hash, phone, gender, active, qualification_status, legacy_source, legacy_id, created_at)
            VALUES ('client', ?, ?, ?, ?, ?, ?, ?, 'verified', 'users', ?, ?)
            ON DUPLICATE KEY UPDATE
                role = VALUES(role),
                name = VALUES(name),
                email = VALUES(email),
                login_code = VALUES(login_code),
                password_hash = VALUES(password_hash),
                phone = VALUES(phone),
                gender = VALUES(gender),
                active = VALUES(active)"
        );
        if (!$stmt) {
            $result->free();
            return;
        }

        while ($row = $result->fetch_assoc()) {
            $email = (string)$row['email'];
            $stmt->bind_param(
                'sssssssis',
                $row['name'],
                $email,
                $email,
                $row['password'],
                $row['phone'],
                $row['gender'],
                $row['active'],
                $row['id'],
                $row['created_at']
            );
            $stmt->execute();
        }

        $stmt->close();
        $result->free();
    }
}

if (!function_exists('fitgym_sync_accounts_from_trainers')) {
    function fitgym_sync_accounts_from_trainers(): void
    {
        global $conn;

        if (!fitgym_table_exists('trainers')) {
            return;
        }

        $result = $conn->query(
            "SELECT id, name, specialization, experience_years, image_path, availability, trainer_code, password_hash,
                    qualification, qualification_status, verified_by_admin_id, verified_at, last_login_at, active, created_at
             FROM trainers"
        );
        if (!$result) {
            return;
        }

        $stmt = $conn->prepare(
            "INSERT INTO accounts
            (role, name, email, login_code, password_hash, specialization, experience_years, image_path, availability,
             qualification, qualification_status, verified_by_account_id, verified_at, last_login_at, active, legacy_source, legacy_id, created_at)
            VALUES ('trainer', ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'trainers', ?, ?)
            ON DUPLICATE KEY UPDATE
                role = VALUES(role),
                name = VALUES(name),
                login_code = VALUES(login_code),
                password_hash = VALUES(password_hash),
                specialization = VALUES(specialization),
                experience_years = VALUES(experience_years),
                image_path = VALUES(image_path),
                availability = VALUES(availability),
                qualification = VALUES(qualification),
                qualification_status = VALUES(qualification_status),
                verified_at = VALUES(verified_at),
                last_login_at = VALUES(last_login_at),
                active = VALUES(active)"
        );
        if (!$stmt) {
            $result->free();
            return;
        }

        while ($row = $result->fetch_assoc()) {
            $verifiedByAccountId = fitgym_find_account_id_by_legacy('admin_users', (int)($row['verified_by_admin_id'] ?? 0));
            $stmt->bind_param(
                'ssssisssssisssis',
                $row['name'],
                $row['trainer_code'],
                $row['password_hash'],
                $row['specialization'],
                $row['experience_years'],
                $row['image_path'],
                $row['availability'],
                $row['qualification'],
                $row['qualification_status'],
                $verifiedByAccountId,
                $row['verified_at'],
                $row['last_login_at'],
                $row['active'],
                $row['id'],
                $row['created_at']
            );
            $stmt->execute();
        }

        $stmt->close();
        $result->free();
    }
}

if (!function_exists('fitgym_backfill_class_trainer_accounts')) {
    function fitgym_backfill_class_trainer_accounts(): void
    {
        global $conn;

        if (!fitgym_table_exists('classes_admin')) {
            return;
        }

        $result = $conn->query("SELECT id, trainer_id, trainer_account_id FROM classes_admin");
        if (!$result) {
            return;
        }

        $stmt = $conn->prepare("UPDATE classes_admin SET trainer_account_id = ? WHERE id = ?");
        if (!$stmt) {
            $result->free();
            return;
        }

        while ($row = $result->fetch_assoc()) {
            if ((int)($row['trainer_account_id'] ?? 0) > 0) {
                continue;
            }
            $accountId = fitgym_find_account_id_by_legacy('trainers', (int)($row['trainer_id'] ?? 0));
            if ($accountId <= 0) {
                continue;
            }
            $classId = (int)$row['id'];
            $stmt->bind_param('ii', $accountId, $classId);
            $stmt->execute();
        }

        $stmt->close();
        $result->free();
    }
}

if (!function_exists('fitgym_find_account_id_by_legacy')) {
    function fitgym_find_account_id_by_legacy(string $source, int $legacyId): int
    {
        global $conn;

        if ($legacyId <= 0 || !isset($conn) || !($conn instanceof mysqli)) {
            return 0;
        }

        $stmt = $conn->prepare("SELECT id FROM accounts WHERE legacy_source = ? AND legacy_id = ? LIMIT 1");
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('si', $source, $legacyId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int)($row['id'] ?? 0);
    }
}

if (!function_exists('fitgym_get_account_by_login')) {
    function fitgym_get_account_by_login(string $identifier): ?array
    {
        global $conn;

        if (!isset($conn) || !($conn instanceof mysqli)) {
            return null;
        }

        $stmt = $conn->prepare(
            "SELECT * FROM accounts
             WHERE email = ? OR login_code = ?
             ORDER BY FIELD(role, 'admin', 'trainer', 'client')
             LIMIT 1"
        );
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('ss', $identifier, $identifier);
        $stmt->execute();
        $account = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $account ?: null;
    }
}

if (!function_exists('fitgym_get_account_by_email')) {
    function fitgym_get_account_by_email(string $email): ?array
    {
        global $conn;

        $email = trim($email);
        if ($email === '' || !isset($conn) || !($conn instanceof mysqli)) {
            return null;
        }

        $stmt = $conn->prepare(
            "SELECT * FROM accounts
             WHERE email = ?
             ORDER BY FIELD(role, 'admin', 'trainer', 'client')
             LIMIT 1"
        );
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('s', $email);
        $stmt->execute();
        $account = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $account ?: null;
    }
}

if (!function_exists('fitgym_get_account_by_id')) {
    function fitgym_get_account_by_id(int $accountId): ?array
    {
        global $conn;

        if ($accountId <= 0 || !isset($conn) || !($conn instanceof mysqli)) {
            return null;
        }

        $stmt = $conn->prepare("SELECT * FROM accounts WHERE id = ? LIMIT 1");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $accountId);
        $stmt->execute();
        $account = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $account ?: null;
    }
}

if (!function_exists('fitgym_update_account_password_hash')) {
    function fitgym_update_account_password_hash(int $accountId, string $passwordHash): bool
    {
        global $conn;

        if ($accountId <= 0 || trim($passwordHash) === '' || !isset($conn) || !($conn instanceof mysqli)) {
            return false;
        }

        $account = fitgym_get_account_by_id($accountId);
        if (!$account) {
            return false;
        }

        $ok = true;
        $stmt = $conn->prepare("UPDATE accounts SET password_hash = ? WHERE id = ?");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('si', $passwordHash, $accountId);
        $ok = $stmt->execute() && $ok;
        $stmt->close();

        $legacySource = (string)($account['legacy_source'] ?? '');
        $legacyId = (int)($account['legacy_id'] ?? 0);

        if ($legacyId > 0 && $legacySource !== '') {
            $legacyUpdateMap = [
                'users' => ['table' => 'users', 'column' => 'password'],
                'trainers' => ['table' => 'trainers', 'column' => 'password_hash'],
                'admin_users' => ['table' => 'admin_users', 'column' => 'password_hash'],
            ];

            if (isset($legacyUpdateMap[$legacySource])) {
                $legacyTable = (string)$legacyUpdateMap[$legacySource]['table'];
                $legacyColumn = (string)$legacyUpdateMap[$legacySource]['column'];

                if (fitgym_table_exists($legacyTable)) {
                    $legacyStmt = $conn->prepare("UPDATE {$legacyTable} SET {$legacyColumn} = ? WHERE id = ?");
                    if ($legacyStmt) {
                        $legacyStmt->bind_param('si', $passwordHash, $legacyId);
                        $ok = $legacyStmt->execute() && $ok;
                        $legacyStmt->close();
                    } else {
                        $ok = false;
                    }
                }
            }
        }

        return $ok;
    }
}

if (!function_exists('fitgym_update_account_login_timestamp')) {
    function fitgym_update_account_login_timestamp(int $accountId): void
    {
        global $conn;

        if ($accountId <= 0 || !isset($conn) || !($conn instanceof mysqli)) {
            return;
        }

        $stmt = $conn->prepare("UPDATE accounts SET last_login_at = NOW() WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $accountId);
            $stmt->execute();
            $stmt->close();
        }
    }
}

fitgym_bootstrap_accounts();

if (!function_exists('fitgym_clear_auth_session')) {
    function fitgym_clear_auth_session(): void
    {
        unset(
            $_SESSION['auth_role'],
            $_SESSION['auth_id'],
            $_SESSION['auth_name'],
            $_SESSION['auth_email'],
            $_SESSION['admin_id'],
            $_SESSION['admin_name'],
            $_SESSION['trainer_id'],
            $_SESSION['trainer_name'],
            $_SESSION['user_id'],
            $_SESSION['user_name'],
            $_SESSION['user_email']
        );
    }
}

if (!function_exists('fitgym_login_user')) {
    function fitgym_login_user(string $role, int $id, string $name, string $email = ''): void
    {
        fitgym_clear_auth_session();

        $_SESSION['auth_role'] = $role;
        $_SESSION['auth_id'] = $id;
        $_SESSION['auth_name'] = $name;
        $_SESSION['auth_email'] = $email;

        if ($role === 'admin') {
            $_SESSION['admin_id'] = $id;
            $_SESSION['admin_name'] = $name;
            return;
        }

        if ($role === 'trainer') {
            $_SESSION['trainer_id'] = $id;
            $_SESSION['trainer_name'] = $name;
            return;
        }

        $_SESSION['user_id'] = $id;
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;
    }
}

if (!function_exists('fitgym_current_role')) {
    function fitgym_current_role(): ?string
    {
        $role = $_SESSION['auth_role'] ?? null;
        return is_string($role) && $role !== '' ? $role : null;
    }
}

if (!function_exists('fitgym_normalize_internal_path')) {
    function fitgym_normalize_internal_path(string $path): string
    {
        $candidate = trim($path);
        if ($candidate === '') {
            return '';
        }

        $parsedPath = parse_url($candidate, PHP_URL_PATH);
        if (!is_string($parsedPath) || $parsedPath === '') {
            return '';
        }

        $normalizedPath = $parsedPath;
        $baseUrl = fitgym_base_url();
        if ($baseUrl !== '' && $normalizedPath === $baseUrl) {
            return '/';
        }
        if ($baseUrl !== '' && str_starts_with($normalizedPath, $baseUrl . '/')) {
            $normalizedPath = substr($normalizedPath, strlen($baseUrl));
        }

        return str_starts_with($normalizedPath, '/') ? $normalizedPath : '/' . $normalizedPath;
    }
}

if (!function_exists('fitgym_path_has_prefix')) {
    function fitgym_path_has_prefix(string $path, string $prefix): bool
    {
        $prefix = '/' . trim($prefix, '/');
        return $path === $prefix || str_starts_with($path, $prefix . '/');
    }
}

if (!function_exists('fitgym_role_home')) {
    function fitgym_role_home(?string $role = null): string
    {
        $role = $role ?? fitgym_current_role();
        if ($role === 'admin') {
            return fitgym_url('/php/admin/index.php');
        }
        if ($role === 'trainer') {
            return fitgym_url('/php/trainer/dashboard.php');
        }
        return fitgym_url('/php/client/dashboard.php');
    }
}

if (!function_exists('fitgym_role_allows_path')) {
    function fitgym_role_allows_path(?string $role, string $path): bool
    {
        $normalizedPath = fitgym_normalize_internal_path($path);
        if ($normalizedPath === '') {
            return false;
        }

        if ($role === 'admin') {
            return fitgym_path_has_prefix($normalizedPath, '/php/admin');
        }

        if ($role === 'trainer') {
            return fitgym_path_has_prefix($normalizedPath, '/php/trainer');
        }

        if ($role === 'client') {
            return !fitgym_path_has_prefix($normalizedPath, '/php/admin')
                && !fitgym_path_has_prefix($normalizedPath, '/php/trainer');
        }

        return false;
    }
}

if (!function_exists('fitgym_post_login_target')) {
    function fitgym_post_login_target(?string $role = null, string $candidate = ''): string
    {
        $role = $role ?? fitgym_current_role();
        if ($candidate !== '' && fitgym_role_allows_path($role, $candidate)) {
            return $candidate;
        }

        return fitgym_role_home($role);
    }
}

if (!function_exists('fitgym_redirect_authenticated_user')) {
    function fitgym_redirect_authenticated_user(): void
    {
        if (fitgym_current_role() !== null) {
            fitgym_redirect(fitgym_post_login_target());
        }
    }
}

if (!function_exists('fitgym_require_role')) {
    function fitgym_require_role(string $role): void
    {
        $currentRole = fitgym_current_role();
        if ($currentRole === $role) {
            return;
        }

        if ($currentRole !== null) {
            fitgym_redirect(fitgym_role_home($currentRole));
        }

        fitgym_redirect('/php/login.php');
    }
}

if (!function_exists('fitgym_get_user_fitness_profile')) {
    function fitgym_get_user_fitness_profile(int $accountId): ?array
    {
        global $conn;

        if ($accountId <= 0 || !isset($conn) || !($conn instanceof mysqli)) {
            return null;
        }

        $stmt = $conn->prepare(
            "SELECT * FROM user_fitness_profiles WHERE account_id = ? LIMIT 1"
        );
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $accountId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ?: null;
    }
}

if (!function_exists('fitgym_save_user_fitness_profile')) {
    function fitgym_save_user_fitness_profile(int $accountId, array $data): bool
    {
        global $conn;

        if ($accountId <= 0 || !isset($conn) || !($conn instanceof mysqli)) {
            return false;
        }

        $age                   = isset($data['age']) && $data['age'] !== '' ? (int)$data['age'] : null;
        $gender                = isset($data['gender']) ? trim((string)$data['gender']) : null;
        $heightCm              = isset($data['height_cm']) && $data['height_cm'] !== '' ? (float)$data['height_cm'] : null;
        $weightKg              = isset($data['weight_kg']) && $data['weight_kg'] !== '' ? (float)$data['weight_kg'] : null;
        $activity              = isset($data['activity']) ? trim((string)$data['activity']) : null;
        $goal                  = isset($data['goal']) ? trim((string)$data['goal']) : null;
        $trainingDays          = isset($data['training_days_per_week']) && $data['training_days_per_week'] !== '' ? (int)$data['training_days_per_week'] : null;
        $fitnessLevel          = isset($data['fitness_level']) ? trim((string)$data['fitness_level']) : null;
        $jointPain             = isset($data['joint_pain']) ? trim((string)$data['joint_pain']) : null;
        $durationPreference    = isset($data['duration_preference']) && $data['duration_preference'] !== '' ? (int)$data['duration_preference'] : null;
        $profileCompleted      = 1;

        $stmt = $conn->prepare(
            "INSERT INTO user_fitness_profiles
                (account_id, age, gender, height_cm, weight_kg, activity, goal,
                 training_days_per_week, fitness_level, joint_pain, duration_preference, profile_completed)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE
                age = VALUES(age),
                gender = VALUES(gender),
                height_cm = VALUES(height_cm),
                weight_kg = VALUES(weight_kg),
                activity = VALUES(activity),
                goal = VALUES(goal),
                training_days_per_week = VALUES(training_days_per_week),
                fitness_level = VALUES(fitness_level),
                joint_pain = VALUES(joint_pain),
                duration_preference = VALUES(duration_preference),
                profile_completed = 1"
        );

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param(
            'iisddssissi',
            $accountId,
            $age,
            $gender,
            $heightCm,
            $weightKg,
            $activity,
            $goal,
            $trainingDays,
            $fitnessLevel,
            $jointPain,
            $durationPreference
        );

        $ok = $stmt->execute();
        $stmt->close();

        if ($ok && $gender !== null && $gender !== '') {
            $updateAccounts = $conn->prepare("UPDATE accounts SET gender = ? WHERE id = ? AND (gender IS NULL OR gender = '')");
            if ($updateAccounts) {
                $updateAccounts->bind_param('si', $gender, $accountId);
                $updateAccounts->execute();
                $updateAccounts->close();
            }
        }

        return $ok;
    }
}
