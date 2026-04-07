<?php
require_once __DIR__ . '/partials/header.php';
require_once __DIR__ . '/../class_recommendation_helpers.php';

$successMessage = '';
$errors = [];
$currentAction = $_POST['action'] ?? '';

$weekdayOptions = [
    'Mon' => 'Monday',
    'Tue' => 'Tuesday',
    'Wed' => 'Wednesday',
    'Thu' => 'Thursday',
    'Fri' => 'Friday',
    'Sat' => 'Saturday',
    'Sun' => 'Sunday',
];
$scheduleTimeOptions = [
    '6:00-7:00 AM',
    '7:00-8:00 AM',
    '8:00-9:00 AM',
    '9:00-10:00 AM',
    '12:00-1:00 PM',
    '4:00-5:00 PM',
    '5:00-6:00 PM',
    '6:00-7:00 PM',
    '7:00-8:00 PM',
];
$categoryOptions = [
    'yoga' => 'Yoga',
    'dance' => 'Dance',
    'cardio' => 'Cardio',
    'strength' => 'Strength',
    'pilates' => 'Pilates',
    'hiit' => 'HIIT',
    'flexibility' => 'Flexibility',
    'recovery' => 'Recovery',
    'mixed' => 'Mixed',
];

if (!function_exists('admin_class_schedule_rows_from_post')) {
    function admin_class_schedule_rows_from_post(array $modes, array $days, array $endDays, array $times, array $weekdayOptions, array $scheduleTimeOptions): array
    {
        $rows = [];
        $errors = [];
        $allowedDays = array_keys($weekdayOptions);
        $allowedTimes = array_values($scheduleTimeOptions);
        $dayOrder = array_values($allowedDays);
        $count = max(count($modes), count($days), count($endDays), count($times));

        for ($i = 0; $i < $count; $i++) {
            $mode = trim((string)($modes[$i] ?? 'single'));
            $day = trim((string)($days[$i] ?? ''));
            $endDay = trim((string)($endDays[$i] ?? ''));
            $time = trim((string)($times[$i] ?? ''));

            if ($day === '' || $time === '') {
                continue;
            }
            if (!in_array($day, $allowedDays, true) || !in_array($time, $allowedTimes, true)) {
                continue;
            }

            if ($mode === 'range') {
                if ($endDay === '' || !in_array($endDay, $allowedDays, true)) {
                    $errors[] = 'Please select both start day and end day for a day range.';
                    continue;
                }

                $startIndex = array_search($day, $dayOrder, true);
                $endIndex = array_search($endDay, $dayOrder, true);
                if ($startIndex === false || $endIndex === false || $endIndex < $startIndex) {
                    $errors[] = 'For a day range, the end day must be after the start day.';
                    continue;
                }

                for ($dayIndex = $startIndex; $dayIndex <= $endIndex; $dayIndex++) {
                    $rangeDay = $dayOrder[$dayIndex];
                    $rows[$rangeDay . '|' . $time] = [
                        'mode' => 'single',
                        'day' => $rangeDay,
                        'end_day' => '',
                        'time' => $time,
                    ];
                }
                continue;
            }

            $rows[$day . '|' . $time] = [
                'mode' => 'single',
                'day' => $day,
                'end_day' => '',
                'time' => $time,
            ];
        }

        return [
            'rows' => array_values($rows),
            'errors' => $errors,
        ];
    }
}

if (!function_exists('admin_class_schedule_build')) {
    function admin_class_schedule_build(array $rows): string
    {
        $parts = [];
        foreach ($rows as $row) {
            $day = trim((string)($row['day'] ?? ''));
            $time = trim((string)($row['time'] ?? ''));
            if ($day === '' || $time === '') {
                continue;
            }
            $parts[] = $day . ' ' . $time;
        }

        return implode(', ', $parts);
    }
}

if (!function_exists('admin_class_schedule_parse_rows')) {
    function admin_class_schedule_parse_rows(string $scheduleConfig = '', string $fallbackSchedule = ''): array
    {
        $rows = [];
        foreach (fitgym_schedule_slots_from_json($scheduleConfig, $fallbackSchedule) as $slot) {
            $rows[] = [
                'mode' => 'single',
                'day' => (string)($slot['day'] ?? ''),
                'end_day' => '',
                'time' => (string)($slot['time'] ?? ''),
            ];
        }

        return $rows;
    }
}

if (!function_exists('admin_class_form_defaults')) {
    function admin_class_form_defaults(): array
    {
        return array_merge([
            'id' => 0,
            'name' => '',
            'slug' => '',
            'category' => '',
            'description' => '',
            'trainer_account_id' => 0,
            'max_participants' => 20,
            'active' => 1,
            'intensity_level' => '',
            'fitness_level' => '',
            'calories_burn_min' => '',
            'calories_burn_max' => '',
            'tdee_min' => '',
            'tdee_max' => '',
            'duration_minutes' => '45',
            'recommended_frequency_per_week' => '',
            'low_impact' => 0,
            'joint_friendly' => 0,
            'requires_equipment' => 0,
            'is_active' => 0,
        ], admin_class_goal_defaults());
    }
}

if (!function_exists('admin_class_goal_payload_keys')) {
    function admin_class_goal_payload_keys(): array
    {
        return array_values(fitgym_goal_flag_columns());
    }
}

if (!function_exists('admin_class_goal_defaults')) {
    function admin_class_goal_defaults(): array
    {
        $defaults = [];
        foreach (admin_class_goal_payload_keys() as $goalColumn) {
            $defaults[$goalColumn] = 0;
        }

        return $defaults;
    }
}

if (!function_exists('admin_class_clean_text')) {
    function admin_class_clean_text($value, int $maxLength = 255): string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return '';
        }

        return mb_substr($value, 0, $maxLength);
    }
}

if (!function_exists('admin_class_clean_int_string')) {
    function admin_class_clean_int_string($value, int $min = 0, ?int $max = null): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            return '';
        }

        $value = (int)$value;
        if ($value < $min) {
            return '';
        }
        if ($max !== null && $value > $max) {
            return '';
        }

        return (string)$value;
    }
}

if (!function_exists('admin_class_checkbox_from_source')) {
    function admin_class_checkbox_from_source(array $source, string $key, int $default = 0): int
    {
        if (!array_key_exists($key, $source)) {
            return $default;
        }

        return (int)((string)$source[$key] === '1' || $source[$key] === 1 || $source[$key] === true || $source[$key] === 'on');
    }
}

if (!function_exists('admin_class_goal_payload')) {
    function admin_class_goal_payload(array $source): array
    {
        $values = [];
        foreach (admin_class_goal_payload_keys() as $goalColumn) {
            $values[$goalColumn] = admin_class_checkbox_from_source($source, $goalColumn);
        }

        return $values;
    }
}

if (!function_exists('admin_class_goal_values')) {
    function admin_class_goal_values(array $source): array
    {
        $values = [];
        foreach (admin_class_goal_payload_keys() as $goalColumn) {
            $values[] = (int)($source[$goalColumn] ?? 0);
        }

        return $values;
    }
}

if (!function_exists('admin_class_goal_columns_sql')) {
    function admin_class_goal_columns_sql(): string
    {
        return implode(', ', admin_class_goal_payload_keys());
    }
}

if (!function_exists('admin_class_goal_placeholders_sql')) {
    function admin_class_goal_placeholders_sql(): string
    {
        return implode(', ', array_fill(0, count(admin_class_goal_payload_keys()), '?'));
    }
}

if (!function_exists('admin_class_goal_update_assignments_sql')) {
    function admin_class_goal_update_assignments_sql(): string
    {
        return implode(', ', array_map(
            static fn(string $goalColumn): string => $goalColumn . ' = ?',
            admin_class_goal_payload_keys()
        ));
    }
}

if (!function_exists('admin_class_goal_bind_types')) {
    function admin_class_goal_bind_types(): string
    {
        return str_repeat('i', count(admin_class_goal_payload_keys()));
    }
}

if (!function_exists('admin_class_normalize_slug')) {
    function admin_class_normalize_slug($value): string
    {
        $value = strtolower(trim((string)$value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);
        $value = $value === null ? '' : trim($value, '-');

        return mb_substr($value, 0, 120);
    }
}

if (!function_exists('admin_class_form_payload')) {
    function admin_class_form_payload(array $source): array
    {
        $defaults = admin_class_form_defaults();

        return array_merge($defaults, [
            'id' => (int)($source['id'] ?? 0),
            'name' => admin_class_clean_text($source['name'] ?? '', 120),
            'slug' => admin_class_normalize_slug($source['slug'] ?? ''),
            'category' => fitgym_normalize_text((string)($source['category'] ?? '')),
            'description' => admin_class_clean_text($source['description'] ?? '', 5000),
            'trainer_account_id' => (int)($source['trainer_account_id'] ?? $source['trainer_id'] ?? 0),
            'max_participants' => (int)($source['max_participants'] ?? 20),
            'active' => admin_class_checkbox_from_source($source, 'active', 1),
            'intensity_level' => fitgym_normalize_intensity((string)($source['intensity_level'] ?? '')),
            'fitness_level' => fitgym_normalize_fitness_level((string)($source['fitness_level'] ?? '')),
            'calories_burn_min' => admin_class_clean_int_string($source['calories_burn_min'] ?? '', 0),
            'calories_burn_max' => admin_class_clean_int_string($source['calories_burn_max'] ?? '', 0),
            'tdee_min' => admin_class_clean_int_string($source['tdee_min'] ?? '', 0),
            'tdee_max' => admin_class_clean_int_string($source['tdee_max'] ?? '', 0),
            'duration_minutes' => admin_class_clean_int_string($source['duration_minutes'] ?? '', 1),
            'recommended_frequency_per_week' => admin_class_clean_int_string($source['recommended_frequency_per_week'] ?? '', 1, 7),
            'low_impact' => admin_class_checkbox_from_source($source, 'low_impact'),
            'joint_friendly' => admin_class_checkbox_from_source($source, 'joint_friendly'),
            'requires_equipment' => admin_class_checkbox_from_source($source, 'requires_equipment'),
            'is_active' => admin_class_checkbox_from_source($source, 'is_active'),
        ], admin_class_goal_payload($source));
    }
}

if (!function_exists('admin_class_validate_payload')) {
    function admin_class_validate_payload(array $payload, array $scheduleRows, array $categoryOptions): array
    {
        $errors = [];

        if ($payload['name'] === '') {
            $errors[] = 'Class name is required.';
        }
        if ($payload['slug'] === '') {
            $errors[] = 'Slug is required and may only use letters, numbers, and hyphens.';
        }
        if (!isset($categoryOptions[$payload['category']])) {
            $errors[] = 'Select a valid category.';
        }
        if ($payload['description'] === '') {
            $errors[] = 'Class description is required.';
        }
        if ($payload['trainer_account_id'] <= 0) {
            $errors[] = 'Assign a verified trainer to the class.';
        }
        if ($payload['max_participants'] <= 0) {
            $errors[] = 'Max participants must be greater than zero.';
        }
        if (empty($scheduleRows)) {
            $errors[] = 'Please add at least one regular class schedule.';
        }

        if ($payload['calories_burn_min'] !== '' && $payload['calories_burn_max'] !== '' && (int)$payload['calories_burn_min'] > (int)$payload['calories_burn_max']) {
            $errors[] = 'Calories burn min cannot be greater than max.';
        }
        if ($payload['tdee_min'] !== '' && $payload['tdee_max'] !== '' && (int)$payload['tdee_min'] > (int)$payload['tdee_max']) {
            $errors[] = 'TDEE min cannot be greater than max.';
        }

        if ((int)$payload['is_active'] === 1) {
            if ((int)$payload['active'] !== 1) {
                $errors[] = 'A recommendable class must also be visible to users.';
            }
            $errors = array_merge($errors, fitgym_recommendation_profile_errors($payload));
        }

        return array_values(array_unique($errors));
    }
}

if (!function_exists('admin_class_ensure_schema')) {
    function admin_class_ensure_schema(mysqli $conn): void
    {
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
            if (function_exists('fitgym_table_has_column') && !fitgym_table_has_column('classes_admin', $column)) {
                $conn->query($sql);
            }
        }
    }
}

if (!function_exists('admin_class_image_upload')) {
    function admin_class_image_upload(array $file): array
    {
        $result = [
            'replace' => false,
            'mime' => null,
            'data' => null,
            'errors' => [],
        ];

        if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return $result;
        }

        $result['replace'] = true;
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];

        if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $result['errors'][] = 'Image upload failed. Please try again.';
            return $result;
        }

        $mimeType = mime_content_type((string)$file['tmp_name']);
        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            $result['errors'][] = 'Only JPG, PNG, and WEBP images are allowed.';
            return $result;
        }

        $binary = file_get_contents((string)$file['tmp_name']);
        if ($binary === false) {
            $result['errors'][] = 'Unable to read the uploaded image.';
            return $result;
        }

        $result['mime'] = $mimeType;
        $result['data'] = $binary;

        return $result;
    }
}

if (!function_exists('admin_class_schedule_rows_for_form')) {
    function admin_class_schedule_rows_for_form(array $source, array $fallbackRows = []): array
    {
        $days = (array)($source['schedule_day'] ?? []);
        $times = (array)($source['schedule_time'] ?? []);
        $modes = (array)($source['schedule_mode'] ?? []);
        $endDays = (array)($source['schedule_end_day'] ?? []);
        $rows = [];

        $count = max(count($days), count($times), count($modes), count($endDays));
        for ($index = 0; $index < $count; $index++) {
            $rows[] = [
                'mode' => (string)($modes[$index] ?? 'single'),
                'day' => (string)($days[$index] ?? ''),
                'end_day' => (string)($endDays[$index] ?? ''),
                'time' => (string)($times[$index] ?? ''),
            ];
        }

        if (!empty($rows)) {
            return $rows;
        }

        return !empty($fallbackRows) ? $fallbackRows : [['mode' => 'single', 'day' => '', 'end_day' => '', 'time' => '']];
    }
}

if ($conn instanceof mysqli) {
    admin_class_ensure_schema($conn);
}

if ($conn instanceof mysqli && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'update') {
        $payload = admin_class_form_payload($_POST);
        $scheduleBuild = admin_class_schedule_rows_from_post(
            (array)($_POST['schedule_mode'] ?? []),
            (array)($_POST['schedule_day'] ?? []),
            (array)($_POST['schedule_end_day'] ?? []),
            (array)($_POST['schedule_time'] ?? []),
            $weekdayOptions,
            $scheduleTimeOptions
        );
        $scheduleRows = $scheduleBuild['rows'];
        $errors = array_merge($errors, $scheduleBuild['errors']);
        $errors = array_merge($errors, admin_class_validate_payload($payload, $scheduleRows, $categoryOptions));

        $upload = admin_class_image_upload($_FILES['class_image'] ?? []);
        $errors = array_merge($errors, $upload['errors']);

        if (empty($errors)) {
            $weeklySchedule = admin_class_schedule_build($scheduleRows);
            $scheduleConfig = json_encode($scheduleRows, JSON_UNESCAPED_SLASHES);

            if ($action === 'add') {
                if ($upload['replace']) {
                    $stmt = $conn->prepare(
                        "INSERT INTO classes_admin (
                            name, slug, category, description, weekly_schedule, schedule_config, max_participants,
                            trainer_account_id, image_path, image_mime, image_data, active,
                            intensity_level, fitness_level, " . admin_class_goal_columns_sql() . ",
                            calories_burn_min, calories_burn_max, tdee_min, tdee_max, duration_minutes,
                            recommended_frequency_per_week, low_impact, joint_friendly, requires_equipment, is_active
                        ) VALUES (
                            ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?,
                            NULLIF(?, ''), NULLIF(?, ''), " . admin_class_goal_placeholders_sql() . ",
                            NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''),
                            NULLIF(?, ''), ?, ?, ?, ?
                        )"
                    );
                    if ($stmt) {
                        $stmt->bind_param(
                            'ssssssiisbiss' . admin_class_goal_bind_types() . 'ssssssiiii',
                            ...array_merge([
                                $payload['name'],
                                $payload['slug'],
                                $payload['category'],
                                $payload['description'],
                                $weeklySchedule,
                                $scheduleConfig,
                                $payload['max_participants'],
                                $payload['trainer_account_id'],
                                $upload['mime'],
                                $upload['data'],
                                $payload['active'],
                                $payload['intensity_level'],
                                $payload['fitness_level'],
                            ], admin_class_goal_values($payload), [
                                $payload['calories_burn_min'],
                                $payload['calories_burn_max'],
                                $payload['tdee_min'],
                                $payload['tdee_max'],
                                $payload['duration_minutes'],
                                $payload['recommended_frequency_per_week'],
                                $payload['low_impact'],
                                $payload['joint_friendly'],
                                $payload['requires_equipment'],
                                $payload['is_active'],
                            ])
                        );
                        $stmt->send_long_data(9, $upload['data']);
                    }
                } else {
                    $stmt = $conn->prepare(
                        "INSERT INTO classes_admin (
                            name, slug, category, description, weekly_schedule, schedule_config, max_participants,
                            trainer_account_id, active, intensity_level, fitness_level, " . admin_class_goal_columns_sql() . ",
                            calories_burn_min, calories_burn_max,
                            tdee_min, tdee_max, duration_minutes, recommended_frequency_per_week,
                            low_impact, joint_friendly, requires_equipment, is_active
                        ) VALUES (
                            ?, ?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), " . admin_class_goal_placeholders_sql() . ",
                            NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''),
                            NULLIF(?, ''), ?, ?, ?, ?
                        )"
                    );
                    if ($stmt) {
                        $stmt->bind_param(
                            'ssssssiiiss' . admin_class_goal_bind_types() . 'ssssssiiii',
                            ...array_merge([
                                $payload['name'],
                                $payload['slug'],
                                $payload['category'],
                                $payload['description'],
                                $weeklySchedule,
                                $scheduleConfig,
                                $payload['max_participants'],
                                $payload['trainer_account_id'],
                                $payload['active'],
                                $payload['intensity_level'],
                                $payload['fitness_level'],
                            ], admin_class_goal_values($payload), [
                                $payload['calories_burn_min'],
                                $payload['calories_burn_max'],
                                $payload['tdee_min'],
                                $payload['tdee_max'],
                                $payload['duration_minutes'],
                                $payload['recommended_frequency_per_week'],
                                $payload['low_impact'],
                                $payload['joint_friendly'],
                                $payload['requires_equipment'],
                                $payload['is_active'],
                            ])
                        );
                    }
                }

                if (!$stmt) {
                    $errors[] = 'Class form is not fully configured yet.';
                } elseif ($stmt->execute()) {
                    $successMessage = 'Class added successfully.';
                } else {
                    $errors[] = 'Unable to save the class. Check that the slug is unique.';
                }

                if (isset($stmt) && $stmt instanceof mysqli_stmt) {
                    $stmt->close();
                }
            }

            if ($action === 'update') {
                $classId = (int)($payload['id'] ?? 0);
                if ($classId <= 0) {
                    $errors[] = 'Invalid class selected for editing.';
                } else {
                    if ($upload['replace']) {
                        $stmt = $conn->prepare(
                            "UPDATE classes_admin SET
                                name = ?, slug = ?, category = ?, description = ?, weekly_schedule = ?, schedule_config = ?,
                                max_participants = ?, trainer_account_id = ?, image_path = NULL, image_mime = ?, image_data = ?,
                                active = ?, intensity_level = NULLIF(?, ''), fitness_level = NULLIF(?, ''),
                                " . admin_class_goal_update_assignments_sql() . ",
                                calories_burn_min = NULLIF(?, ''), calories_burn_max = NULLIF(?, ''), tdee_min = NULLIF(?, ''),
                                tdee_max = NULLIF(?, ''), duration_minutes = NULLIF(?, ''),
                                recommended_frequency_per_week = NULLIF(?, ''), low_impact = ?, joint_friendly = ?,
                                requires_equipment = ?, is_active = ?
                            WHERE id = ?"
                        );
                        if ($stmt) {
                            $stmt->bind_param(
                                'ssssssiisbiss' . admin_class_goal_bind_types() . 'ssssssiiiii',
                                ...array_merge([
                                    $payload['name'],
                                    $payload['slug'],
                                    $payload['category'],
                                    $payload['description'],
                                    $weeklySchedule,
                                    $scheduleConfig,
                                    $payload['max_participants'],
                                    $payload['trainer_account_id'],
                                    $upload['mime'],
                                    $upload['data'],
                                    $payload['active'],
                                    $payload['intensity_level'],
                                    $payload['fitness_level'],
                                ], admin_class_goal_values($payload), [
                                    $payload['calories_burn_min'],
                                    $payload['calories_burn_max'],
                                    $payload['tdee_min'],
                                    $payload['tdee_max'],
                                    $payload['duration_minutes'],
                                    $payload['recommended_frequency_per_week'],
                                    $payload['low_impact'],
                                    $payload['joint_friendly'],
                                    $payload['requires_equipment'],
                                    $payload['is_active'],
                                    $classId,
                                ])
                            );
                            $stmt->send_long_data(9, $upload['data']);
                        }
                    } else {
                        $stmt = $conn->prepare(
                            "UPDATE classes_admin SET
                                name = ?, slug = ?, category = ?, description = ?, weekly_schedule = ?, schedule_config = ?,
                                max_participants = ?, trainer_account_id = ?, active = ?,
                                intensity_level = NULLIF(?, ''), fitness_level = NULLIF(?, ''),
                                " . admin_class_goal_update_assignments_sql() . ",
                                calories_burn_min = NULLIF(?, ''), calories_burn_max = NULLIF(?, ''), tdee_min = NULLIF(?, ''),
                                tdee_max = NULLIF(?, ''), duration_minutes = NULLIF(?, ''),
                                recommended_frequency_per_week = NULLIF(?, ''), low_impact = ?, joint_friendly = ?,
                                requires_equipment = ?, is_active = ?
                            WHERE id = ?"
                        );
                        if ($stmt) {
                            $stmt->bind_param(
                                'ssssssiiiss' . admin_class_goal_bind_types() . 'ssssssiiiii',
                                ...array_merge([
                                    $payload['name'],
                                    $payload['slug'],
                                    $payload['category'],
                                    $payload['description'],
                                    $weeklySchedule,
                                    $scheduleConfig,
                                    $payload['max_participants'],
                                    $payload['trainer_account_id'],
                                    $payload['active'],
                                    $payload['intensity_level'],
                                    $payload['fitness_level'],
                                ], admin_class_goal_values($payload), [
                                    $payload['calories_burn_min'],
                                    $payload['calories_burn_max'],
                                    $payload['tdee_min'],
                                    $payload['tdee_max'],
                                    $payload['duration_minutes'],
                                    $payload['recommended_frequency_per_week'],
                                    $payload['low_impact'],
                                    $payload['joint_friendly'],
                                    $payload['requires_equipment'],
                                    $payload['is_active'],
                                    $classId,
                                ])
                            );
                        }
                    }

                    if (!$stmt) {
                        $errors[] = 'Edit form is not fully configured yet.';
                    } elseif ($stmt->execute()) {
                        $successMessage = 'Class updated successfully.';
                    } else {
                        $errors[] = 'Unable to update the class. Check that the slug is unique.';
                    }

                    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
                        $stmt->close();
                    }
                }
            }
        }
    }

    if ($action === 'toggle') {
        $stmt = $conn->prepare("UPDATE classes_admin SET active = IF(active = 1, 0, 1) WHERE id = ?");
        if ($stmt) {
            $classId = (int)($_POST['id'] ?? 0);
            $stmt->bind_param('i', $classId);
            $stmt->execute();
            $stmt->close();
            $successMessage = 'Class visibility updated.';
        }
    }

    if ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM classes_admin WHERE id = ?");
        if ($stmt) {
            $classId = (int)($_POST['id'] ?? 0);
            $stmt->bind_param('i', $classId);
            $stmt->execute();
            $stmt->close();
            $successMessage = 'Class deleted.';
        }
    }
}

$activeBookingWhere = fitgym_booking_active_sql();

$classes = $conn ? $conn->query(
    "SELECT c.*, a.name AS trainer_name,
            COALESCE(stats.total_bookings, 0) AS total_bookings,
            COALESCE(stats.total_clients, 0) AS total_clients
     FROM classes_admin c
     LEFT JOIN accounts a ON c.trainer_account_id = a.id AND a.role = 'trainer'
     LEFT JOIN (
        SELECT class_slug,
               COUNT(*) AS total_bookings,
               COALESCE(SUM(participants), 0) AS total_clients
        FROM bookings
        WHERE {$activeBookingWhere}
        GROUP BY class_slug
     ) stats ON stats.class_slug = c.slug
     ORDER BY c.created_at DESC"
) : false;
$trainers = $conn ? $conn->query("SELECT id, name FROM accounts WHERE role = 'trainer' AND active = 1 AND qualification_status = 'verified' ORDER BY name ASC") : false;

$rawClassRows = $classes ? $classes->fetch_all(MYSQLI_ASSOC) : [];
$classRows = [];
foreach ($rawClassRows as $row) {
    $normalizedRow = fitgym_normalize_class_row($row);
    $classRows[] = array_merge($row, $normalizedRow);
}

$trainerOptions = $trainers ? $trainers->fetch_all(MYSQLI_ASSOC) : [];
$activePrograms = count(array_filter($classRows, static fn(array $row): bool => (int)($row['active'] ?? 0) === 1));
$recommendablePrograms = count(array_filter($classRows, static fn(array $row): bool => (int)($row['is_active'] ?? 0) === 1));
$readyPrograms = count(array_filter($classRows, static fn(array $row): bool => !empty($row['recommendation_ready'])));

$editClassId = (int)($_GET['edit'] ?? ($_POST['id'] ?? 0));
$editClass = null;
foreach ($classRows as $row) {
    if ((int)($row['id'] ?? 0) === $editClassId) {
        $editClass = $row;
        break;
    }
}

$addFormValues = $currentAction === 'add' ? admin_class_form_payload($_POST) : admin_class_form_defaults();
$addScheduleRows = $currentAction === 'add'
    ? admin_class_schedule_rows_for_form($_POST)
    : [['mode' => 'single', 'day' => '', 'end_day' => '', 'time' => '']];

$editFormValues = $editClass ? array_merge(admin_class_form_defaults(), [
    'id' => (int)($editClass['id'] ?? 0),
    'name' => (string)($editClass['name'] ?? ''),
    'slug' => (string)($editClass['slug'] ?? ''),
    'category' => (string)($editClass['category'] ?? ''),
    'description' => (string)($editClass['description'] ?? ''),
    'trainer_account_id' => (int)($editClass['trainer_account_id'] ?? 0),
    'max_participants' => (int)($editClass['max_participants'] ?? 20),
    'active' => (int)($editClass['active'] ?? 1),
    'intensity_level' => (string)($editClass['intensity_level'] ?? ''),
    'fitness_level' => (string)($editClass['fitness_level'] ?? ''),
    'calories_burn_min' => (string)($editClass['calories_burn_min'] ?? ''),
    'calories_burn_max' => (string)($editClass['calories_burn_max'] ?? ''),
    'tdee_min' => (string)($editClass['tdee_min'] ?? ''),
    'tdee_max' => (string)($editClass['tdee_max'] ?? ''),
    'duration_minutes' => (string)($editClass['duration_minutes'] ?? ''),
    'recommended_frequency_per_week' => (string)($editClass['recommended_frequency_per_week'] ?? ''),
    'low_impact' => (int)($editClass['low_impact'] ?? 0),
    'joint_friendly' => (int)($editClass['joint_friendly'] ?? 0),
    'requires_equipment' => (int)($editClass['requires_equipment'] ?? 0),
    'is_active' => (int)($editClass['is_active'] ?? 0),
], admin_class_goal_payload($editClass)) : admin_class_form_defaults();
$editScheduleRows = $editClass
    ? admin_class_schedule_parse_rows((string)($editClass['schedule_config'] ?? ''), (string)($editClass['weekly_schedule'] ?? ''))
    : [['mode' => 'single', 'day' => '', 'end_day' => '', 'time' => '']];

if ($currentAction === 'update' && !empty($errors) && $editClass !== null && (int)($_POST['id'] ?? 0) === (int)($editClass['id'] ?? 0)) {
    $editFormValues = admin_class_form_payload($_POST);
    $editScheduleRows = admin_class_schedule_rows_for_form($_POST, $editScheduleRows);
}

if (empty($editScheduleRows)) {
    $editScheduleRows = [['mode' => 'single', 'day' => '', 'end_day' => '', 'time' => '']];
}
?>

<div class="page-header-row">
    <h2>Class / Program Management</h2>
    <div class="page-actions">
        <button type="button" class="btn-primary panel-toggle" data-panel="classAddPanel" aria-expanded="<?= $currentAction === 'add' ? 'true' : 'false' ?>">Add Class</button>
        <button type="button" class="btn-secondary panel-toggle" data-panel="classEditPanel" aria-expanded="<?= $editClass ? 'true' : 'false' ?>">Edit Class</button>
    </div>
</div>

<?php if ($successMessage !== ''): ?>
    <div class="alert"><?= esc($successMessage) ?></div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="alert-list">
            <?php foreach ($errors as $error): ?>
                <li><?= esc($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="stat-strip">
    <div class="stat-chip">
        <span>Total Programs</span>
        <strong><?= esc(count($classRows)) ?></strong>
    </div>
    <div class="stat-chip">
        <span>Visible Programs</span>
        <strong><?= esc($activePrograms) ?></strong>
    </div>
    <div class="stat-chip">
        <span>Recommendable Toggles On</span>
        <strong><?= esc($recommendablePrograms) ?></strong>
    </div>
    <div class="stat-chip">
        <span>Recommendation-Ready</span>
        <strong><?= esc($readyPrograms) ?></strong>
    </div>
</div>

<div id="classAddPanel" class="card collapsible-panel<?= $currentAction === 'add' ? ' is-open' : '' ?>">
    <div class="card-head">
        <h3>Create Class</h3>
        <p class="admin-note">Define the display details first, then complete the recommendation profile before turning recommendation eligibility on.</p>
    </div>
    <form method="POST" enctype="multipart/form-data" class="form-grid">
        <input type="hidden" name="action" value="add">

        <div class="form-section full-span">
            <div class="section-head">
                <h4>Basic Class Info</h4>
                <p>These fields drive class listing, trainer assignment, and booking.</p>
            </div>
            <div class="section-grid">
                <label>Class Name
                    <input name="name" value="<?= esc($addFormValues['name']) ?>" required>
                </label>
                <label>Slug
                    <input name="slug" value="<?= esc($addFormValues['slug']) ?>" required>
                    <small class="field-hint">Lowercase URL key. Example: yoga-flow or boxing-cardio.</small>
                </label>
                <label>Category
                    <select name="category" required>
                        <option value="">Select category</option>
                        <?php foreach ($categoryOptions as $value => $label): ?>
                            <option value="<?= esc($value) ?>" <?= $addFormValues['category'] === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Trainer
                    <select name="trainer_account_id" required>
                        <option value="">Select trainer</option>
                        <?php foreach ($trainerOptions as $trainer): ?>
                            <option value="<?= esc($trainer['id']) ?>" <?= (int)$addFormValues['trainer_account_id'] === (int)$trainer['id'] ? 'selected' : '' ?>><?= esc($trainer['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Max Participants
                    <input type="number" name="max_participants" min="1" value="<?= esc((string)$addFormValues['max_participants']) ?>" required>
                </label>
                <label>Visible to Users
                    <span class="checkbox-row"><input type="checkbox" name="active" value="1" <?= (int)$addFormValues['active'] === 1 ? 'checked' : '' ?>> Active class listing</span>
                    <small class="field-hint">Turn this off to hide the class from users and bookings.</small>
                </label>
                <label class="full-span">Description
                    <textarea name="description" rows="4" required><?= esc($addFormValues['description']) ?></textarea>
                </label>
                <label>Class Image
                    <input type="file" name="class_image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                    <small class="field-hint">Optional. JPG, PNG, and WEBP are supported.</small>
                </label>
            </div>
        </div>

        <div class="form-section full-span">
            <div class="section-head">
                <h4>Schedule</h4>
                <p>Add one or more regular class slots. Bookings use this schedule directly.</p>
            </div>
            <div id="scheduleRowsCreate" class="section-stack">
                <?php foreach ($addScheduleRows as $row): ?>
                    <div class="inline-actions schedule-row">
                        <select name="schedule_mode[]" class="schedule-mode">
                            <option value="single" <?= ($row['mode'] ?? 'single') === 'single' ? 'selected' : '' ?>>Single Day</option>
                            <option value="range" <?= ($row['mode'] ?? 'single') === 'range' ? 'selected' : '' ?>>Day Range</option>
                        </select>
                        <select name="schedule_day[]">
                            <option value="">Start day</option>
                            <?php foreach ($weekdayOptions as $short => $full): ?>
                                <option value="<?= esc($short) ?>" <?= ($row['day'] ?? '') === $short ? 'selected' : '' ?>><?= esc($full) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="schedule_end_day[]" class="schedule-end-day" style="<?= ($row['mode'] ?? 'single') === 'range' ? '' : 'display:none;' ?>">
                            <option value="">End day</option>
                            <?php foreach ($weekdayOptions as $short => $full): ?>
                                <option value="<?= esc($short) ?>" <?= ($row['end_day'] ?? '') === $short ? 'selected' : '' ?>><?= esc($full) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="schedule_time[]">
                            <option value="">Select time slot</option>
                            <?php foreach ($scheduleTimeOptions as $option): ?>
                                <option value="<?= esc($option) ?>" <?= ($row['time'] ?? '') === $option ? 'selected' : '' ?>><?= esc($option) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn-danger remove-schedule">Remove</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn-secondary add-schedule" data-target="scheduleRowsCreate">Add Schedule Row</button>
        </div>

        <div class="form-section full-span">
            <div class="section-head">
                <h4>Recommendation Profile</h4>
                <p>Only classes with a complete and valid profile can be used by the TDEE recommendation engine.</p>
            </div>
            <div class="section-grid">
                <label>Intensity Level
                    <select name="intensity_level">
                        <option value="">Select intensity</option>
                        <option value="low" <?= $addFormValues['intensity_level'] === 'low' ? 'selected' : '' ?>>Low</option>
                        <option value="medium" <?= $addFormValues['intensity_level'] === 'medium' ? 'selected' : '' ?>>Medium</option>
                        <option value="high" <?= $addFormValues['intensity_level'] === 'high' ? 'selected' : '' ?>>High</option>
                    </select>
                    <small class="field-hint">Expected effort level for a normal session.</small>
                </label>
                <label>Fitness Level
                    <select name="fitness_level">
                        <option value="">Select fitness level</option>
                        <option value="beginner" <?= $addFormValues['fitness_level'] === 'beginner' ? 'selected' : '' ?>>Beginner</option>
                        <option value="intermediate" <?= $addFormValues['fitness_level'] === 'intermediate' ? 'selected' : '' ?>>Intermediate</option>
                        <option value="advanced" <?= $addFormValues['fitness_level'] === 'advanced' ? 'selected' : '' ?>>Advanced</option>
                    </select>
                    <small class="field-hint">Highest user level this class is designed for.</small>
                </label>
                <label>Calories Burn Min
                    <input type="number" name="calories_burn_min" min="0" value="<?= esc($addFormValues['calories_burn_min']) ?>">
                    <small class="field-hint">Approximate minimum calories burned per session.</small>
                </label>
                <label>Calories Burn Max
                    <input type="number" name="calories_burn_max" min="0" value="<?= esc($addFormValues['calories_burn_max']) ?>">
                    <small class="field-hint">Approximate maximum calories burned per session.</small>
                </label>
                <label>Duration Minutes
                    <input type="number" name="duration_minutes" min="1" value="<?= esc($addFormValues['duration_minutes']) ?>">
                    <small class="field-hint">Canonical session duration used for matching and display.</small>
                </label>
                <label>Recommended Frequency / Week
                    <input type="number" name="recommended_frequency_per_week" min="1" max="7" value="<?= esc($addFormValues['recommended_frequency_per_week']) ?>">
                    <small class="field-hint">Ideal weekly sessions for this class.</small>
                </label>
                <label>TDEE Min (optional)
                    <input type="number" name="tdee_min" min="0" value="<?= esc($addFormValues['tdee_min']) ?>">
                    <small class="field-hint">Optional broad eligibility band. Not the main score.</small>
                </label>
                <label>TDEE Max (optional)
                    <input type="number" name="tdee_max" min="0" value="<?= esc($addFormValues['tdee_max']) ?>">
                    <small class="field-hint">Leave blank unless the class truly fits a broad TDEE range.</small>
                </label>
                <div class="full-span">
                    <label>Goal Suitability</label>
                    <div class="inline-actions">
                        <?php foreach (fitgym_goal_options() as $goalKey => $goalLabel): ?>
                            <?php $goalColumn = fitgym_goal_flag_columns()[$goalKey] ?? ''; ?>
                            <label><input type="checkbox" name="<?= esc($goalColumn) ?>" value="1" <?= (int)($addFormValues[$goalColumn] ?? 0) === 1 ? 'checked' : '' ?>> <?= esc($goalLabel) ?></label>
                        <?php endforeach; ?>
                    </div>
                    <small class="field-hint">Choose every outcome this class genuinely supports. At least one is required for a recommendable class.</small>
                </div>
                <div class="full-span">
                    <label>Safety / Access</label>
                    <div class="inline-actions">
                        <label><input type="checkbox" name="low_impact" value="1" <?= (int)$addFormValues['low_impact'] === 1 ? 'checked' : '' ?>> Low Impact</label>
                        <label><input type="checkbox" name="joint_friendly" value="1" <?= (int)$addFormValues['joint_friendly'] === 1 ? 'checked' : '' ?>> Joint Friendly</label>
                        <label><input type="checkbox" name="requires_equipment" value="1" <?= (int)$addFormValues['requires_equipment'] === 1 ? 'checked' : '' ?>> Requires Equipment</label>
                    </div>
                </div>
                <div class="full-span">
                    <label>Recommendation Eligibility</label>
                    <span class="checkbox-row"><input type="checkbox" name="is_active" value="1" <?= (int)$addFormValues['is_active'] === 1 ? 'checked' : '' ?>> Mark class as recommendable</span>
                    <small class="field-hint">Saving will fail if this is on and the recommendation profile is incomplete or invalid.</small>
                </div>
            </div>
        </div>

        <div class="full-span">
            <button class="btn-primary" type="submit">Save Class</button>
        </div>
    </form>
</div>

<div id="classEditPanel" class="card collapsible-panel<?= $editClass ? ' is-open' : '' ?>">
    <div class="card-head">
        <h3>Edit Class</h3>
        <p class="admin-note">Load a class from the list below to update the operational details and recommendation profile together.</p>
    </div>
    <?php if ($editClass === null): ?>
        <div class="empty-state">Choose <code>Edit</code> on a class row to update it here.</div>
    <?php else: ?>
        <form method="POST" enctype="multipart/form-data" class="form-grid">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?= esc($editFormValues['id']) ?>">

            <div class="form-section full-span">
                <div class="section-head">
                    <h4>Basic Class Info</h4>
                    <p>Update the listing details shown across classes, booking, and trainer views.</p>
                </div>
                <div class="section-grid">
                    <label>Class Name
                        <input name="name" value="<?= esc($editFormValues['name']) ?>" required>
                    </label>
                    <label>Slug
                        <input name="slug" value="<?= esc($editFormValues['slug']) ?>" required>
                    </label>
                    <label>Category
                        <select name="category" required>
                            <option value="">Select category</option>
                            <?php foreach ($categoryOptions as $value => $label): ?>
                                <option value="<?= esc($value) ?>" <?= $editFormValues['category'] === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Trainer
                        <select name="trainer_account_id" required>
                            <option value="">Select trainer</option>
                            <?php foreach ($trainerOptions as $trainer): ?>
                                <option value="<?= esc($trainer['id']) ?>" <?= (int)$editFormValues['trainer_account_id'] === (int)$trainer['id'] ? 'selected' : '' ?>><?= esc($trainer['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Max Participants
                        <input type="number" name="max_participants" min="1" value="<?= esc((string)$editFormValues['max_participants']) ?>" required>
                    </label>
                    <label>Visible to Users
                        <span class="checkbox-row"><input type="checkbox" name="active" value="1" <?= (int)$editFormValues['active'] === 1 ? 'checked' : '' ?>> Active class listing</span>
                    </label>
                    <label class="full-span">Description
                        <textarea name="description" rows="4" required><?= esc($editFormValues['description']) ?></textarea>
                    </label>
                    <label>Replace Class Image
                        <input type="file" name="class_image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                        <small class="field-hint">Leave blank to keep the current stored image.</small>
                    </label>
                </div>
            </div>

            <div class="form-section full-span">
                <div class="section-head">
                    <h4>Schedule</h4>
                    <p>Maintain the recurring class schedule used by booking and class listings.</p>
                </div>
                <div id="scheduleRowsEdit" class="section-stack">
                    <?php foreach ($editScheduleRows as $row): ?>
                        <div class="inline-actions schedule-row">
                            <select name="schedule_mode[]" class="schedule-mode">
                                <option value="single" <?= ($row['mode'] ?? 'single') === 'single' ? 'selected' : '' ?>>Single Day</option>
                                <option value="range" <?= ($row['mode'] ?? 'single') === 'range' ? 'selected' : '' ?>>Day Range</option>
                            </select>
                            <select name="schedule_day[]">
                                <option value="">Start day</option>
                                <?php foreach ($weekdayOptions as $short => $full): ?>
                                    <option value="<?= esc($short) ?>" <?= ($row['day'] ?? '') === $short ? 'selected' : '' ?>><?= esc($full) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="schedule_end_day[]" class="schedule-end-day" style="<?= ($row['mode'] ?? 'single') === 'range' ? '' : 'display:none;' ?>">
                                <option value="">End day</option>
                                <?php foreach ($weekdayOptions as $short => $full): ?>
                                    <option value="<?= esc($short) ?>" <?= ($row['end_day'] ?? '') === $short ? 'selected' : '' ?>><?= esc($full) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="schedule_time[]">
                                <option value="">Select time slot</option>
                                <?php foreach ($scheduleTimeOptions as $option): ?>
                                    <option value="<?= esc($option) ?>" <?= ($row['time'] ?? '') === $option ? 'selected' : '' ?>><?= esc($option) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn-danger remove-schedule">Remove</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn-secondary add-schedule" data-target="scheduleRowsEdit">Add Schedule Row</button>
            </div>

            <div class="form-section full-span">
                <div class="section-head">
                    <h4>Recommendation Profile</h4>
                    <p>These fields control whether the TDEE matcher can recommend the class and how strongly it ranks.</p>
                </div>
                <div class="section-grid">
                    <label>Intensity Level
                        <select name="intensity_level">
                            <option value="">Select intensity</option>
                            <option value="low" <?= $editFormValues['intensity_level'] === 'low' ? 'selected' : '' ?>>Low</option>
                            <option value="medium" <?= $editFormValues['intensity_level'] === 'medium' ? 'selected' : '' ?>>Medium</option>
                            <option value="high" <?= $editFormValues['intensity_level'] === 'high' ? 'selected' : '' ?>>High</option>
                        </select>
                    </label>
                    <label>Fitness Level
                        <select name="fitness_level">
                            <option value="">Select fitness level</option>
                            <option value="beginner" <?= $editFormValues['fitness_level'] === 'beginner' ? 'selected' : '' ?>>Beginner</option>
                            <option value="intermediate" <?= $editFormValues['fitness_level'] === 'intermediate' ? 'selected' : '' ?>>Intermediate</option>
                            <option value="advanced" <?= $editFormValues['fitness_level'] === 'advanced' ? 'selected' : '' ?>>Advanced</option>
                        </select>
                    </label>
                    <label>Calories Burn Min
                        <input type="number" name="calories_burn_min" min="0" value="<?= esc($editFormValues['calories_burn_min']) ?>">
                    </label>
                    <label>Calories Burn Max
                        <input type="number" name="calories_burn_max" min="0" value="<?= esc($editFormValues['calories_burn_max']) ?>">
                    </label>
                    <label>Duration Minutes
                        <input type="number" name="duration_minutes" min="1" value="<?= esc($editFormValues['duration_minutes']) ?>">
                    </label>
                    <label>Recommended Frequency / Week
                        <input type="number" name="recommended_frequency_per_week" min="1" max="7" value="<?= esc($editFormValues['recommended_frequency_per_week']) ?>">
                    </label>
                    <label>TDEE Min (optional)
                        <input type="number" name="tdee_min" min="0" value="<?= esc($editFormValues['tdee_min']) ?>">
                    </label>
                    <label>TDEE Max (optional)
                        <input type="number" name="tdee_max" min="0" value="<?= esc($editFormValues['tdee_max']) ?>">
                    </label>
                    <div class="full-span">
                        <label>Goal Suitability</label>
                        <div class="inline-actions">
                            <?php foreach (fitgym_goal_options() as $goalKey => $goalLabel): ?>
                                <?php $goalColumn = fitgym_goal_flag_columns()[$goalKey] ?? ''; ?>
                                <label><input type="checkbox" name="<?= esc($goalColumn) ?>" value="1" <?= (int)($editFormValues[$goalColumn] ?? 0) === 1 ? 'checked' : '' ?>> <?= esc($goalLabel) ?></label>
                            <?php endforeach; ?>
                        </div>
                        <small class="field-hint">The recommendation engine can only use goals that are explicitly enabled here.</small>
                    </div>
                    <div class="full-span">
                        <label>Safety / Access</label>
                        <div class="inline-actions">
                            <label><input type="checkbox" name="low_impact" value="1" <?= (int)$editFormValues['low_impact'] === 1 ? 'checked' : '' ?>> Low Impact</label>
                            <label><input type="checkbox" name="joint_friendly" value="1" <?= (int)$editFormValues['joint_friendly'] === 1 ? 'checked' : '' ?>> Joint Friendly</label>
                            <label><input type="checkbox" name="requires_equipment" value="1" <?= (int)$editFormValues['requires_equipment'] === 1 ? 'checked' : '' ?>> Requires Equipment</label>
                        </div>
                    </div>
                    <div class="full-span">
                        <label>Recommendation Eligibility</label>
                        <span class="checkbox-row"><input type="checkbox" name="is_active" value="1" <?= (int)$editFormValues['is_active'] === 1 ? 'checked' : '' ?>> Mark class as recommendable</span>
                        <small class="field-hint">Saving fails if recommendability is on and the profile is incomplete.</small>
                    </div>
                </div>
            </div>

            <div class="full-span">
                <button class="btn-primary" type="submit">Update Class</button>
                <a class="btn-secondary" href="<?= esc(fitgym_url('/php/admin/classes.php')) ?>">Cancel</a>
            </div>
        </form>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-head">
        <h3>Program List</h3>
        <p class="admin-note">Recommendation readiness is calculated from the same backend rules used by the TDEE matcher.</p>
    </div>

    <?php if (empty($classRows)): ?>
        <div class="empty-state">No classes have been created yet.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Program</th>
                        <th>Image</th>
                        <th>Bookings</th>
                        <th>Schedule</th>
                        <th>Recommendation Profile</th>
                        <th>Trainer</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($classRows as $row): ?>
                        <tr>
                            <td>
                                <strong><?= esc($row['name']) ?></strong>
                                <div class="meta-list">
                                    <span>Slug: <?= esc($row['slug']) ?></span>
                                    <span><?= esc($row['category_label']) ?></span>
                                    <span><?= esc($row['fitness_level_label']) ?> | <?= esc((string)($row['duration_minutes'] ?? 0)) ?> min</span>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($row['image_path'])): ?>
                                    <div class="meta-list">
                                        <span>Uploaded</span>
                                        <span><?= esc($row['image_path']) ?></span>
                                    </div>
                                <?php elseif (!empty($row['image_data'])): ?>
                                    <span class="badge success">Stored in DB</span>
                                <?php else: ?>
                                    <span class="muted">Auto fallback</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="meta-list">
                                    <span><?= esc((string)($row['total_bookings'] ?? 0)) ?> booking(s)</span>
                                    <span><?= esc((string)($row['total_clients'] ?? 0)) ?> user(s)</span>
                                </div>
                            </td>
                            <td><?= esc($row['weekly_schedule'] ?: 'Schedule not set') ?></td>
                            <td>
                                <div class="meta-list">
                                    <span><?= esc((string)($row['intensity_level'] ?: 'No intensity')) ?> intensity</span>
                                    <span>
                                        <?php if ($row['calories_burn_min'] !== null || $row['calories_burn_max'] !== null): ?>
                                            <?= esc((string)($row['calories_burn_min'] ?? $row['calories_burn_max'])) ?> - <?= esc((string)($row['calories_burn_max'] ?? $row['calories_burn_min'])) ?> kcal/session
                                        <?php else: ?>
                                            Calorie range missing
                                        <?php endif; ?>
                                    </span>
                                    <span><?= esc((string)($row['recommended_frequency_per_week'] ?? '0')) ?> sessions/week target</span>
                                </div>
                            </td>
                            <td><?= esc($row['trainer_name'] ?: 'No trainer assigned') ?></td>
                            <td>
                                <div class="meta-list">
                                    <span class="badge <?= (int)$row['active'] === 1 ? 'success' : 'danger' ?>"><?= (int)$row['active'] === 1 ? 'Visible' : 'Hidden' ?></span>
                                    <span class="badge <?= (int)$row['is_active'] === 1 ? 'warning' : 'danger' ?>"><?= (int)$row['is_active'] === 1 ? 'Recommendable toggle on' : 'Recommendable toggle off' ?></span>
                                    <span class="badge <?= !empty($row['recommendation_ready']) ? 'success' : 'danger' ?>"><?= !empty($row['recommendation_ready']) ? 'Recommendation-ready' : 'Not ready' ?></span>
                                </div>
                            </td>
                            <td class="actions-cell">
                                <div class="inline-actions">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?= esc($row['id']) ?>">
                                        <button class="btn-secondary" type="submit"><?= (int)$row['active'] === 1 ? 'Hide' : 'Show' ?></button>
                                    </form>
                                    <a class="btn-secondary" href="<?= esc(fitgym_url('/php/admin/classes.php')) ?>?edit=<?= esc($row['id']) ?>#classEditPanel">Edit</a>
                                    <form method="POST" onsubmit="return confirm('Delete this class?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= esc($row['id']) ?>">
                                        <button class="btn-danger" type="submit">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const scheduleRowTemplate = <?= json_encode('<div class="inline-actions schedule-row"><select name="schedule_mode[]" class="schedule-mode"><option value="single">Single Day</option><option value="range">Day Range</option></select><select name="schedule_day[]"><option value="">Start day</option>' .
        implode('', array_map(static function ($short, $full) {
            return '<option value="' . htmlspecialchars($short, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($full, ENT_QUOTES, 'UTF-8') . '</option>';
        }, array_keys($weekdayOptions), array_values($weekdayOptions))) .
        '</select><select name="schedule_end_day[]" class="schedule-end-day" style="display:none;"><option value="">End day</option>' .
        implode('', array_map(static function ($short, $full) {
            return '<option value="' . htmlspecialchars($short, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($full, ENT_QUOTES, 'UTF-8') . '</option>';
        }, array_keys($weekdayOptions), array_values($weekdayOptions))) .
        '</select><select name="schedule_time[]"><option value="">Select time slot</option>' .
        implode('', array_map(static function ($option) {
            return '<option value="' . htmlspecialchars($option, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($option, ENT_QUOTES, 'UTF-8') . '</option>';
        }, $scheduleTimeOptions)) .
        '</select><button type="button" class="btn-danger remove-schedule">Remove</button></div>') ?>;

    function syncScheduleRow(row) {
        const mode = row ? row.querySelector('.schedule-mode') : null;
        const endDay = row ? row.querySelector('.schedule-end-day') : null;
        if (!mode || !endDay) return;
        const isRange = mode.value === 'range';
        endDay.style.display = isRange ? '' : 'none';
        if (!isRange) {
            endDay.value = '';
        }
    }

    document.querySelectorAll('.panel-toggle').forEach(function (button) {
        button.addEventListener('click', function () {
            const panel = document.getElementById(button.dataset.panel || '');
            if (!panel) return;
            const open = panel.classList.toggle('is-open');
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    });

    document.querySelectorAll('.add-schedule').forEach(function (button) {
        button.addEventListener('click', function () {
            const target = document.getElementById(button.dataset.target || '');
            if (!target) return;
            target.insertAdjacentHTML('beforeend', scheduleRowTemplate);
            const rows = target.querySelectorAll('.schedule-row');
            if (rows.length > 0) {
                syncScheduleRow(rows[rows.length - 1]);
            }
        });
    });

    document.addEventListener('click', function (event) {
        const trigger = event.target.closest('.remove-schedule');
        if (!trigger) return;
        const row = trigger.closest('.schedule-row');
        const parent = row ? row.parentElement : null;
        if (!row || !parent) return;
        if (parent.querySelectorAll('.schedule-row').length <= 1) {
            row.querySelectorAll('select').forEach(function (select) {
                select.value = '';
            });
            syncScheduleRow(row);
            return;
        }
        row.remove();
    });

    document.addEventListener('change', function (event) {
        if (!event.target.classList.contains('schedule-mode')) return;
        syncScheduleRow(event.target.closest('.schedule-row'));
    });

    document.querySelectorAll('.schedule-row').forEach(syncScheduleRow);

    <?php if ($editClass): ?>
    const editPanel = document.getElementById('classEditPanel');
    if (editPanel) {
        editPanel.classList.add('is-open');
    }
    <?php endif; ?>
});
</script>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
