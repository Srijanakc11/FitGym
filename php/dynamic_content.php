<?php
if (!isset($conn)) {
    require_once __DIR__ . '/database.php';
}
require_once __DIR__ . '/class_recommendation_helpers.php';
require_once __DIR__ . '/mail_helpers.php';

if (!function_exists('fitgym_base_url')) {
    function fitgym_base_url(): string
    {
        $scriptDir = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '')));
        $baseUrl = preg_replace('#/php(?:/admin|/client|/trainer)?/?$#', '', $scriptDir);
        $baseUrl = $baseUrl === null ? '' : rtrim($baseUrl, '/');
        if ($baseUrl !== '' && $baseUrl !== '.' && $baseUrl !== '/') {
            return $baseUrl;
        }

        $projectRootName = trim((string)basename(str_replace('\\', '/', dirname(__DIR__))));
        if ($projectRootName !== '' && strtolower($projectRootName) !== 'htdocs') {
            return '/' . ltrim($projectRootName, '/');
        }

        return '';
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

if (!function_exists('fitgym_request_scheme')) {
    function fitgym_request_scheme(): string
    {
        $https = strtolower((string)($_SERVER['HTTPS'] ?? ''));
        if ($https !== '' && $https !== 'off' && $https !== '0') {
            return 'https';
        }

        $forwardedProto = strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
        if ($forwardedProto === 'https') {
            return 'https';
        }

        $requestScheme = strtolower(trim((string)($_SERVER['REQUEST_SCHEME'] ?? '')));
        if ($requestScheme === 'https') {
            return 'https';
        }

        return 'http';
    }
}

if (!function_exists('fitgym_absolute_base_url')) {
    function fitgym_absolute_base_url(): string
    {
        $host = trim((string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
        return fitgym_request_scheme() . '://' . $host . fitgym_base_url();
    }
}

if (!function_exists('fitgym_absolute_url')) {
    function fitgym_absolute_url(string $path = ''): string
    {
        $path = '/' . ltrim($path, '/');
        return fitgym_absolute_base_url() . $path;
    }
}

if (!function_exists('fitgym_esc')) {
    function fitgym_esc($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('fitgym_load_settings')) {
    function fitgym_load_settings(): array
    {
        static $settings = null;
        global $conn;

        if ($settings !== null) {
            return $settings;
        }

        $settings = [];
        if (!isset($conn) || !($conn instanceof mysqli)) {
            return $settings;
        }

        $query = $conn->query("SELECT setting_key, setting_value FROM settings");
        if (!$query) {
            return $settings;
        }

        while ($row = $query->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        $query->free();

        return $settings;
    }
}

if (!function_exists('fitgym_setting')) {
    function fitgym_setting(string $key, string $default = ''): string
    {
        $settings = fitgym_load_settings();
        return isset($settings[$key]) ? trim((string)$settings[$key]) : $default;
    }
}

if (!function_exists('fitgym_json_decode')) {
    function fitgym_json_decode(string $json, array $fallback = []): array
    {
        if ($json === '') {
            return $fallback;
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : $fallback;
    }
}

if (!function_exists('fitgym_json_setting')) {
    function fitgym_json_setting(string $key, array $fallback = []): array
    {
        return fitgym_json_decode(fitgym_setting($key), $fallback);
    }
}

if (!function_exists('fitgym_load_content_blocks')) {
    function fitgym_load_content_blocks(): array
    {
        static $blocks = null;
        global $conn;

        if ($blocks !== null) {
            return $blocks;
        }

        $blocks = [];
        if (!isset($conn) || !($conn instanceof mysqli)) {
            return $blocks;
        }

        $query = $conn->query("SELECT block_key, title, body FROM content_blocks");
        if (!$query) {
            return $blocks;
        }

        while ($row = $query->fetch_assoc()) {
            $blocks[$row['block_key']] = [
                'title' => (string)($row['title'] ?? ''),
                'body' => (string)($row['body'] ?? ''),
            ];
        }
        $query->free();

        return $blocks;
    }
}

if (!function_exists('fitgym_block')) {
    function fitgym_block(string $key, string $fallbackTitle = '', string $fallbackBody = ''): array
    {
        $blocks = fitgym_load_content_blocks();
        if (!isset($blocks[$key])) {
            return ['title' => $fallbackTitle, 'body' => $fallbackBody];
        }
        return [
            'title' => trim($blocks[$key]['title']) !== '' ? $blocks[$key]['title'] : $fallbackTitle,
            'body' => trim($blocks[$key]['body']) !== '' ? $blocks[$key]['body'] : $fallbackBody,
        ];
    }
}

if (!function_exists('fitgym_json_block')) {
    function fitgym_json_block(string $key, array $fallback = []): array
    {
        $block = fitgym_block($key, '', '');
        return fitgym_json_decode($block['body'], $fallback);
    }
}

if (!function_exists('fitgym_schedule_slots_from_json')) {
    function fitgym_schedule_slots_from_json(string $json = '', string $fallbackSchedule = ''): array
    {
        $slots = [];
        $decoded = fitgym_json_decode($json, []);
        foreach ($decoded as $row) {
            if (!is_array($row)) {
                continue;
            }
            $day = trim((string)($row['day'] ?? ''));
            $time = trim((string)($row['time'] ?? ''));
            if ($day === '' || $time === '') {
                continue;
            }
            $slots[] = ['day' => $day, 'time' => $time];
        }

        if (!empty($slots)) {
            return $slots;
        }

        $weekdayMap = [
            'Monday' => 'Mon',
            'Tuesday' => 'Tue',
            'Wednesday' => 'Wed',
            'Thursday' => 'Thu',
            'Friday' => 'Fri',
            'Saturday' => 'Sat',
            'Sunday' => 'Sun',
        ];
        $time = '';
        if (preg_match('/(\d{1,2}:\d{2}\s*-\s*\d{1,2}:\d{2}\s*(?:AM|PM))/i', $fallbackSchedule, $matches)) {
            $time = strtoupper(trim(preg_replace('/\s+/', ' ', $matches[1])));
        }
        foreach ($weekdayMap as $full => $short) {
            if (preg_match('/\b' . preg_quote($short, '/') . '\b/i', $fallbackSchedule) || preg_match('/\b' . preg_quote($full, '/') . '\b/i', $fallbackSchedule)) {
                $slots[] = ['day' => $short, 'time' => $time !== '' ? $time : fitgym_setting('default_class_time', 'Schedule on request')];
            }
        }

        return $slots;
    }
}

if (!function_exists('fitgym_schedule_summary')) {
    function fitgym_schedule_summary(array $slots): string
    {
        if (empty($slots)) {
            return '';
        }
        $parts = [];
        foreach ($slots as $slot) {
            $day = trim((string)($slot['day'] ?? ''));
            $time = trim((string)($slot['time'] ?? ''));
            if ($day === '' || $time === '') {
                continue;
            }
            $parts[] = $day . ' ' . $time;
        }
        return implode(', ', $parts);
    }
}

if (!function_exists('fitgym_schedule_upcoming_options')) {
    function fitgym_schedule_upcoming_options(array $slots, int $daysAhead = 21): array
    {
        $dayNumberMap = [
            'Mon' => 1,
            'Tue' => 2,
            'Wed' => 3,
            'Thu' => 4,
            'Fri' => 5,
            'Sat' => 6,
            'Sun' => 7,
        ];
        $results = [];
        $today = new DateTimeImmutable('today');

        foreach ($slots as $slot) {
            $day = trim((string)($slot['day'] ?? ''));
            $time = trim((string)($slot['time'] ?? ''));
            if (!isset($dayNumberMap[$day]) || $time === '') {
                continue;
            }

            for ($offset = 0; $offset <= $daysAhead; $offset++) {
                $date = $today->modify('+' . $offset . ' day');
                if ((int)$date->format('N') !== $dayNumberMap[$day]) {
                    continue;
                }
                $key = $date->format('Y-m-d') . '|' . $time;
                $results[$key] = [
                    'key' => $key,
                    'date' => $date->format('Y-m-d'),
                    'day' => $day,
                    'time' => $time,
                    'label' => $day . ' • ' . $date->format('M j, Y') . ' • ' . $time,
                ];
            }
        }

        ksort($results);
        return array_values($results);
    }
}

if (!function_exists('fitgym_get_nav_links')) {
    function fitgym_get_nav_links(): array
    {
        $fallback = [
            ['label' => 'Home', 'url' => fitgym_url('/index.php'), 'match' => 'index.php'],
            ['label' => 'Classes', 'url' => fitgym_url('/php/classes.php'), 'match' => 'classes.php'],
            ['label' => 'Contact', 'url' => fitgym_url('/php/contact.php'), 'match' => 'contact.php'],
            ['label' => 'About Us', 'url' => fitgym_url('/php/about.php'), 'match' => 'about.php'],
        ];
        return fitgym_json_setting('header_nav_links', $fallback);
    }
}

if (!function_exists('fitgym_guess_class_image')) {
    function fitgym_guess_class_image(string $slug = '', string $name = ''): string
    {
        $value = strtolower(trim($slug . ' ' . $name));

        $keywordMap = [
            'yoga' => '/pictures/yoga.jpg',
            'zumba' => '/pictures/zumba.jpg',
            'dance' => '/pictures/zumba.jpg',
            'boxing' => '/pictures/box.jpg',
            'box' => '/pictures/box.jpg',
            'kickboxing' => '/pictures/box.jpg',
            'swim' => '/pictures/swim.jpg',
            'swimming' => '/pictures/swim.jpg',
            'rope' => '/pictures/battle-ropes.jpg',
            'battle' => '/pictures/battle-ropes.jpg',
            'hiit' => '/pictures/battle-ropes.jpg',
            'cardio' => '/pictures/battle-ropes.jpg',
            'strength' => '/pictures/workout.jpg',
            'workout' => '/pictures/workout.jpg',
            'weight' => '/pictures/workout.jpg',
            'gym' => '/pictures/workout.jpg',
            'fitness' => '/pictures/workout.jpg',
        ];

        foreach ($keywordMap as $keyword => $path) {
            if ($value !== '' && strpos($value, $keyword) !== false) {
                return fitgym_url($path);
            }
        }

        return fitgym_url('/pictures/workout.jpg');
    }
}

if (!function_exists('fitgym_table_has_column')) {
    function fitgym_table_has_column(string $table, string $column): bool
    {
        global $conn;
        $cache = $GLOBALS['fitgym_table_column_cache'] ?? [];

        $key = $table . '.' . $column;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        if (!isset($conn) || !($conn instanceof mysqli)) {
            $cache[$key] = false;
            $GLOBALS['fitgym_table_column_cache'] = $cache;
            return false;
        }

        $tableEscaped = $conn->real_escape_string($table);
        $columnEscaped = $conn->real_escape_string($column);
        $result = $conn->query("SHOW COLUMNS FROM `{$tableEscaped}` LIKE '{$columnEscaped}'");
        $cache[$key] = $result instanceof mysqli_result && $result->num_rows > 0;
        $GLOBALS['fitgym_table_column_cache'] = $cache;
        if ($result instanceof mysqli_result) {
            $result->free();
        }

        return $cache[$key];
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

if (!function_exists('fitgym_reset_table_column_cache')) {
    function fitgym_reset_table_column_cache(): void
    {
        $GLOBALS['fitgym_table_column_cache'] = [];
    }
}

if (!function_exists('fitgym_ensure_classes_admin_runtime_schema')) {
    function fitgym_ensure_classes_admin_runtime_schema(): void
    {
        static $ensured = false;
        global $conn;

        if ($ensured) {
            return;
        }

        $ensured = true;

        if (!isset($conn) || !($conn instanceof mysqli) || !fitgym_table_exists('classes_admin')) {
            return;
        }

        $columnSql = [
            'description' => "ALTER TABLE classes_admin ADD COLUMN description TEXT NULL AFTER category",
            'schedule_config' => "ALTER TABLE classes_admin ADD COLUMN schedule_config LONGTEXT NULL AFTER weekly_schedule",
            'image_path' => "ALTER TABLE classes_admin ADD COLUMN image_path VARCHAR(255) NULL AFTER trainer_account_id",
            'image_mime' => "ALTER TABLE classes_admin ADD COLUMN image_mime VARCHAR(100) NULL AFTER image_path",
            'image_data' => "ALTER TABLE classes_admin ADD COLUMN image_data LONGBLOB NULL AFTER image_mime",
            'category' => "ALTER TABLE classes_admin ADD COLUMN category VARCHAR(100) NULL AFTER name",
            'intensity_level' => "ALTER TABLE classes_admin ADD COLUMN intensity_level ENUM('low','medium','high') NULL AFTER description",
            'fitness_level' => "ALTER TABLE classes_admin ADD COLUMN fitness_level ENUM('beginner','intermediate','advanced') NULL AFTER intensity_level",
            'goal_fat_loss' => "ALTER TABLE classes_admin ADD COLUMN goal_fat_loss TINYINT(1) NOT NULL DEFAULT 0 AFTER fitness_level",
            'goal_maintenance' => "ALTER TABLE classes_admin ADD COLUMN goal_maintenance TINYINT(1) NOT NULL DEFAULT 0 AFTER goal_fat_loss",
            'goal_muscle_gain' => "ALTER TABLE classes_admin ADD COLUMN goal_muscle_gain TINYINT(1) NOT NULL DEFAULT 0 AFTER goal_maintenance",
            'goal_endurance' => "ALTER TABLE classes_admin ADD COLUMN goal_endurance TINYINT(1) NOT NULL DEFAULT 0 AFTER goal_muscle_gain",
            'goal_mobility' => "ALTER TABLE classes_admin ADD COLUMN goal_mobility TINYINT(1) NOT NULL DEFAULT 0 AFTER goal_endurance",
            'goal_flexibility' => "ALTER TABLE classes_admin ADD COLUMN goal_flexibility TINYINT(1) NOT NULL DEFAULT 0 AFTER goal_mobility",
            'goal_stress_relief' => "ALTER TABLE classes_admin ADD COLUMN goal_stress_relief TINYINT(1) NOT NULL DEFAULT 0 AFTER goal_flexibility",
            'calories_burn_min' => "ALTER TABLE classes_admin ADD COLUMN calories_burn_min INT NULL AFTER goal_stress_relief",
            'calories_burn_max' => "ALTER TABLE classes_admin ADD COLUMN calories_burn_max INT NULL AFTER calories_burn_min",
            'tdee_min' => "ALTER TABLE classes_admin ADD COLUMN tdee_min INT NULL AFTER calories_burn_max",
            'tdee_max' => "ALTER TABLE classes_admin ADD COLUMN tdee_max INT NULL AFTER tdee_min",
            'duration_minutes' => "ALTER TABLE classes_admin ADD COLUMN duration_minutes INT NULL AFTER tdee_max",
            'recommended_frequency_per_week' => "ALTER TABLE classes_admin ADD COLUMN recommended_frequency_per_week INT NULL AFTER duration_minutes",
            'low_impact' => "ALTER TABLE classes_admin ADD COLUMN low_impact TINYINT(1) NOT NULL DEFAULT 0 AFTER recommended_frequency_per_week",
            'joint_friendly' => "ALTER TABLE classes_admin ADD COLUMN joint_friendly TINYINT(1) NOT NULL DEFAULT 0 AFTER low_impact",
            'requires_equipment' => "ALTER TABLE classes_admin ADD COLUMN requires_equipment TINYINT(1) NOT NULL DEFAULT 0 AFTER joint_friendly",
            'is_active' => "ALTER TABLE classes_admin ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 0 AFTER requires_equipment",
        ];

        foreach ($columnSql as $column => $sql) {
            if (!fitgym_table_has_column('classes_admin', $column)) {
                $conn->query($sql);
                $GLOBALS['fitgym_table_column_cache'] = [];
            }
        }
    }
}

if (!function_exists('fitgym_booking_expiry_days')) {
    function fitgym_booking_expiry_days(): int
    {
        return 30;
    }
}

if (!function_exists('fitgym_sync_booking_expiry_statuses')) {
    function fitgym_sync_booking_expiry_statuses(): void
    {
        static $synced = false;
        global $conn;

        if ($synced || !isset($conn) || !($conn instanceof mysqli)) {
            return;
        }

        if (!fitgym_table_has_column('bookings', 'preferred_date') || !fitgym_table_has_column('bookings', 'status')) {
            $synced = true;
            return;
        }

        $expiryDays = max(1, fitgym_booking_expiry_days());

        if (fitgym_table_has_column('bookings', 'payment_provider') && fitgym_table_has_column('bookings', 'payment_status')) {
            $conn->query(
                "UPDATE bookings
                 SET payment_status = 'expired'
                 WHERE COALESCE(payment_provider, 'cash') = 'khalti'
                   AND COALESCE(payment_status, 'unpaid') IN ('initiated', 'pending')
                   AND preferred_date < DATE_SUB(CURDATE(), INTERVAL {$expiryDays} DAY)"
            );
        }

        $conn->query(
            "UPDATE bookings
             SET status = 'Expired'
             WHERE preferred_date < DATE_SUB(CURDATE(), INTERVAL {$expiryDays} DAY)
               AND COALESCE(status, 'Pending') NOT IN ('Cancelled', 'Expired')"
        );

        $synced = true;
    }
}

if (!function_exists('fitgym_booking_status_badge_class')) {
    function fitgym_booking_status_badge_class(?string $status): string
    {
        $normalized = strtolower(trim((string)$status));
        return match ($normalized) {
            'confirmed' => 'success',
            'cancelled' => 'danger',
            'expired' => 'info',
            default => 'warning',
        };
    }
}

if (!function_exists('fitgym_booking_status_is_cancellable')) {
    function fitgym_booking_status_is_cancellable(?string $status): bool
    {
        $normalized = strtolower(trim((string)$status));
        return !in_array($normalized, ['cancelled', 'expired'], true);
    }
}

if (!function_exists('fitgym_booking_active_sql')) {
    function fitgym_booking_active_sql(string $alias = ''): string
    {
        fitgym_sync_booking_expiry_statuses();

        $prefix = $alias !== '' ? rtrim($alias, '.') . '.' : '';
        $statusColumn = $prefix . 'status';
        $baseCondition = "COALESCE({$statusColumn}, 'Pending') NOT IN ('Cancelled', 'Expired')";

        if (
            !fitgym_table_has_column('bookings', 'payment_provider')
            || !fitgym_table_has_column('bookings', 'payment_status')
            || !fitgym_table_has_column('bookings', 'payment_requested_at')
        ) {
            return $baseCondition;
        }

        $paymentProviderColumn = $prefix . 'payment_provider';
        $paymentStatusColumn = $prefix . 'payment_status';
        $paymentRequestedAtColumn = $prefix . 'payment_requested_at';

        return $baseCondition
            . " AND ("
            . "COALESCE({$paymentProviderColumn}, 'cash') <> 'khalti'"
            . " OR COALESCE({$paymentStatusColumn}, 'unpaid') = 'paid'"
            . " OR ("
            . "COALESCE({$paymentStatusColumn}, 'unpaid') IN ('initiated', 'pending')"
            . " AND COALESCE({$paymentRequestedAtColumn}, NOW()) >= DATE_SUB(NOW(), INTERVAL 65 MINUTE)"
            . ")"
            . ")";
    }
}

if (!function_exists('fitgym_get_classes')) {
    function fitgym_get_classes(): array
    {
        static $classCache = null;
        global $conn;

        if ($classCache !== null) {
            return $classCache;
        }

        $classCache = [];
        fitgym_ensure_classes_admin_runtime_schema();

        $imageMap = fitgym_json_setting('class_images_json', []);
        $descriptionMap = fitgym_json_setting('class_descriptions_json', []);
        $locationMap = fitgym_json_setting('class_locations_json', []);
        $timeMap = fitgym_json_setting('class_times_json', []);
        $hasScheduleConfigColumn = fitgym_table_has_column('classes_admin', 'schedule_config');
        $hasImagePathColumn = fitgym_table_has_column('classes_admin', 'image_path');
        $hasImageMimeColumn = fitgym_table_has_column('classes_admin', 'image_mime');
        $hasImageDataColumn = fitgym_table_has_column('classes_admin', 'image_data');
        $hasCategoryColumn = fitgym_table_has_column('classes_admin', 'category');
        $hasDescriptionColumn = fitgym_table_has_column('classes_admin', 'description');
        $hasIntensityLevelColumn = fitgym_table_has_column('classes_admin', 'intensity_level');
        $hasFitnessLevelColumn = fitgym_table_has_column('classes_admin', 'fitness_level');
        $goalColumnAvailability = [];
        foreach (fitgym_goal_flag_columns() as $goalColumn) {
            $goalColumnAvailability[$goalColumn] = fitgym_table_has_column('classes_admin', $goalColumn);
        }
        $hasCaloriesBurnMinColumn = fitgym_table_has_column('classes_admin', 'calories_burn_min');
        $hasCaloriesBurnMaxColumn = fitgym_table_has_column('classes_admin', 'calories_burn_max');
        $hasLegacyDurationColumn = fitgym_table_has_column('classes_admin', 'duration_min');
        $hasLegacyLevelColumn = fitgym_table_has_column('classes_admin', 'level');
        $hasKcalMinColumn = fitgym_table_has_column('classes_admin', 'kcal_min');
        $hasKcalMaxColumn = fitgym_table_has_column('classes_admin', 'kcal_max');
        $hasTdeeMinColumn = fitgym_table_has_column('classes_admin', 'tdee_min');
        $hasTdeeMaxColumn = fitgym_table_has_column('classes_admin', 'tdee_max');
        $hasDurationMinutesColumn = fitgym_table_has_column('classes_admin', 'duration_minutes');
        $hasRecommendedFrequencyColumn = fitgym_table_has_column('classes_admin', 'recommended_frequency_per_week');
        $hasLowImpactColumn = fitgym_table_has_column('classes_admin', 'low_impact');
        $hasJointFriendlyColumn = fitgym_table_has_column('classes_admin', 'joint_friendly');
        $hasRequiresEquipmentColumn = fitgym_table_has_column('classes_admin', 'requires_equipment');
        $hasRecommendationActiveColumn = fitgym_table_has_column('classes_admin', 'is_active');
        $activeBookingWhere = fitgym_booking_active_sql();

        if (isset($conn) && $conn instanceof mysqli) {
            $scheduleConfigSelect = $hasScheduleConfigColumn ? ', c.schedule_config' : '';
            $imageSelect = ', c.image_path';
            $imageMimeSelect = ', c.image_mime';
            $imageDataSelect = ', c.image_data';
            $categorySelect = $hasCategoryColumn ? ', c.category' : '';
            $descriptionSelect = $hasDescriptionColumn ? ', c.description' : '';
            $intensityLevelSelect = $hasIntensityLevelColumn ? ', c.intensity_level' : '';
            $fitnessLevelSelect = $hasFitnessLevelColumn ? ', c.fitness_level' : '';
            $goalSelectParts = [];
            foreach ($goalColumnAvailability as $goalColumn => $isAvailable) {
                if ($isAvailable) {
                    $goalSelectParts[] = ', c.' . $goalColumn;
                }
            }
            $caloriesBurnMinSelect = $hasCaloriesBurnMinColumn ? ', c.calories_burn_min' : '';
            $caloriesBurnMaxSelect = $hasCaloriesBurnMaxColumn ? ', c.calories_burn_max' : '';
            $legacyDurationSelect = $hasLegacyDurationColumn ? ', c.duration_min' : '';
            $legacyLevelSelect = $hasLegacyLevelColumn ? ', c.level' : '';
            $kcalMinSelect = $hasKcalMinColumn ? ', c.kcal_min' : '';
            $kcalMaxSelect = $hasKcalMaxColumn ? ', c.kcal_max' : '';
            $tdeeMinSelect = $hasTdeeMinColumn ? ', c.tdee_min' : '';
            $priceSelect = ', c.price';
            $tdeeMaxSelect = $hasTdeeMaxColumn ? ', c.tdee_max' : '';
            $durationMinutesSelect = $hasDurationMinutesColumn ? ', c.duration_minutes' : '';
            $recommendedFrequencySelect = $hasRecommendedFrequencyColumn ? ', c.recommended_frequency_per_week' : '';
            $lowImpactSelect = $hasLowImpactColumn ? ', c.low_impact' : '';
            $jointFriendlySelect = $hasJointFriendlyColumn ? ', c.joint_friendly' : '';
            $requiresEquipmentSelect = $hasRequiresEquipmentColumn ? ', c.requires_equipment' : '';
            $recommendationActiveSelect = $hasRecommendationActiveColumn ? ', c.is_active' : '';
            $recommendationFieldsSelect = $categorySelect
                . $descriptionSelect
                . $legacyLevelSelect
                . $legacyDurationSelect
                . $intensityLevelSelect
                . $fitnessLevelSelect
                . implode('', $goalSelectParts)
                . $caloriesBurnMinSelect
                . $caloriesBurnMaxSelect
                . $kcalMinSelect
                . $kcalMaxSelect
                . $tdeeMinSelect
                . $tdeeMaxSelect
                . $durationMinutesSelect
                . $recommendedFrequencySelect
                . $lowImpactSelect
                . $jointFriendlySelect
                . $requiresEquipmentSelect
                . $recommendationActiveSelect;
            $sql = "SELECT c.id, c.slug, c.name, c.max_participants, c.trainer_account_id, c.weekly_schedule, c.active{$scheduleConfigSelect}{$imageSelect}{$imageMimeSelect}{$imageDataSelect}{$recommendationFieldsSelect}{$priceSelect},
                           a.name AS trainer_name,
                           COALESCE(stats.total_bookings, 0) AS total_bookings,
                           COALESCE(stats.total_clients, 0) AS total_clients
                    FROM classes_admin c
                    LEFT JOIN (
                        SELECT class_slug,
                               COUNT(*) AS total_bookings,
                               COALESCE(SUM(participants), 0) AS total_clients
                        FROM bookings
                        WHERE {$activeBookingWhere}
                        GROUP BY class_slug
                    ) stats ON stats.class_slug = c.slug
                    LEFT JOIN accounts a ON c.trainer_account_id = a.id AND a.role = 'trainer'
                    WHERE c.active = 1
                    ORDER BY c.created_at DESC";
            $query = $conn->query($sql);
            if ($query) {
                while ($row = $query->fetch_assoc()) {
                    $slug = (string)$row['slug'];
                    $name = (string)$row['name'];
                    $scheduleSlots = fitgym_schedule_slots_from_json((string)($row['schedule_config'] ?? ''), (string)($row['weekly_schedule'] ?? ''));
                    $scheduleSummary = fitgym_schedule_summary($scheduleSlots);
                    $uploadedImage = trim((string)($row['image_path'] ?? ''));
                    $uploadedImageMime = trim((string)($row['image_mime'] ?? ''));
                    $uploadedImageData = $row['image_data'] ?? null;
                    $image = $uploadedImage !== '' ? fitgym_url($uploadedImage) : ($imageMap[$slug] ?? fitgym_guess_class_image($slug, $name));
                    if ($uploadedImageMime !== '' && $uploadedImageData !== null && $uploadedImageData !== '') {
                        $image = 'data:' . $uploadedImageMime . ';base64,' . base64_encode((string)$uploadedImageData);
                    }
                    $classData = fitgym_normalize_class_row($row);
                    $classData['image'] = (string)$image;
                    $classData['description'] = trim((string)($row['description'] ?? '')) !== ''
                        ? (string)$row['description']
                        : (string)($descriptionMap[$slug] ?? ('Join our ' . $name . ' program with guided sessions and steady progress.'));
                    $classData['trainer'] = (string)($row['trainer_name'] ?? 'TBA');
                    $classData['location'] = (string)($locationMap[$slug] ?? fitgym_setting('default_class_location', 'Main Studio'));
                    $classData['time'] = (string)($timeMap[$slug] ?? ($scheduleSummary !== '' ? $scheduleSummary : ((string)($row['weekly_schedule'] ?? '') ?: fitgym_setting('default_class_time', 'Schedule on request'))));
                    $classData['schedule_slots'] = $scheduleSlots;
                    $classData['total_bookings'] = (int)($row['total_bookings'] ?? 0);
                    $classData['total_clients'] = (int)($row['total_clients'] ?? 0);
                    $classData['duration_min'] = $classData['duration_minutes'] ?? 45;
                    $classCache[] = $classData;
                }
                $query->free();
            }
        }

        return $classCache;
    }
}

if (!function_exists('fitgym_get_class_by_slug')) {
    function fitgym_get_class_by_slug(string $slug): ?array
    {
        foreach (fitgym_get_classes() as $item) {
            if ((string)$item['slug'] === $slug) {
                return $item;
            }
        }
        return null;
    }
}

if (!function_exists('fitgym_get_home_featured_classes')) {
    function fitgym_get_home_featured_classes(int $limit = 3): array
    {
        $limit = max(1, $limit);
        $classes = fitgym_get_classes();
        if ($classes === []) {
            return [
                'mode' => 'empty',
                'classes' => [],
            ];
        }

        $popularClasses = array_values(array_filter($classes, static function (array $classRow): bool {
            return (int)($classRow['total_bookings'] ?? 0) > 0;
        }));

        if ($popularClasses !== []) {
            usort($popularClasses, static function (array $left, array $right): int {
                $bookingCompare = (int)($right['total_bookings'] ?? 0) <=> (int)($left['total_bookings'] ?? 0);
                if ($bookingCompare !== 0) {
                    return $bookingCompare;
                }

                $clientCompare = (int)($right['total_clients'] ?? 0) <=> (int)($left['total_clients'] ?? 0);
                if ($clientCompare !== 0) {
                    return $clientCompare;
                }

                $nameCompare = strcasecmp((string)($left['title'] ?? $left['name'] ?? ''), (string)($right['title'] ?? $right['name'] ?? ''));
                if ($nameCompare !== 0) {
                    return $nameCompare;
                }

                return (int)($left['id'] ?? 0) <=> (int)($right['id'] ?? 0);
            });

            return [
                'mode' => 'popular',
                'classes' => array_slice($popularClasses, 0, $limit),
            ];
        }

        $fallbackClasses = fitgym_json_block('home_featured_classes', []);
        if ($fallbackClasses === []) {
            $fallbackClasses = array_values(array_filter($classes, static function (array $classRow): bool {
                return fitgym_class_is_recommendation_ready($classRow);
            }));
        }

        if ($fallbackClasses === []) {
            $fallbackClasses = $classes;
        }

        return [
            'mode' => 'fallback',
            'classes' => array_slice($fallbackClasses, 0, $limit),
        ];
    }
}

if (!function_exists('fitgym_get_testimonials')) {
    function fitgym_get_testimonials(): array
    {
        static $testimonials = null;
        global $conn;

        if ($testimonials !== null) {
            return $testimonials;
        }

        $testimonials = [];
        if (isset($conn) && $conn instanceof mysqli) {
            $query = $conn->query("SELECT user_name, rating, comment FROM reviews WHERE status = 'Approved' ORDER BY created_at DESC LIMIT 8");
            if ($query) {
                while ($row = $query->fetch_assoc()) {
                    $rating = max(1, min(5, (int)$row['rating']));
                    $testimonials[] = [
                        'stars' => str_repeat('★', $rating) . str_repeat('☆', 5 - $rating),
                        'message' => (string)$row['comment'],
                        'author' => (string)$row['user_name'],
                    ];
                }
                $query->free();
            }
        }

        if (!empty($testimonials)) {
            return $testimonials;
        }

        return fitgym_json_block('home_testimonials', [
            ['stars' => '★★★★★', 'message' => 'Great environment and great trainers.', 'author' => 'Member'],
            ['stars' => '★★★★★', 'message' => 'Best gym experience in the city.', 'author' => 'Returning Member'],
        ]);
    }
}

if (!function_exists('fitgym_ensure_notifications_table')) {
    function fitgym_ensure_notifications_table(): void
    {
        static $ensured = false;
        global $conn;
        if ($ensured || !isset($conn) || !($conn instanceof mysqli)) return;
        $ensured = true;

        $sql = "CREATE TABLE IF NOT EXISTS `user_notifications` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `booking_id` INT NOT NULL,
            `type` VARCHAR(50) NOT NULL,
            `message` VARCHAR(255) NOT NULL,
            `details` TEXT NOT NULL,
            `is_read` TINYINT(1) DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (`user_id`),
            INDEX (`is_read`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $conn->query($sql);
    }
}

if (!function_exists('fitgym_parse_class_start_time')) {
    function fitgym_parse_class_start_time(string $date, string $timeSlot): ?int
    {
        $parts = explode('-', $timeSlot);
        $startTimeStr = trim($parts[0]);
        if (!preg_match('/[AP]M/i', $startTimeStr) && preg_match('/[AP]M/i', $timeSlot)) {
            preg_match('/([AP]M)/i', $timeSlot, $m);
            $startTimeStr .= ' ' . $m[1];
        }
        $timestamp = strtotime($date . ' ' . $startTimeStr);
        return $timestamp !== false ? $timestamp : null;
    }
}

if (!function_exists('fitgym_generate_class_reminders')) {
    function fitgym_generate_class_reminders(int $userId): void
    {
        global $conn;
        if (!isset($conn) || !($conn instanceof mysqli) || $userId <= 0) return;

        fitgym_ensure_notifications_table();
        $activeSql = fitgym_booking_active_sql('b');
        
        $sql = "SELECT b.id, b.class_name, b.trainer_name, b.preferred_date, b.time_slot, b.payment_status, b.status
                FROM bookings b
                WHERE b.email = (SELECT email FROM accounts WHERE id = ? LIMIT 1)
                  AND b.preferred_date >= CURDATE()
                  AND b.preferred_date <= DATE_ADD(CURDATE(), INTERVAL 1 DAY)
                  AND {$activeSql}";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) return;
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $bookings = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();

        $now = time();
        foreach ($bookings as $b) {
            $startTime = fitgym_parse_class_start_time((string)$b['preferred_date'], (string)$b['time_slot']);
            if (!$startTime) continue;

            $diffMinutes = (int)round(($startTime - $now) / 60);
            if ($diffMinutes <= 60 && $diffMinutes > 15) {
                fitgym_create_reminder_if_not_exists($userId, (int)$b['id'], 'reminder_60', $b);
            }
            if ($diffMinutes <= 15 && $diffMinutes > -10) {
                fitgym_create_reminder_if_not_exists($userId, (int)$b['id'], 'reminder_15', $b);
            }
        }
    }
}

if (!function_exists('fitgym_create_reminder_if_not_exists')) {
    function fitgym_create_reminder_if_not_exists(int $userId, int $bookingId, string $type, array $bookingData): void
    {
        global $conn;
        $check = $conn->prepare("SELECT id FROM user_notifications WHERE user_id = ? AND booking_id = ? AND type = ? LIMIT 1");
        $check->bind_param('iis', $userId, $bookingId, $type);
        $check->execute();
        if ($check->get_result()->fetch_assoc()) {
            $check->close();
            return;
        }
        $check->close();

        $message = "Class Reminder: " . $bookingData['class_name'] . " starts in " . ($type === 'reminder_60' ? '1 hour' : '15 minutes') . "!";
        $details = json_encode([
            'title' => 'Upcoming Class: ' . $bookingData['class_name'],
            'time' => $bookingData['time_slot'],
            'date' => $bookingData['preferred_date'],
            'trainer' => $bookingData['trainer_name'],
            'status' => $bookingData['status'],
            'payment' => $bookingData['payment_status']
        ]);

        $ins = $conn->prepare("INSERT INTO user_notifications (user_id, booking_id, type, message, details) VALUES (?, ?, ?, ?, ?)");
        $ins->bind_param('iisss', $userId, $bookingId, $type, $message, $details);
        $ins->execute();
        $ins->close();
    }
}

if (!function_exists('fitgym_create_booking_notification_for_trainer')) {
    function fitgym_create_booking_notification_for_trainer(int $bookingId, string $trainerName, string $className, string $clientName, string $timeSlot, string $date): void
    {
        global $conn;
        if (!isset($conn) || !($conn instanceof mysqli) || $trainerName === '' || $trainerName === 'TBA') return;

        $stmt = $conn->prepare("SELECT id FROM accounts WHERE name = ? AND role = 'trainer' LIMIT 1");
        if (!$stmt) return;
        $stmt->bind_param('s', $trainerName);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $trainerId = (int)$row['id'];
            $message = "New Booking: " . $clientName . " for " . $className;
            $details = json_encode([
                'title' => 'New Booking: ' . $className,
                'time' => $timeSlot,
                'date' => $date,
                'client' => $clientName
            ]);
            
            fitgym_ensure_notifications_table();
            $ins = $conn->prepare("INSERT INTO user_notifications (user_id, booking_id, type, message, details) VALUES (?, ?, 'new_booking', ?, ?)");
            if ($ins) {
                $ins->bind_param('iiss', $trainerId, $bookingId, $message, $details);
                $ins->execute();
                $ins->close();
            }
        }
        $stmt->close();
    }
}

if (!function_exists('fitgym_create_booking_status_notification_for_client')) {
    function fitgym_create_booking_status_notification_for_client(array $bookingRow, string $status, string $actorName = 'Trainer', string $reason = ''): void
    {
        global $conn;
        if (!isset($conn) || !($conn instanceof mysqli)) return;

        $bookingId = (int)($bookingRow['id'] ?? 0);
        $email = trim((string)($bookingRow['email'] ?? ''));
        if ($bookingId <= 0 || $email === '') return;

        $clientStmt = $conn->prepare("SELECT id, name FROM accounts WHERE email = ? AND role = 'client' LIMIT 1");
        if (!$clientStmt) return;
        $clientStmt->bind_param('s', $email);
        $clientStmt->execute();
        $clientRow = $clientStmt->get_result()->fetch_assoc();
        $clientStmt->close();
        if (!$clientRow) return;

        $normalizedStatus = ucfirst(strtolower(trim($status)));
        $className = trim((string)($bookingRow['class_name'] ?? 'your class'));
        $timeSlot = trim((string)($bookingRow['time_slot'] ?? ''));
        $date = trim((string)($bookingRow['preferred_date'] ?? ''));
        $trainerName = trim((string)($bookingRow['trainer_name'] ?? $actorName));
        $type = strtolower($normalizedStatus) === 'confirmed' ? 'booking_confirmed' : 'booking_cancelled';
        $reason = trim($reason);
        $message = $normalizedStatus === 'Confirmed'
            ? $trainerName . ' confirmed your booking for ' . $className . '.'
            : $trainerName . ' cancelled your booking for ' . $className . '.';
        if ($normalizedStatus === 'Cancelled' && $reason !== '') {
            $message .= ' Reason: ' . $reason;
        }
        $details = json_encode([
            'title' => 'Booking ' . $normalizedStatus,
            'class_name' => $className,
            'date' => $date,
            'time' => $timeSlot,
            'trainer' => $trainerName,
            'status' => $normalizedStatus,
            'reason' => $reason,
        ]);

        fitgym_ensure_notifications_table();
        $check = $conn->prepare("SELECT id FROM user_notifications WHERE user_id = ? AND booking_id = ? AND type = ? LIMIT 1");
        if ($check) {
            $clientId = (int)$clientRow['id'];
            $check->bind_param('iis', $clientId, $bookingId, $type);
            $check->execute();
            $existing = $check->get_result()->fetch_assoc();
            $check->close();
            if ($existing) {
                $update = $conn->prepare("UPDATE user_notifications SET message = ?, details = ?, is_read = 0, created_at = NOW() WHERE id = ?");
                if ($update) {
                    $notifId = (int)$existing['id'];
                    $update->bind_param('ssi', $message, $details, $notifId);
                    $update->execute();
                    $update->close();
                }
                return;
            }
        }

        $insert = $conn->prepare("INSERT INTO user_notifications (user_id, booking_id, type, message, details) VALUES (?, ?, ?, ?, ?)");
        if ($insert) {
            $clientId = (int)$clientRow['id'];
            $insert->bind_param('iisss', $clientId, $bookingId, $type, $message, $details);
            $insert->execute();
            $insert->close();
        }
    }
}

if (!function_exists('fitgym_get_user_notifications')) {
    function fitgym_get_user_notifications(int $userId): array
    {
        global $conn;
        if (!isset($conn) || !($conn instanceof mysqli) || $userId <= 0) return [];

        fitgym_ensure_notifications_table();
        $sql = "SELECT id, message, details, is_read, created_at FROM user_notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10";
        $stmt = $conn->prepare($sql);
        if (!$stmt) return [];
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }
}

if (!function_exists('fitgym_mark_notification_as_read')) {
    function fitgym_mark_notification_as_read(int $notifId): void
    {
        global $conn;
        if (!isset($conn) || !($conn instanceof mysqli)) return;
        $stmt = $conn->prepare("UPDATE user_notifications SET is_read = 1 WHERE id = ?");
        if (!$stmt) return;
        $stmt->bind_param('i', $notifId);
        $stmt->execute();
        $stmt->close();
    }
}

fitgym_process_booking_email_notifications();
