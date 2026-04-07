<?php
session_start();
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/dynamic_content.php';
require_once __DIR__ . '/auth_common.php';
require_once __DIR__ . '/payment_helpers.php';

if (fitgym_current_role() === null) {
    $requestUri = (string)($_SERVER['REQUEST_URI'] ?? fitgym_url('/php/book_class.php'));
    fitgym_redirect(fitgym_url('/php/login.php') . '?next=' . rawurlencode($requestUri));
}

fitgym_require_role('client');

if ($conn && function_exists('fitgym_table_has_column') && !fitgym_table_has_column('bookings', 'trainer_type')) {
    $conn->query("ALTER TABLE bookings ADD COLUMN trainer_type VARCHAR(20) NOT NULL DEFAULT 'regular' AFTER trainer_name");
}

fitgym_bootstrap_booking_payment_columns();
$activeBookingWhere = fitgym_booking_active_sql();

if (!function_exists('fitgym_booking_price_breakdown')) {
    function fitgym_booking_price_breakdown(array $classRow, string $trainerType): array
    {
        $baseRupees = fitgym_parse_price_rupees($classRow['price'] ?? 2000);
        $multiplier = $trainerType === 'private' ? 3 : 1;
        $sessionRupees = $baseRupees * $multiplier;

        return [
            'base_rupees' => $baseRupees,
            'multiplier' => $multiplier,
            'session_rupees' => $sessionRupees,
            'session_paisa' => $sessionRupees * 100,
            'session_label' => fitgym_price_label_from_rupees($sessionRupees),
            'price_title' => $trainerType === 'private' ? 'Private Session Price' : 'Class Price',
            'price_hint' => $trainerType === 'private'
                ? 'Private trainer sessions are charged at 3x the regular class price.'
                : '',
        ];
    }
}

$fallbackClasses = [
    'zumba' => [
        'slug' => 'zumba',
        'title' => 'Zumba',
        'description' => 'Dance-cardio intervals with easy choreography, upbeat music, and reliable calorie burn for general fitness.',
        'category_label' => 'Dance',
        'intensity_level' => 'medium',
        'fitness_level' => 'beginner',
        'fitness_level_label' => 'Beginner',
        'goal_fat_loss' => 1,
        'goal_maintenance' => 1,
        'goal_muscle_gain' => 0,
        'goal_endurance' => 1,
        'goal_mobility' => 0,
        'goal_flexibility' => 0,
        'goal_stress_relief' => 0,
        'calories_burn_min' => 280,
        'calories_burn_max' => 400,
        'recommended_frequency_per_week' => 3,
        'low_impact' => 0,
        'joint_friendly' => 0,
        'requires_equipment' => 0,
        'image' => fitgym_url('/pictures/zumba.jpg'),
        'trainer' => 'Ram Tamang',
        'location' => 'Hall A',
        'duration_minutes' => 60,
        'price' => 'NPR 2000',
        'max_participants' => 20,
        'schedule_slots' => [
            ['day' => 'Mon', 'time' => '6:00-7:00 AM'],
            ['day' => 'Wed', 'time' => '6:00-7:00 AM'],
            ['day' => 'Fri', 'time' => '6:00-7:00 AM'],
        ],
    ],
];

$classMap = [];
foreach (fitgym_get_classes() as $item) {
    $slug = trim((string)($item['slug'] ?? ''));
    if ($slug === '') {
        continue;
    }
    $classRow = [
        'slug' => $slug,
        'title' => (string)($item['title'] ?? 'Class'),
        'description' => trim((string)($item['description'] ?? '')),
        'category_label' => trim((string)($item['category_label'] ?? fitgym_labelize_token((string)($item['category'] ?? '')))),
        'intensity_level' => fitgym_normalize_intensity((string)($item['intensity_level'] ?? '')),
        'fitness_level' => fitgym_normalize_fitness_level((string)($item['fitness_level'] ?? '')),
        'fitness_level_label' => (string)($item['fitness_level_label'] ?? fitgym_labelize_token((string)($item['fitness_level'] ?? ''))),
        'calories_burn_min' => fitgym_nullable_int($item['calories_burn_min'] ?? null, 0),
        'calories_burn_max' => fitgym_nullable_int($item['calories_burn_max'] ?? null, 0),
        'recommended_frequency_per_week' => fitgym_nullable_int($item['recommended_frequency_per_week'] ?? null, 1),
        'low_impact' => (int)($item['low_impact'] ?? 0),
        'joint_friendly' => (int)($item['joint_friendly'] ?? 0),
        'requires_equipment' => (int)($item['requires_equipment'] ?? 0),
        'image' => (string)($item['image'] ?? fitgym_url('/pictures/workout.jpg')),
        'trainer' => (string)($item['trainer'] ?? 'TBA'),
        'location' => (string)($item['location'] ?? 'Main Studio'),
        'duration_minutes' => (int)($item['duration_minutes'] ?? $item['duration_min'] ?? 45),
        'price' => 'NPR 2000',
        'max_participants' => max(1, (int)($item['max_participants'] ?? 20)),
        'schedule_slots' => (array)($item['schedule_slots'] ?? []),
    ];

    foreach (fitgym_goal_flag_columns() as $goalColumn) {
        $classRow[$goalColumn] = (int)($item[$goalColumn] ?? 0);
    }

    $classMap[$slug] = $classRow;
}

if (empty($classMap)) {
    $classMap = $fallbackClasses;
}

$verifiedTrainers = [];
if ($conn instanceof mysqli) {
    $trainerQuery = $conn->query("SELECT id, name FROM accounts WHERE role = 'trainer' AND active = 1 AND qualification_status = 'verified' ORDER BY name ASC");
    if ($trainerQuery) {
        while ($trainerRow = $trainerQuery->fetch_assoc()) {
            $trainerName = trim((string)($trainerRow['name'] ?? ''));
            if ($trainerName !== '') {
                $verifiedTrainers[] = [
                    'id' => (int)($trainerRow['id'] ?? 0),
                    'name' => $trainerName,
                ];
            }
        }
        $trainerQuery->free();
    }
}

$verifiedTrainerNames = array_map(static fn($row) => (string)$row['name'], $verifiedTrainers);
$class = trim((string)($_GET['class'] ?? $_POST['class_type'] ?? array_key_first($classMap)));
if (!isset($classMap[$class])) {
    $class = array_key_first($classMap);
}
$data = $classMap[$class];

$currentAccountId = (int)($_SESSION['auth_id'] ?? $_SESSION['user_id'] ?? 0);
$userName = trim((string)($_SESSION['auth_name'] ?? $_SESSION['user_name'] ?? ''));
$userEmail = trim((string)($_SESSION['auth_email'] ?? $_SESSION['user_email'] ?? ''));
$userPhone = '';

if ($conn instanceof mysqli && $currentAccountId > 0) {
    $accountStmt = $conn->prepare(
        "SELECT name, email, phone
         FROM accounts
         WHERE id = ?
           AND role = 'client'
           AND active = 1
         LIMIT 1"
    );
    if ($accountStmt) {
        $accountStmt->bind_param('i', $currentAccountId);
        $accountStmt->execute();
        $accountRow = $accountStmt->get_result()->fetch_assoc();
        $accountStmt->close();

        if ($accountRow) {
            $userName = trim((string)($accountRow['name'] ?? $userName));
            $userEmail = trim((string)($accountRow['email'] ?? $userEmail));
            $userPhone = trim((string)($accountRow['phone'] ?? ''));
        }
    }
}

if ($userEmail === '') {
    fitgym_clear_auth_session();
    fitgym_redirect(fitgym_url('/php/login.php'));
}

$successMessage = '';
if (!empty($_SESSION['booking_success_flash']) && is_array($_SESSION['booking_success_flash'])) {
    $flash = $_SESSION['booking_success_flash'];
    unset($_SESSION['booking_success_flash']);
    if ((string)($flash['class_slug'] ?? '') === $class) {
        $successMessage = (string)($flash['message'] ?? '');
    }
}

$errors = [];
$success = $successMessage !== '';

$fullName = $userName !== '' ? $userName : trim((string)($_POST['full_name'] ?? ''));
$email = preg_replace('/[\p{Z}\s\x00-\x1F\x7F]+/u', '', $userEmail);
$contactNumber = trim((string)($_POST['contact_number'] ?? $userPhone));
$classType = trim((string)($_POST['class_type'] ?? $class));
if (!isset($classMap[$classType])) {
    $classType = $class;
}
$selectedClass = $classMap[$classType];
$trainerType = trim((string)($_POST['trainer_type'] ?? 'regular'));
if (!in_array($trainerType, ['regular', 'private'], true)) {
    $trainerType = 'regular';
}
$selectedRegularSlot = trim((string)($_POST['regular_schedule'] ?? ''));
$privateDate = trim((string)($_POST['private_date'] ?? ''));
$privateTimeFromRaw = trim((string)($_POST['private_time_from'] ?? ''));
$privateTimeToRaw = trim((string)($_POST['private_time_to'] ?? ''));
$selectedPrivateTrainer = trim((string)($_POST['private_trainer_name'] ?? ''));
$participants = 1;
$paymentMethod = trim((string)($_POST['payment'] ?? 'cash'));
$khaltiEnabled = fitgym_khalti_is_configured();
if (!$khaltiEnabled && $paymentMethod === 'khalti') {
    $paymentMethod = 'cash';
}

$regularScheduleOptions = fitgym_schedule_upcoming_options((array)($selectedClass['schedule_slots'] ?? []), 28);
$regularScheduleMap = [];
foreach ($regularScheduleOptions as $option) {
    $regularScheduleMap[$option['key']] = $option;
}
$hasRegularTrainer = trim((string)($selectedClass['trainer'] ?? '')) !== '' && trim((string)($selectedClass['trainer'] ?? '')) !== 'TBA';
$hasRegularMode = $hasRegularTrainer && !empty($regularScheduleOptions);
if (!$hasRegularMode) {
    $trainerType = 'private';
}

$pricing = fitgym_booking_price_breakdown($selectedClass, $trainerType);
$selectedClass['base_price_rupees'] = (int)$pricing['base_rupees'];
$selectedClass['price_multiplier'] = (int)$pricing['multiplier'];
$selectedClass['price_rupees'] = (int)$pricing['session_rupees'];
$selectedClass['price_paisa'] = (int)$pricing['session_paisa'];
$selectedClass['price'] = (string)$pricing['session_label'];
$selectedClass['price_title'] = (string)$pricing['price_title'];
$selectedClass['price_hint'] = (string)$pricing['price_hint'];

$regularSeatUsage = [];
if ($conn instanceof mysqli && !empty($regularScheduleOptions)) {
    $optionDates = array_values(array_unique(array_map(static fn($row) => (string)$row['date'], $regularScheduleOptions)));
    if (!empty($optionDates)) {
        $placeholders = implode(',', array_fill(0, count($optionDates), '?'));
        $usageStmt = $conn->prepare(
            "SELECT preferred_date, time_slot, COALESCE(SUM(participants), 0) AS total_clients
             FROM bookings
             WHERE class_slug = ?
               AND trainer_type = 'regular'
               AND preferred_date IN ({$placeholders})
               AND {$activeBookingWhere}
             GROUP BY preferred_date, time_slot"
        );
        if ($usageStmt) {
            $types = 's' . str_repeat('s', count($optionDates));
            $params = array_merge([$selectedClass['slug']], $optionDates);
            $usageStmt->bind_param($types, ...$params);
            $usageStmt->execute();
            $usageResult = $usageStmt->get_result();
            while ($usageRow = $usageResult->fetch_assoc()) {
                $usageKey = (string)$usageRow['preferred_date'] . '|' . (string)$usageRow['time_slot'];
                $regularSeatUsage[$usageKey] = (int)($usageRow['total_clients'] ?? 0);
            }
            $usageStmt->close();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($db_error) || !$conn) {
        $errors[] = 'Database is not connected. Please check your database setup.';
    }
    if ($currentAccountId <= 0 || $userEmail === '') {
        $errors[] = 'Please log in with a client account before booking a class.';
    }
    if ($fullName === '' || mb_strlen($fullName) > 100) {
        $errors[] = 'Please enter a valid full name (max 100 characters).';
    }
    if ($contactNumber === '' || !preg_match('/^[0-9+\-\s]{7,20}$/', $contactNumber)) {
        $errors[] = 'Please enter a valid contact number.';
    }
    if (!isset($classMap[$classType])) {
        $errors[] = 'Please select a valid class type.';
    }
    if (!in_array($paymentMethod, ['cash', 'khalti'], true)) {
        $errors[] = 'Please select a valid payment method.';
    }
    if ($paymentMethod === 'khalti' && !$khaltiEnabled) {
        $errors[] = 'Khalti sandbox checkout is not configured yet. Add the sandbox secret key in admin settings first.';
    }

    $preferredDate = '';
    $timeSlot = '';
    $selectedTrainer = '';

    if ($trainerType === 'regular') {
        if ($selectedClass['trainer'] === '' || $selectedClass['trainer'] === 'TBA') {
            $errors[] = 'This class does not have a regular trainer assigned yet.';
        }
        if (!isset($regularScheduleMap[$selectedRegularSlot])) {
            $errors[] = 'Please select one of the available regular class schedules.';
        } else {
            $preferredDate = (string)$regularScheduleMap[$selectedRegularSlot]['date'];
            $timeSlot = (string)$regularScheduleMap[$selectedRegularSlot]['time'];
            $selectedTrainer = (string)$selectedClass['trainer'];
        }
    } else {
        $participants = 1;
        if (!in_array($selectedPrivateTrainer, $verifiedTrainerNames, true)) {
            $errors[] = 'Please select a valid private trainer.';
        }
        $dateObj = DateTime::createFromFormat('Y-m-d', $privateDate);
        $dateErrors = DateTime::getLastErrors();
        if ($privateDate === '' || !$dateObj || $dateErrors['warning_count'] || $dateErrors['error_count']) {
            $errors[] = 'Please select a valid private session date.';
        } else {
            $today = new DateTime('today');
            if ($dateObj < $today) {
                $errors[] = 'Private session date must be today or later.';
            }
        }
        $privateStartObj = null;
        $privateEndObj = null;
        if (!preg_match('/^\d{2}:\d{2}$/', $privateTimeFromRaw)) {
            $errors[] = 'Please select a valid private session start time.';
        } else {
            $privateStartObj = DateTime::createFromFormat('H:i', $privateTimeFromRaw);
            if (!$privateStartObj) {
                $errors[] = 'Please select a valid private session start time.';
            }
        }
        if (!preg_match('/^\d{2}:\d{2}$/', $privateTimeToRaw)) {
            $errors[] = 'Please select a valid private session end time.';
        } else {
            $privateEndObj = DateTime::createFromFormat('H:i', $privateTimeToRaw);
            if (!$privateEndObj) {
                $errors[] = 'Please select a valid private session end time.';
            }
        }
        if ($privateStartObj && $privateEndObj) {
            if ($privateEndObj <= $privateStartObj) {
                $errors[] = 'Private session end time must be later than start time.';
            } else {
                $timeSlot = $privateStartObj->format('g:i A') . ' - ' . $privateEndObj->format('g:i A');
            }
        }
        $preferredDate = $privateDate;
        $selectedTrainer = $selectedPrivateTrainer;
    }

    if (empty($errors)) {
        $duplicateStmt = $conn->prepare(
            "SELECT id
             FROM bookings
             WHERE email = ?
               AND class_slug = ?
               AND preferred_date = ?
               AND time_slot = ?
               AND trainer_type = ?
               AND {$activeBookingWhere}
             LIMIT 1"
        );
        if ($duplicateStmt) {
            $duplicateStmt->bind_param('sssss', $userEmail, $classType, $preferredDate, $timeSlot, $trainerType);
            $duplicateStmt->execute();
            $duplicateExists = (bool)$duplicateStmt->get_result()->fetch_assoc();
            $duplicateStmt->close();

            if ($duplicateExists) {
                $errors[] = 'You already booked this class session. One account can hold only one seat per session.';
            }
        }
    }

    if (empty($errors)) {
        $capacityStmt = $conn->prepare(
            "SELECT COALESCE(SUM(participants), 0) AS total_clients
             FROM bookings
             WHERE class_slug = ?
               AND preferred_date = ?
               AND time_slot = ?
               AND trainer_type = ?
               AND {$activeBookingWhere}"
        );
        if ($capacityStmt) {
            $capacityStmt->bind_param('ssss', $classType, $preferredDate, $timeSlot, $trainerType);
            $capacityStmt->execute();
            $capacityRow = $capacityStmt->get_result()->fetch_assoc();
            $capacityStmt->close();

            $currentClients = (int)($capacityRow['total_clients'] ?? 0);
            $maxParticipants = $trainerType === 'private' ? 1 : (int)$selectedClass['max_participants'];
            if (($currentClients + $participants) > $maxParticipants) {
                $remaining = max(0, $maxParticipants - $currentClients);
                $errors[] = $trainerType === 'private'
                    ? 'This private trainer time is already booked. Please choose another time.'
                    : "This class slot is almost full. Only {$remaining} seat(s) left.";
            }
        }
    }

    if (empty($errors)) {
        $paymentProvider = $paymentMethod === 'khalti' ? 'khalti' : 'cash';
        $paymentStatus = $paymentMethod === 'khalti' ? 'initiated' : 'unpaid';
        $paymentOrderId = fitgym_generate_payment_order_id('FGBKG');
        $paymentAmountPaisa = (int)($selectedClass['price_paisa'] ?? 0);
        $paymentRequestedAt = $paymentMethod === 'khalti' ? date('Y-m-d H:i:s') : '';
        $sql = "INSERT INTO bookings (
                    class_slug, class_name, trainer_name, trainer_type, full_name, email, contact_number,
                    preferred_date, time_slot, participants, payment_method, payment_provider, payment_status,
                    payment_order_id, payment_amount_paisa, payment_requested_at, created_at
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, ''), NOW())";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param(
                'sssssssssissssis',
                $classType,
                $selectedClass['title'],
                $selectedTrainer,
                $trainerType,
                $fullName,
                $email,
                $contactNumber,
                $preferredDate,
                $timeSlot,
                $participants,
                $paymentMethod,
                $paymentProvider,
                $paymentStatus,
                $paymentOrderId,
                $paymentAmountPaisa,
                $paymentRequestedAt
            );

            if ($stmt->execute()) {
                $bookingId = (int)$stmt->insert_id;

                if ($paymentMethod === 'khalti') {
                    $bookingPayload = [
                        'id' => $bookingId,
                        'class_slug' => $classType,
                        'class_name' => $selectedClass['title'],
                        'trainer_name' => $selectedTrainer,
                        'trainer_type' => $trainerType,
                        'full_name' => $fullName,
                        'email' => $email,
                        'contact_number' => $contactNumber,
                        'preferred_date' => $preferredDate,
                        'time_slot' => $timeSlot,
                        'participants' => $participants,
                        'payment_order_id' => $paymentOrderId,
                        'payment_amount_paisa' => $paymentAmountPaisa,
                    ];
                    $initiation = fitgym_khalti_initiate_payment($bookingPayload);
                    $initiationBody = is_array($initiation['body'] ?? null) ? $initiation['body'] : [];

                    if (!empty($initiation['ok']) && !empty($initiationBody['payment_url']) && !empty($initiationBody['pidx'])) {
                        fitgym_update_booking_payment_fields($bookingId, [
                            'payment_pidx' => (string)$initiationBody['pidx'],
                            'payment_response_json' => json_encode($initiationBody, JSON_UNESCAPED_SLASHES),
                        ]);

                        fitgym_redirect((string)$initiationBody['payment_url']);
                    }

                    $deleteStmt = $conn->prepare("DELETE FROM bookings WHERE id = ? LIMIT 1");
                    if ($deleteStmt) {
                        $deleteStmt->bind_param('i', $bookingId);
                        $deleteStmt->execute();
                        $deleteStmt->close();
                    }

                    $errorMessage = 'Khalti checkout could not be started. Please try again.';
                    foreach ($initiationBody as $value) {
                        if (is_array($value) && isset($value[0]) && is_string($value[0]) && trim($value[0]) !== '') {
                            $errorMessage = trim($value[0]);
                            break;
                        }
                    }
                    if (!empty($initiation['error'])) {
                        $errorMessage = trim((string)$initiation['error']);
                    }
                    $errors[] = $errorMessage;
                } else {
                    $_SESSION['booking_success_flash'] = [
                        'class_slug' => $classType,
                        'message' => 'Your ' . $selectedClass['title'] . ' booking request was submitted. Payment will be collected at the gym after admin approval.',
                    ];

                    fitgym_redirect(fitgym_url('/php/book_class.php') . '?class=' . rawurlencode($classType));
                }
            } else {
                $errors[] = 'Booking could not be saved. Please try again.';
            }
            $stmt->close();
        } else {
            $errors[] = 'Booking could not be prepared. Please try again.';
        }
    }
}

$durationLabel = ((int)($selectedClass['duration_minutes'] ?? 0) > 0 ? (int)$selectedClass['duration_minutes'] . ' mins' : 'Schedule on request');
$classDescription = trim((string)($selectedClass['description'] ?? ''));
$classGoalLabels = [];
foreach (fitgym_goal_options() as $goalKey => $goalLabel) {
    $goalColumn = fitgym_goal_flag_columns()[$goalKey] ?? null;
    if ($goalColumn !== null && (int)($selectedClass[$goalColumn] ?? 0) === 1) {
        $classGoalLabels[] = $goalLabel;
    }
}
$burnMin = fitgym_nullable_int($selectedClass['calories_burn_min'] ?? null, 0);
$burnMax = fitgym_nullable_int($selectedClass['calories_burn_max'] ?? null, 0);
$burnRangeLabel = 'Not specified';
if ($burnMin !== null || $burnMax !== null) {
    $burnStart = $burnMin ?? $burnMax;
    $burnEnd = $burnMax ?? $burnMin;
    $burnRangeLabel = $burnStart . '-' . $burnEnd . ' kcal / session';
}
$frequencyLabel = ((int)($selectedClass['recommended_frequency_per_week'] ?? 0) > 0)
    ? (int)$selectedClass['recommended_frequency_per_week'] . ' session' . ((int)$selectedClass['recommended_frequency_per_week'] === 1 ? '' : 's') . ' / week'
    : 'Flexible';
$impactLabel = (int)($selectedClass['low_impact'] ?? 0) === 1 ? 'Low impact' : 'Standard impact';
if ((int)($selectedClass['joint_friendly'] ?? 0) === 1) {
    $impactLabel .= ' and joint friendly';
}
$equipmentLabel = (int)($selectedClass['requires_equipment'] ?? 0) === 1 ? 'Equipment required' : 'No equipment needed';
$classSummaryTags = array_values(array_filter([
    trim((string)($selectedClass['category_label'] ?? '')),
    ($selectedClass['intensity_level'] ?? '') !== '' ? fitgym_labelize_token((string)$selectedClass['intensity_level']) . ' intensity' : '',
    trim((string)($selectedClass['fitness_level_label'] ?? '')),
    (int)($selectedClass['low_impact'] ?? 0) === 1 ? 'Low impact' : '',
    (int)($selectedClass['joint_friendly'] ?? 0) === 1 ? 'Joint friendly' : '',
]));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Book <?= htmlspecialchars($selectedClass['title']) ?> | FitGym</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="icon" href="<?= fitgym_esc(fitgym_asset_url('/pictures/favicon.png')) ?>">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= fitgym_esc(fitgym_asset_url('/css/header.css')) ?>">
    <link rel="stylesheet" href="<?= fitgym_esc(fitgym_asset_url('/css/footer.css')) ?>">
    <link rel="stylesheet" href="<?= fitgym_esc(fitgym_asset_url('/css/index.css')) ?>">
    <link rel="stylesheet" href="<?= fitgym_esc(fitgym_asset_url('/css/booking.css')) ?>">
</head>
<body>
<?php include "header.php"; ?>

<main class="booking-page">
    <section class="booking-hero">
        <div class="hero-content">
            <p class="eyebrow">Ready to train</p>
            <h1>Book Your Class</h1>
            <p class="subtext">Choose the available booking option for this class and set your schedule clearly.</p>
        </div>
    </section>

    <section class="booking-wrapper">
        <div class="booking-grid">
            <article class="class-summary-card">
                <div class="summary-media">
                    <img src="<?= htmlspecialchars($selectedClass['image']) ?>" alt="<?= htmlspecialchars($selectedClass['title']) ?>">
                    <span class="badge">Top Pick</span>
                </div>
                <div class="summary-body">
                    <h2><?= htmlspecialchars($selectedClass['title']) ?></h2>
                    <?php if ($classDescription !== ''): ?>
                        <p class="summary-description"><?= htmlspecialchars($classDescription) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($classSummaryTags)): ?>
                        <div class="summary-tags">
                            <?php foreach ($classSummaryTags as $tag): ?>
                                <span class="summary-tag"><?= htmlspecialchars($tag) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <div class="summary-detail-grid">
                        <div class="summary-detail-card">
                            <span>Calories Burn</span>
                            <strong><?= htmlspecialchars($burnRangeLabel) ?></strong>
                        </div>
                        <div class="summary-detail-card">
                            <span>Recommended For</span>
                            <strong><?= htmlspecialchars(!empty($classGoalLabels) ? implode(', ', $classGoalLabels) : 'General fitness') ?></strong>
                        </div>
                        <div class="summary-detail-card">
                            <span>Ideal Frequency</span>
                            <strong><?= htmlspecialchars($frequencyLabel) ?></strong>
                        </div>
                        <div class="summary-detail-card">
                            <span>Impact Level</span>
                            <strong><?= htmlspecialchars($impactLabel) ?></strong>
                        </div>
                        <div class="summary-detail-card">
                            <span>Equipment</span>
                            <strong><?= htmlspecialchars($equipmentLabel) ?></strong>
                        </div>
                    </div>
                    <p class="summary-line"><span><?= $hasRegularTrainer ? 'Assigned Trainer' : 'Trainer Status' ?></span><?= htmlspecialchars($hasRegularTrainer ? $selectedClass['trainer'] : 'Private booking only') ?></p>
                    <p class="summary-line"><span>Duration</span><?= htmlspecialchars($durationLabel) ?></p>
                    <p class="summary-line"><span>Location</span><?= htmlspecialchars($selectedClass['location']) ?></p>
                    <p class="summary-line"><span>Max Per Slot</span><?= htmlspecialchars((string)$selectedClass['max_participants']) ?> users</p>
                    <div class="price-tag">
                        <span id="summaryPriceLabel"><?= htmlspecialchars($selectedClass['price_title']) ?></span>
                        <strong
                            id="summaryPriceValue"
                            data-base-rupees="<?= (int)$selectedClass['base_price_rupees'] ?>"
                            data-private-multiplier="<?= (int)$selectedClass['price_multiplier'] ?>"
                        ><?= htmlspecialchars($selectedClass['price']) ?></strong>
                    </div>
                    <?php if ($selectedClass['price_hint'] !== ''): ?>
                        <p id="summaryPriceHint" class="admin-note"><?= htmlspecialchars($selectedClass['price_hint']) ?></p>
                    <?php else: ?>
                        <p id="summaryPriceHint" class="admin-note" hidden></p>
                    <?php endif; ?>
                </div>
            </article>

            <div class="booking-card">
                <form id="bookingForm" class="booking-form" method="POST" action="">
                    <h3>Booking Details</h3>

                    <div id="formErrors" class="form-errors <?= !empty($errors) ? 'show' : '' ?>" role="alert">
                        <?php if (!empty($errors)): ?>
                            <ul>
                                <?php foreach ($errors as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                    <div class="field-group">
                        <label for="classType">Class Type</label>
                        <select id="classType" name="class_type" required>
                            <?php foreach ($classMap as $slug => $classItem): ?>
                                <option value="<?= htmlspecialchars($slug) ?>" <?= $classType === $slug ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($classItem['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field-group">
                        <label for="fullName">Full Name</label>
                        <input id="fullName" name="full_name" type="text" value="<?= htmlspecialchars($fullName) ?>" readonly>
                        <p class="admin-note">Bookings are linked to your logged-in account.</p>
                    </div>

                    <div class="field-group">
                        <label for="contactNumber">Contact Number</label>
                        <input id="contactNumber" name="contact_number" type="tel" value="<?= htmlspecialchars($contactNumber) ?>" placeholder="9800000000" required>
                    </div>

                    <div class="field-group">
                        <label for="bookingEmail">Account Email</label>
                        <input id="bookingEmail" type="email" value="<?= htmlspecialchars($userEmail) ?>" readonly>
                    </div>

                    <div class="field-group">
                        <label for="trainerType">Session Type</label>
                        <select id="trainerType" name="trainer_type" required>
                            <?php if ($hasRegularMode): ?>
                                <option value="regular" <?= $trainerType === 'regular' ? 'selected' : '' ?>>Class Schedule</option>
                            <?php endif; ?>
                            <option value="private" <?= $trainerType === 'private' ? 'selected' : '' ?>>Private Trainer</option>
                        </select>
                        <p class="admin-note">Private trainer sessions cost 3x the regular class rate.</p>
                        <?php if (!$hasRegularMode): ?>
                            <p class="admin-note">This class currently has no admin-arranged regular trainer schedule, so private booking is available.</p>
                        <?php endif; ?>
                    </div>

                    <div id="regularBookingFields">
                        <div class="field-group">
                            <label>Assigned Trainer</label>
                            <input type="text" value="<?= htmlspecialchars($selectedClass['trainer']) ?>" readonly>
                        </div>

                        <div class="field-group">
                            <label for="regularSchedule">Available Class Schedules</label>
                            <select id="regularSchedule" name="regular_schedule">
                                <option value="">Choose a schedule</option>
                                <?php foreach ($regularScheduleOptions as $option): ?>
                                    <?php
                                    $usage = (int)($regularSeatUsage[$option['key']] ?? 0);
                                    $remaining = max(0, (int)$selectedClass['max_participants'] - $usage);
                                    ?>
                                    <option value="<?= htmlspecialchars($option['key']) ?>" <?= $selectedRegularSlot === $option['key'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($option['label']) ?> | <?= htmlspecialchars((string)$remaining) ?> seat(s) left
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div id="privateBookingFields">
                        <div class="field-group">
                            <label for="privateTrainerName">Private Trainer</label>
                            <select id="privateTrainerName" name="private_trainer_name">
                                <option value="">Choose trainer</option>
                                <?php foreach ($verifiedTrainers as $trainerRow): ?>
                                    <option value="<?= htmlspecialchars($trainerRow['name']) ?>" <?= $selectedPrivateTrainer === $trainerRow['name'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($trainerRow['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="field-row">
                            <div class="field-group">
                                <label for="privateDate">Private Session Date</label>
                                <input id="privateDate" name="private_date" type="date" value="<?= htmlspecialchars($privateDate) ?>">
                            </div>

                            <div class="field-group">
                                <label for="privateTimeFrom">Private Session Time</label>
                                <div class="field-row">
                                    <div class="field-group">
                                        <label for="privateTimeFrom">From</label>
                                        <input id="privateTimeFrom" name="private_time_from" type="time" value="<?= htmlspecialchars($privateTimeFromRaw) ?>">
                                    </div>
                                    <div class="field-group">
                                        <label for="privateTimeTo">To</label>
                                        <input id="privateTimeTo" name="private_time_to" type="time" value="<?= htmlspecialchars($privateTimeToRaw) ?>">
                                    </div>
                                </div>
                                <p id="privateTimeHint" class="admin-note">Choose a start and end time. The AM/PM preview will appear here.</p>
                            </div>
                        </div>
                    </div>

                    <div class="payment-section">
                        <h3>Payment</h3>
                        <div class="payment-options">
                            <label class="payment-option">
                                <input type="radio" name="payment" value="cash" <?= $paymentMethod === 'cash' ? 'checked' : '' ?>>
                                <span class="payment-icon">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2zm2 4h4v4H5v-4zm6 0h8v4h-8v-4z"/></svg>
                                </span>
                                Cash on Service
                            </label>
                            <label class="payment-option <?= !$khaltiEnabled ? 'is-disabled' : '' ?>">
                                <input type="radio" name="payment" value="khalti" <?= $paymentMethod === 'khalti' ? 'checked' : '' ?> <?= !$khaltiEnabled ? 'disabled' : '' ?>>
                                <span class="payment-icon">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2zm0 4v8h16V9H4zm2 2h6v2H6v-2z"/></svg>
                                </span>
                                Khalti Sandbox
                            </label>
                        </div>
                        <?php if (!$khaltiEnabled): ?>
                            <p class="admin-note">Add your Khalti sandbox secret key in admin settings to enable Khalti checkout.</p>
                        <?php endif; ?>
                        <div class="price-row">
                            <span id="paymentPriceLabel"><?= htmlspecialchars($selectedClass['price_title']) ?></span>
                            <strong id="paymentPriceValue"><?= htmlspecialchars($selectedClass['price']) ?></strong>
                        </div>
                        <?php if ($selectedClass['price_hint'] !== ''): ?>
                            <p id="paymentPriceHint" class="admin-note"><?= htmlspecialchars($selectedClass['price_hint']) ?></p>
                        <?php else: ?>
                            <p id="paymentPriceHint" class="admin-note" hidden></p>
                        <?php endif; ?>
                    </div>

                    <div class="actions">
                        <button type="submit" class="primary-btn">Confirm Booking</button>
                        <a class="secondary-btn" href="<?= fitgym_esc(fitgym_url('/php/classes.php')) ?>">Cancel</a>
                    </div>

                    <p id="successMessage" class="success-message <?= $success ? 'show' : '' ?>" role="status" aria-live="polite">
                        <?= htmlspecialchars($successMessage !== '' ? $successMessage : ('Your ' . $selectedClass['title'] . ' class has been successfully booked!')) ?>
                    </p>
                </form>
            </div>
        </div>
    </section>
</main>

<?php include "footer.php"; ?>

<script>
    const bookingForm = document.getElementById('bookingForm');
    const errorBox = document.getElementById('formErrors');
    const trainerType = document.getElementById('trainerType');
    const classType = document.getElementById('classType');
    const regularFields = document.getElementById('regularBookingFields');
    const privateFields = document.getElementById('privateBookingFields');
    const privateTimeFrom = document.getElementById('privateTimeFrom');
    const privateTimeTo = document.getElementById('privateTimeTo');
    const privateTimeHint = document.getElementById('privateTimeHint');
    const summaryPriceValue = document.getElementById('summaryPriceValue');
    const summaryPriceLabel = document.getElementById('summaryPriceLabel');
    const summaryPriceHint = document.getElementById('summaryPriceHint');
    const paymentPriceValue = document.getElementById('paymentPriceValue');
    const paymentPriceLabel = document.getElementById('paymentPriceLabel');
    const paymentPriceHint = document.getElementById('paymentPriceHint');

    function formatCurrency(amountRupees) {
        return `NPR ${new Intl.NumberFormat('en-US').format(amountRupees)}`;
    }

    function updateSessionPrice() {
        if (!summaryPriceValue || !paymentPriceValue || !trainerType) {
            return;
        }

        const baseRupees = parseInt(summaryPriceValue.dataset.baseRupees || '2000', 10);
        const isPrivate = trainerType.value === 'private';
        const multiplier = isPrivate ? 3 : 1;
        const sessionRupees = baseRupees * multiplier;
        const label = isPrivate ? 'Private Session Price' : 'Class Price';
        const hint = isPrivate
            ? 'Private trainer sessions are charged at 3x the regular class price.'
            : '';

        summaryPriceValue.textContent = formatCurrency(sessionRupees);
        paymentPriceValue.textContent = formatCurrency(sessionRupees);
        summaryPriceLabel.textContent = label;
        paymentPriceLabel.textContent = label;
        if (summaryPriceHint) {
            summaryPriceHint.textContent = hint;
            summaryPriceHint.hidden = hint === '';
        }
        if (paymentPriceHint) {
            paymentPriceHint.textContent = hint;
            paymentPriceHint.hidden = hint === '';
        }
    }

    function toggleTrainerMode() {
        const isRegular = trainerType.value === 'regular';
        regularFields.style.display = isRegular ? 'block' : 'none';
        privateFields.style.display = isRegular ? 'none' : 'block';
        updateSessionPrice();
    }

    function formatTimeWithMeridiem(value) {
        if (!value || !/^\d{2}:\d{2}$/.test(value)) {
            return '';
        }
        const [hoursText, minutes] = value.split(':');
        const hours = parseInt(hoursText, 10);
        const meridiem = hours >= 12 ? 'PM' : 'AM';
        const displayHour = hours % 12 || 12;
        return `${displayHour}:${minutes} ${meridiem}`;
    }

    function updatePrivateTimeHint() {
        if (!privateTimeHint) {
            return;
        }
        const fromLabel = formatTimeWithMeridiem(privateTimeFrom?.value || '');
        const toLabel = formatTimeWithMeridiem(privateTimeTo?.value || '');

        if (fromLabel && toLabel) {
            privateTimeHint.textContent = `Selected session: ${fromLabel} to ${toLabel}`;
        } else if (fromLabel) {
            privateTimeHint.textContent = `Start time: ${fromLabel}`;
        } else if (toLabel) {
            privateTimeHint.textContent = `End time: ${toLabel}`;
        } else {
            privateTimeHint.textContent = 'Choose a start and end time. The AM/PM preview will appear here.';
        }
    }

    trainerType.addEventListener('change', toggleTrainerMode);
    toggleTrainerMode();
    if (privateTimeFrom) {
        privateTimeFrom.addEventListener('input', updatePrivateTimeHint);
    }
    if (privateTimeTo) {
        privateTimeTo.addEventListener('input', updatePrivateTimeHint);
    }
    updatePrivateTimeHint();

    classType.addEventListener('change', function () {
        const nextUrl = new URL(window.location.href);
        nextUrl.searchParams.set('class', classType.value);
        window.location.href = nextUrl.toString();
    });

    bookingForm.addEventListener('submit', (event) => {
        const errors = [];
        const fullName = document.getElementById('fullName');
        const contactNumber = document.getElementById('contactNumber');

        [fullName, contactNumber].forEach((field) => field.classList.remove('field-error'));

        if (!fullName.value.trim()) {
            errors.push('Full name is required.');
            fullName.classList.add('field-error');
        }

        const phonePattern = /^[0-9+\-\s]{7,20}$/;
        if (!phonePattern.test(contactNumber.value.trim())) {
            errors.push('A valid contact number is required.');
            contactNumber.classList.add('field-error');
        }

        if (trainerType.value === 'regular') {
            const regularSchedule = document.getElementById('regularSchedule');
            regularSchedule.classList.remove('field-error');
            if (!regularSchedule.value) {
                errors.push('Please select one regular class schedule.');
                regularSchedule.classList.add('field-error');
            }
        } else {
            const privateTrainerName = document.getElementById('privateTrainerName');
            const privateDate = document.getElementById('privateDate');
            const privateTimeFrom = document.getElementById('privateTimeFrom');
            const privateTimeTo = document.getElementById('privateTimeTo');
            [privateTrainerName, privateDate, privateTimeFrom, privateTimeTo].forEach((field) => field.classList.remove('field-error'));
            if (!privateTrainerName.value) {
                errors.push('Please select a private trainer.');
                privateTrainerName.classList.add('field-error');
            }
            if (!privateDate.value) {
                errors.push('Please select a private session date.');
                privateDate.classList.add('field-error');
            }
            if (!privateTimeFrom.value) {
                errors.push('Please select a private session start time.');
                privateTimeFrom.classList.add('field-error');
            }
            if (!privateTimeTo.value) {
                errors.push('Please select a private session end time.');
                privateTimeTo.classList.add('field-error');
            }
            if (privateTimeFrom.value && privateTimeTo.value && privateTimeTo.value <= privateTimeFrom.value) {
                errors.push('Private session end time must be later than start time.');
                privateTimeFrom.classList.add('field-error');
                privateTimeTo.classList.add('field-error');
            }
        }

        if (errors.length > 0) {
            event.preventDefault();
            errorBox.innerHTML = `<ul>${errors.map((error) => `<li>${error}</li>`).join('')}</ul>`;
            errorBox.classList.add('show');
        } else {
            errorBox.innerHTML = '';
            errorBox.classList.remove('show');
        }
    });
</script>
</body>
</html>
