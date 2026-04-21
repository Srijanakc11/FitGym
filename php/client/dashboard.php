<?php
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../dynamic_content.php';
require_once __DIR__ . '/../payment_helpers.php';
require_once __DIR__ . '/../class_recommendation_helpers.php';

fitgym_bootstrap_booking_payment_columns();
fitgym_sync_booking_expiry_statuses();

if (!function_exists('client_format_date')) {
    function client_format_date(?string $value, string $fallback = '-'): string
    {
        if ($value === null || trim($value) === '') {
            return $fallback;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $fallback;
        }

        return date('M j, Y', $timestamp);
    }
}

if (!function_exists('client_format_datetime')) {
    function client_format_datetime(?string $value, string $fallback = '-'): string
    {
        if ($value === null || trim($value) === '') {
            return $fallback;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $fallback;
        }

        return date('M j, Y g:i A', $timestamp);
    }
}

if (!function_exists('client_payment_status_summary')) {
    function client_payment_status_summary(array $booking): array
    {
        $snapshot = fitgym_booking_payment_snapshot($booking);
        return [
            'label' => $snapshot['status_label'],
            'detail' => $snapshot['status_detail'],
        ];
    }
}

if (!function_exists('client_booking_expiry_date')) {
    function client_booking_expiry_date(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d', trim($value));
        if (!$date) {
            $timestamp = strtotime($value);
            if ($timestamp === false) {
                return null;
            }
            $date = (new DateTimeImmutable())->setTimestamp($timestamp);
        }

        return $date->modify('+' . max(1, fitgym_booking_expiry_days()) . ' days')->format('Y-m-d');
    }
}

$userId = (int)($_SESSION['auth_id'] ?? $_SESSION['user_id'] ?? 0);
$userEmail = (string)($_SESSION['auth_email'] ?? $_SESSION['user_email'] ?? '');
$userName = (string)($_SESSION['auth_name'] ?? $_SESSION['user_name'] ?? 'Member');
$profileFlash = '';

$user = null;
if ($conn && $userId > 0) {
    $stmt = $conn->prepare("SELECT id, name, email, phone, gender FROM accounts WHERE id = ? AND role = 'client' LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

if ($user) {
    $userEmail = (string)($user['email'] ?? $userEmail);
    $userName  = (string)($user['name'] ?? $userName);
}

// --- Handle Profile Edits ---
if ($conn && $userId > 0 && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'update_account') {
        $updName = trim($_POST['name'] ?? '');
        $updPhone = trim($_POST['phone'] ?? '');
        $updGender = trim($_POST['gender'] ?? '');
        $stmt = $conn->prepare("UPDATE accounts SET name=?, phone=?, gender=? WHERE id=? AND role='client'");
        if ($stmt) {
            $stmt->bind_param('sssi', $updName, $updPhone, $updGender, $userId);
            $stmt->execute();
            $stmt->close();
            $_SESSION['auth_name'] = $updName;
            $_SESSION['user_name'] = $updName;
            $_SESSION['client_profile_flash'] = 'Account details updated successfully.';
            fitgym_redirect('/php/client/dashboard.php');
        }
    } elseif ($action === 'update_preferences') {
        if (!function_exists('fitgym_save_user_fitness_profile')) {
            require_once __DIR__ . '/../auth_common.php';
        }
        fitgym_save_user_fitness_profile($userId, $_POST);
        $_SESSION['client_profile_flash'] = 'Fitness preferences saved successfully.';
        fitgym_redirect('/php/client/dashboard.php');
    } elseif ($action === 'delete_preferences') {
        $stmt = $conn->prepare("DELETE FROM user_fitness_profiles WHERE account_id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->close();
            $_SESSION['client_profile_flash'] = 'Fitness profile deleted.';
            fitgym_redirect('/php/client/dashboard.php');
        }
    }
}

// --- Profile-based recommendations ---
$fitnessProfile       = null;
$profileRecommended   = [];
$profileComplete      = false;

if ($userId > 0) {
    if (!function_exists('fitgym_get_user_fitness_profile')) {
        require_once __DIR__ . '/../auth_common.php';
    }
    $fitnessProfile = fitgym_get_user_fitness_profile($userId);
    if ($fitnessProfile !== null && (int)($fitnessProfile['profile_completed'] ?? 0) === 1) {
        $profileComplete = true;
        $profInput = [
            'age'                    => (string)($fitnessProfile['age'] ?? ''),
            'gender'                 => (string)($fitnessProfile['gender'] ?? 'female'),
            'height_cm'              => (string)($fitnessProfile['height_cm'] ?? ''),
            'weight_kg'              => (string)($fitnessProfile['weight_kg'] ?? ''),
            'activity'               => (string)($fitnessProfile['activity'] ?? 'moderate'),
            'goal'                   => (string)($fitnessProfile['goal'] ?? 'maintenance'),
            'training_days_per_week' => (string)($fitnessProfile['training_days_per_week'] ?? '3'),
            'fitness_level'          => (string)($fitnessProfile['fitness_level'] ?? 'beginner'),
            'joint_pain'             => (string)($fitnessProfile['joint_pain'] ?? 'no'),
            'duration_preference'    => (string)($fitnessProfile['duration_preference'] ?? ''),
        ];
        $allClasses = array_map('fitgym_normalize_class_row', fitgym_get_classes());
        $recommendableClasses = array_values(array_filter(
            $allClasses,
            static fn(array $r): bool => !empty($r['recommendation_ready'])
        ));
        if (!empty($recommendableClasses)) {
            $profCalc = fitgym_calculate_tdee_context($profInput);
            if (empty($profCalc['errors'])) {
                $profCtx = $profCalc['context'];
                $recSet  = fitgym_get_recommended_classes($recommendableClasses, $profCtx);
                $recRows = $recSet['has_exact_match'] ? $recSet['exact_matches'] : $recSet['fallback_alternatives'];
                $profileRecommended = array_slice($recRows, 0, 3);
            }
        }
    }
}


if ($conn && $userEmail !== '' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    $bookingId = (int)($_POST['booking_id'] ?? 0);
    $cancelled = false;
    $stmt = $conn->prepare(
        "UPDATE bookings
         SET status = 'Cancelled',
             payment_status = CASE
                 WHEN COALESCE(payment_provider, 'cash') = 'khalti' AND COALESCE(payment_status, '') = 'paid' THEN payment_status
                 ELSE 'cancelled'
             END
         WHERE id = ? AND email = ?
           AND COALESCE(status, 'Pending') NOT IN ('Cancelled', 'Expired')"
    );
    if ($stmt) {
        $stmt->bind_param('is', $bookingId, $userEmail);
        $stmt->execute();
        $cancelled = $stmt->affected_rows > 0;
        $stmt->close();
    }

    $_SESSION['client_profile_flash'] = $cancelled
        ? 'Booking cancelled successfully.'
        : 'This booking can no longer be cancelled.';
    fitgym_redirect('/php/client/dashboard.php');
}

if ($conn && $userEmail !== '' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'remove') {
    $bookingId = (int)($_POST['booking_id'] ?? 0);
    $removed = false;
    $stmt = $conn->prepare(
        "DELETE FROM bookings
         WHERE id = ?
           AND email = ?
           AND COALESCE(status, 'Pending') = 'Cancelled'
         LIMIT 1"
    );
    if ($stmt) {
        $stmt->bind_param('is', $bookingId, $userEmail);
        $stmt->execute();
        $removed = $stmt->affected_rows > 0;
        $stmt->close();
    }

    $_SESSION['client_profile_flash'] = $removed
        ? 'Cancelled booking removed from your profile.'
        : 'Only cancelled bookings can be removed.';
    fitgym_redirect('/php/client/dashboard.php');
}

if (!empty($_SESSION['client_profile_flash'])) {
    $profileFlash = (string)$_SESSION['client_profile_flash'];
    unset($_SESSION['client_profile_flash']);
}

$bookings = [];
if ($conn && $userEmail !== '') {
    $stmt = $conn->prepare("SELECT * FROM bookings WHERE email = ? ORDER BY preferred_date DESC, created_at DESC");
    if ($stmt) {
        $stmt->bind_param('s', $userEmail);
        $stmt->execute();
        $bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

$classLookup = [];
foreach (fitgym_get_classes() as $classRow) {
    $classRow = fitgym_normalize_class_row($classRow);
    $slug = trim((string)($classRow['slug'] ?? ''));
    if ($slug === '') {
        continue;
    }
    $classLookup[$slug] = $classRow;
}

foreach ($bookings as &$booking) {
    $classSlug = trim((string)($booking['class_slug'] ?? ''));
    $classDetails = $classLookup[$classSlug] ?? null;
    $paymentSnapshot = fitgym_booking_payment_snapshot($booking);
    $paymentSummary = client_payment_status_summary($booking);
    $burnMin = $classDetails !== null ? fitgym_nullable_int($classDetails['calories_burn_min'] ?? null, 0) : null;
    $burnMax = $classDetails !== null ? fitgym_nullable_int($classDetails['calories_burn_max'] ?? null, 0) : null;
    $participants = max(1, (int)($booking['participants'] ?? 1));

    $booking['display_date'] = client_format_date((string)($booking['preferred_date'] ?? ''));
    $booking['display_created_at'] = client_format_datetime((string)($booking['created_at'] ?? ''));
    $booking['expiry_date'] = client_booking_expiry_date((string)($booking['preferred_date'] ?? ''));
    $booking['display_expiry_date'] = client_format_date($booking['expiry_date'], '-');
    $booking['booking_reference'] = 'FG-' . str_pad((string)((int)($booking['id'] ?? 0)), 6, '0', STR_PAD_LEFT);
    $booking['participants_label'] = $participants . ' seat' . ($participants === 1 ? '' : 's');
    $booking['status_class'] = strtolower(trim((string)($booking['status'] ?? 'pending')));
    $booking['expiry_state_class'] = match ($booking['status_class']) {
        'expired' => 'expired',
        'cancelled' => 'cancelled',
        default => 'active',
    };
    $booking['trainer_type_label'] = strtolower((string)($booking['trainer_type'] ?? 'regular')) === 'private' ? 'Private trainer' : 'Class schedule';
    $booking['payment_provider_label'] = $paymentSnapshot['provider_label'];
    $booking['payment_method_label'] = $paymentSnapshot['method_label'];
    $booking['payment_status_label'] = $paymentSummary['label'];
    $booking['payment_status_detail'] = $paymentSummary['detail'];
    $booking['payment_order_id'] = $paymentSnapshot['order_id'];
    $booking['payment_pidx'] = $paymentSnapshot['pidx'];
    $booking['payment_transaction_id'] = $paymentSnapshot['transaction_id'];
    $booking['class_category_label'] = $classDetails !== null ? (string)($classDetails['category_label'] ?? 'General') : 'General';
    $booking['class_location'] = $classDetails !== null ? (string)($classDetails['location'] ?? 'Main Studio') : 'Main Studio';
    $booking['class_duration_label'] = $classDetails !== null && !empty($classDetails['duration_minutes'])
        ? (int)$classDetails['duration_minutes'] . ' min'
        : 'Flexible';
    $booking['class_burn_label'] = ($burnMin !== null || $burnMax !== null)
        ? (($burnMin ?? $burnMax ?? 0) . '-' . ($burnMax ?? $burnMin ?? 0) . ' kcal')
        : 'Not specified';
    $booking['class_image'] = $classDetails !== null ? (string)($classDetails['image'] ?? '') : '';
    $booking['class_description'] = $classDetails !== null ? trim((string)($classDetails['description'] ?? '')) : '';
}
unset($booking);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile | FitGym</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="<?= fitgym_esc(fitgym_asset_url('/pictures/favicon.png')) ?>">
    <link rel="stylesheet" href="<?= fitgym_esc(fitgym_asset_url('/css/header.css')) ?>">
    <link rel="stylesheet" href="<?= fitgym_esc(fitgym_asset_url('/css/footer.css')) ?>">
    <link rel="stylesheet" href="<?= fitgym_esc(fitgym_asset_url('/css/index.css')) ?>">
    <link rel="stylesheet" href="<?= fitgym_esc(fitgym_asset_url('/css/client.css')) ?>">
</head>
<body>
<?php include "../header.php"; ?>

<main class="client-page">
    <section class="client-hero">
        <div>
            <p class="eyebrow">Member profile</p>
            <h1>My Profile</h1>
            <p><?= htmlspecialchars($userName) ?>, review your account details, bookings, and payment information in one place.</p>
        </div>
        <div class="hero-actions">
            <a class="btn" href="<?= fitgym_esc(fitgym_url('/php/classes.php')) ?>">Browse Classes</a>
        </div>
    </section>

    <?php if ($profileFlash !== ''): ?>
        <div class="client-flash"><?= htmlspecialchars($profileFlash) ?></div>
    <?php endif; ?>

    <?php if (!empty($profileRecommended)): ?>
    <section class="client-rec-section">
        <div class="client-rec-header">
            <div>
                <p class="eyebrow">Based on your profile</p>
                <h2>Recommended Classes</h2>
            </div>
            <a href="<?= fitgym_esc(fitgym_url('/php/recommend.php')) ?>" class="btn secondary">Full Planner</a>
        </div>
        <div class="client-rec-grid">
            <?php foreach ($profileRecommended as $recIndex => $recRow): ?>
                <?php
                $recRow     = fitgym_normalize_class_row($recRow);
                $recTitle   = (string)($recRow['class_name'] ?? $recRow['name'] ?? 'Class');
                $recSlug    = (string)($recRow['slug'] ?? '');
                $recUrl     = fitgym_url('/php/class_inside.php') . ($recSlug !== '' ? '?class=' . rawurlencode($recSlug) : '');
                $recCat     = (string)($recRow['category_label'] ?? 'General');
                $recLevel   = fitgym_labelize_token((string)($recRow['fitness_level'] ?? 'beginner'));
                $recIntens  = fitgym_labelize_token((string)($recRow['intensity_level'] ?? 'medium'));
                $recScore   = max(0, min(100, (int)round(((int)($recRow['score'] ?? 0) / 122) * 100)));
                $recImage   = (string)($recRow['image'] ?? fitgym_url('/pictures/workout.jpg'));
                $recDesc    = (string)($recRow['description'] ?? '');
                ?>
                <div class="client-rec-card">
                    <div class="client-rec-img-wrap">
                        <img src="<?= fitgym_esc($recImage) ?>" alt="<?= fitgym_esc($recTitle) ?>">
                        <span class="client-rec-rank">#<?= $recIndex + 1 ?></span>
                    </div>
                    <div class="client-rec-body">
                        <div class="client-rec-meta">
                            <span><?= fitgym_esc($recCat) ?></span>
                            <span><?= fitgym_esc($recLevel) ?></span>
                            <span><?= fitgym_esc($recIntens) ?> intensity</span>
                        </div>
                        <h3><?= fitgym_esc($recTitle) ?></h3>
                        <?php if ($recDesc !== ''): ?>
                            <p><?= fitgym_esc(mb_strimwidth($recDesc, 0, 100, '…')) ?></p>
                        <?php endif; ?>
                        <p class="rec-price-tag"><strong>Price:</strong> <?= fitgym_esc((string)$recRow['price_formatted']) ?></p>
                        <div class="client-rec-foot">
                            <span class="client-rec-score"><?= $recScore ?>% match</span>
                            <a class="btn small" href="<?= fitgym_esc($recUrl) ?>">View Class</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div style="text-align: center; margin-top: 20px;">
            <a href="<?= fitgym_esc(fitgym_url('/php/classes.php')) ?>" class="btn secondary">Explore All Classes</a>
        </div>
    </section>
    <?php elseif (!$profileComplete): ?>
    <section class="client-rec-section client-rec-empty">
        <p>🏋️ <strong>Complete your fitness profile</strong> to get personalised class recommendations!</p>
    </section>
    <?php endif; ?>



    <section class="client-grid">
        <div class="client-card" id="account-card">
            <div class="section-head">
                <h3>Account Details</h3>
                <button type="button" class="btn small secondary" onclick="toggleEdit('account')">Edit</button>
            </div>
            
            <div class="view-mode-wrap" id="account-view">
                <div class="profile-row"><span>Name</span><strong><?= htmlspecialchars($user['name'] ?? $userName) ?></strong></div>
                <div class="profile-row"><span>Email</span><strong><?= htmlspecialchars($user['email'] ?? $userEmail) ?></strong></div>
                <div class="profile-row"><span>Phone</span><strong><?= htmlspecialchars($user['phone'] ?? '-') ?></strong></div>
                <div class="profile-row"><span>Gender</span><strong><?= htmlspecialchars($user['gender'] ?? ($fitnessProfile['gender'] ?? '-')) ?></strong></div>
            </div>

            <form method="POST" class="profile-edit-form" id="account-edit">
                <input type="hidden" name="action" value="update_account">
                <label>Name <input type="text" name="name" value="<?= htmlspecialchars($user['name'] ?? $userName) ?>" required></label>
                <label>Phone <input type="text" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>"></label>
                <label>Gender 
                    <select name="gender">
                        <option value="Male" <?= ($user['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= ($user['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                        <option value="Other" <?= ($user['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </label>
                <div class="profile-form-actions">
                    <button type="button" class="btn small secondary" onclick="toggleEdit('account')">Cancel</button>
                    <button type="submit" class="btn small">Save</button>
                </div>
            </form>
            <a class="link" href="<?= fitgym_esc(fitgym_url('/php/logout.php')) ?>">Logout</a>
        </div>

        <div class="client-card" id="fitness-card">
            <div class="section-head">
                <h3>Fitness Profile</h3>
                <?php if ($profileComplete && $fitnessProfile !== null): ?>
                    <button type="button" class="btn small secondary" onclick="toggleEdit('fitness')">Edit</button>
                <?php endif; ?>
            </div>

            <?php if ($profileComplete && $fitnessProfile !== null): ?>
            <div class="view-mode-wrap" id="fitness-view">
                <div class="profile-row"><span>Age</span><strong><?= htmlspecialchars((string)($fitnessProfile['age'] ?? '-')) ?></strong></div>
                <div class="profile-row"><span>Height</span><strong><?= htmlspecialchars((string)($fitnessProfile['height_cm'] ?? '-')) ?> cm</strong></div>
                <div class="profile-row"><span>Weight</span><strong><?= htmlspecialchars((string)($fitnessProfile['weight_kg'] ?? '-')) ?> kg</strong></div>
                <div class="profile-row"><span>Activity Level</span><strong><?= htmlspecialchars(ucwords(str_replace('_', ' ', (string)($fitnessProfile['activity'] ?? '-')))) ?></strong></div>
                <div class="profile-row"><span>Goal</span><strong><?= htmlspecialchars(ucwords(str_replace('_', ' ', (string)($fitnessProfile['goal'] ?? '-')))) ?></strong></div>
                <div class="profile-row"><span>Training Days</span><strong><?= htmlspecialchars((string)($fitnessProfile['training_days_per_week'] ?? '-')) ?> days/week</strong></div>
                
                <form method="POST" style="margin-top: 14px;">
                    <input type="hidden" name="action" value="delete_preferences">
                    <button type="submit" class="btn small secondary" style="color: #b3261e; border-color: transparent;" onclick="return confirm('Are you sure you want to delete your fitness profile? Recommendations will be reset.');">Delete Profile</button>
                </form>
            </div>
            
            <form method="POST" class="profile-edit-form" id="fitness-edit">
                <input type="hidden" name="action" value="update_preferences">
                <label>Age <input type="number" name="age" min="14" max="100" value="<?= htmlspecialchars((string)($fitnessProfile['age'] ?? '')) ?>" required></label>
                <label>Height (cm) <input type="number" step="0.1" name="height_cm" value="<?= htmlspecialchars((string)($fitnessProfile['height_cm'] ?? '')) ?>" required></label>
                <label>Weight (kg) <input type="number" step="0.1" name="weight_kg" value="<?= htmlspecialchars((string)($fitnessProfile['weight_kg'] ?? '')) ?>" required></label>
                <label>Gender 
                    <select name="gender" required>
                        <option value="female" <?= ($fitnessProfile['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
                        <option value="male" <?= ($fitnessProfile['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Male</option>
                    </select>
                </label>
                <label>Activity Level 
                    <select name="activity" required>
                        <option value="sedentary" <?= ($fitnessProfile['activity'] ?? '') === 'sedentary' ? 'selected' : '' ?>>Sedentary</option>
                        <option value="light" <?= ($fitnessProfile['activity'] ?? '') === 'light' ? 'selected' : '' ?>>Light</option>
                        <option value="moderate" <?= ($fitnessProfile['activity'] ?? '') === 'moderate' ? 'selected' : '' ?>>Moderate</option>
                        <option value="active" <?= ($fitnessProfile['activity'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="very_active" <?= ($fitnessProfile['activity'] ?? '') === 'very_active' ? 'selected' : '' ?>>Very Active</option>
                    </select>
                </label>
                <label>Goal 
                    <select name="goal" required>
                        <option value="maintenance" <?= strpos(($fitnessProfile['goal'] ?? ''), 'maintenance') !== false ? 'selected' : '' ?>>Maintenance</option>
                        <option value="fat_loss" <?= strpos(($fitnessProfile['goal'] ?? ''), 'fat_loss') !== false ? 'selected' : '' ?>>Fat Loss</option>
                        <option value="muscle_gain" <?= strpos(($fitnessProfile['goal'] ?? ''), 'muscle_gain') !== false ? 'selected' : '' ?>>Muscle Gain</option>
                    </select>
                </label>
                <label>Fitness Level
                    <select name="fitness_level" required>
                        <option value="beginner" <?= ($fitnessProfile['fitness_level'] ?? '') === 'beginner' ? 'selected' : '' ?>>Beginner</option>
                        <option value="intermediate" <?= ($fitnessProfile['fitness_level'] ?? '') === 'intermediate' ? 'selected' : '' ?>>Intermediate</option>
                        <option value="advanced" <?= ($fitnessProfile['fitness_level'] ?? '') === 'advanced' ? 'selected' : '' ?>>Advanced</option>
                    </select>
                </label>
                <label>Training Days/Wk <input type="number" name="training_days_per_week" min="1" max="7" value="<?= htmlspecialchars((string)($fitnessProfile['training_days_per_week'] ?? '')) ?>" required></label>
                <label>Joint Pain
                    <select name="joint_pain" required>
                        <option value="no" <?= ($fitnessProfile['joint_pain'] ?? '') === 'no' ? 'selected' : '' ?>>No</option>
                        <option value="yes" <?= ($fitnessProfile['joint_pain'] ?? '') === 'yes' ? 'selected' : '' ?>>Yes</option>
                    </select>
                </label>
                <label>Duration (min) <input type="number" name="duration_preference" value="<?= htmlspecialchars((string)($fitnessProfile['duration_preference'] ?? '')) ?>"></label>
                
                <div class="profile-form-actions">
                    <button type="button" class="btn small secondary" onclick="toggleEdit('fitness')">Cancel</button>
                    <button type="submit" class="btn small">Save</button>
                </div>
            </form>
            <?php else: ?>
                <p class="muted">No fitness profile found.</p>
                <form method="POST" style="margin-top: 14px;" id="reset-popup-form">
                    <button type="button" class="btn small secondary" 
                        onclick="document.cookie='fg_skip_prof=; Max-Age=-99999; path=/'; fetch('<?= fitgym_esc(fitgym_url('/php/profile_popup_handler.php')) ?>', {method: 'POST', body: new URLSearchParams({action: 'reset_dismissed'})}).then(() => location.reload());">
                        Show Profile Form
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <div class="client-card">
            <div class="section-head">
                <h3>My Bookings</h3>
                <span class="booking-count"><?= count($bookings) ?> booking<?= count($bookings) === 1 ? '' : 's' ?></span>
            </div>
            <p class="booking-help">Bookings automatically expire <?= (int)fitgym_booking_expiry_days() ?> days after the class date. Each reservation shows its expiry date below.</p>

            <?php if (empty($bookings)): ?>
                <p class="muted">No bookings yet. Book a class to see it here.</p>
            <?php else: ?>
                <div class="booking-list">
                    <?php foreach ($bookings as $booking): ?>
                        <div class="booking-item">
                            <div class="booking-copy">
                                <strong><?= htmlspecialchars($booking['class_name']) ?></strong>
                                <span><?= htmlspecialchars($booking['display_date']) ?> | <?= htmlspecialchars($booking['time_slot']) ?></span>
                                <span><?= htmlspecialchars($booking['trainer_name']) ?> | <?= htmlspecialchars($booking['payment_status_label']) ?></span>
                                <span class="booking-expiry <?= htmlspecialchars($booking['expiry_state_class']) ?>">Expiry date: <?= htmlspecialchars($booking['display_expiry_date']) ?></span>
                                <span class="status <?= htmlspecialchars($booking['status_class']) ?>"><?= htmlspecialchars($booking['status'] ?? 'Pending') ?></span>
                            </div>

                            <div class="booking-actions">
                                <button class="btn small secondary details-trigger" type="button" data-booking-modal="booking-detail-<?= (int)$booking['id'] ?>">View Details</button>
                                <?php if (fitgym_booking_status_is_cancellable((string)($booking['status'] ?? 'Pending'))): ?>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="cancel">
                                        <input type="hidden" name="booking_id" value="<?= (int)$booking['id'] ?>">
                                        <button class="btn small" type="submit">Cancel</button>
                                    </form>
                                <?php elseif (($booking['status'] ?? 'Pending') === 'Cancelled'): ?>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="booking_id" value="<?= (int)$booking['id'] ?>">
                                        <button class="btn small" type="submit">Remove</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php foreach ($bookings as $booking): ?>
                    <div class="booking-modal" id="booking-detail-<?= (int)$booking['id'] ?>" aria-hidden="true">
                        <div class="booking-modal-backdrop" data-close-modal></div>
                        <div class="booking-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="booking-modal-title-<?= (int)$booking['id'] ?>">
                            <button class="booking-modal-close" type="button" aria-label="Close details" data-close-modal>&times;</button>

                            <div class="booking-modal-head">
                                <div>
                                    <p class="modal-kicker">Booking Details</p>
                                    <h4 id="booking-modal-title-<?= (int)$booking['id'] ?>"><?= htmlspecialchars($booking['class_name']) ?></h4>
                                    <p class="modal-subtitle"><?= htmlspecialchars($booking['display_date']) ?> | <?= htmlspecialchars($booking['time_slot']) ?></p>
                                </div>
                                <span class="status <?= htmlspecialchars($booking['status_class']) ?>"><?= htmlspecialchars($booking['status'] ?? 'Pending') ?></span>
                            </div>

                            <?php if ($booking['class_image'] !== ''): ?>
                                <img class="booking-modal-image" src="<?= htmlspecialchars($booking['class_image']) ?>" alt="<?= htmlspecialchars($booking['class_name']) ?>">
                            <?php endif; ?>

                            <div class="booking-detail-grid">
                                <div class="booking-detail-row"><span>Booking Ref</span><strong><?= htmlspecialchars($booking['booking_reference']) ?></strong></div>
                                <div class="booking-detail-row"><span>Booking Status</span><strong><?= htmlspecialchars($booking['status'] ?? 'Pending') ?></strong></div>
                                <div class="booking-detail-row"><span>Class</span><strong><?= htmlspecialchars($booking['class_name']) ?></strong></div>
                                <div class="booking-detail-row"><span>Category</span><strong><?= htmlspecialchars($booking['class_category_label']) ?></strong></div>
                                <div class="booking-detail-row"><span>Date</span><strong><?= htmlspecialchars($booking['display_date']) ?></strong></div>
                                <div class="booking-detail-row"><span>Time Slot</span><strong><?= htmlspecialchars((string)($booking['time_slot'] ?? '-')) ?></strong></div>
                                <div class="booking-detail-row"><span>Trainer</span><strong><?= htmlspecialchars($booking['trainer_name']) ?></strong></div>
                                <div class="booking-detail-row"><span>Session Type</span><strong><?= htmlspecialchars($booking['trainer_type_label']) ?></strong></div>
                                <div class="booking-detail-row"><span>Reserved</span><strong><?= htmlspecialchars($booking['participants_label']) ?></strong></div>
                                <div class="booking-detail-row"><span>Payment Provider</span><strong><?= htmlspecialchars($booking['payment_provider_label']) ?></strong></div>
                                <div class="booking-detail-row"><span>Payment Method</span><strong><?= htmlspecialchars($booking['payment_method_label']) ?></strong></div>
                                <div class="booking-detail-row"><span>Payment Status</span><strong><?= htmlspecialchars($booking['payment_status_label']) ?></strong></div>
                                <div class="booking-detail-row"><span>Booked On</span><strong><?= htmlspecialchars($booking['display_created_at']) ?></strong></div>
                                <div class="booking-detail-row"><span>Expiry Date</span><strong><?= htmlspecialchars($booking['display_expiry_date']) ?></strong></div>
                                <div class="booking-detail-row"><span>Location</span><strong><?= htmlspecialchars($booking['class_location']) ?></strong></div>
                                <div class="booking-detail-row"><span>Duration</span><strong><?= htmlspecialchars($booking['class_duration_label']) ?></strong></div>
                                <div class="booking-detail-row"><span>Calories Burn</span><strong><?= htmlspecialchars($booking['class_burn_label']) ?></strong></div>
                                <div class="booking-detail-row"><span>Khalti Order</span><strong><?= htmlspecialchars($booking['payment_order_id'] !== '' ? $booking['payment_order_id'] : '-') ?></strong></div>
                                <div class="booking-detail-row"><span>Khalti Txn</span><strong><?= htmlspecialchars($booking['payment_transaction_id'] !== '' ? $booking['payment_transaction_id'] : ($booking['payment_pidx'] !== '' ? $booking['payment_pidx'] : '-')) ?></strong></div>
                            </div>

                            <div class="booking-detail-note">
                                <strong>Booking expiry</strong>
                                <p>
                                    <?php if (($booking['status_class'] ?? '') === 'expired'): ?>
                                        This booking expired automatically on <?= htmlspecialchars($booking['display_expiry_date']) ?>, which is <?= (int)fitgym_booking_expiry_days() ?> days after the class date.
                                    <?php else: ?>
                                        This booking will stay active until <?= htmlspecialchars($booking['display_expiry_date']) ?>. After that it is archived automatically.
                                    <?php endif; ?>
                                </p>
                            </div>

                            <div class="booking-detail-note">
                                <strong>Payment note</strong>
                                <p><?= htmlspecialchars($booking['payment_status_detail']) ?></p>
                            </div>

                            <?php if ($booking['class_description'] !== ''): ?>
                                <div class="booking-detail-note">
                                    <strong>Class overview</strong>
                                    <p><?= htmlspecialchars($booking['class_description']) ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php include "../footer.php"; ?>

<script>
(() => {
    const body = document.body;
    const modals = Array.from(document.querySelectorAll('.booking-modal'));
    const triggers = Array.from(document.querySelectorAll('.details-trigger'));

    window.toggleEdit = function(idPrefix) {
        const viewEl = document.getElementById(idPrefix + '-view');
        const editEl = document.getElementById(idPrefix + '-edit');
        if (!viewEl || !editEl) return;
        
        if (editEl.classList.contains('is-active')) {
            editEl.classList.remove('is-active');
            viewEl.classList.remove('is-hidden');
        } else {
            editEl.classList.add('is-active');
            viewEl.classList.add('is-hidden');
        }
    };

    if (modals.length === 0 || triggers.length === 0) {
        return;
    }

    const closeModal = (modal) => {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        body.classList.remove('modal-open');
    };

    const openModal = (modal) => {
        modals.forEach((item) => closeModal(item));
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        body.classList.add('modal-open');
    };

    triggers.forEach((trigger) => {
        trigger.addEventListener('click', () => {
            const modalId = trigger.getAttribute('data-booking-modal');
            if (!modalId) {
                return;
            }
            const modal = document.getElementById(modalId);
            if (!modal) {
                return;
            }
            openModal(modal);
        });
    });

    modals.forEach((modal) => {
        modal.querySelectorAll('[data-close-modal]').forEach((button) => {
            button.addEventListener('click', () => closeModal(modal));
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }
        modals.forEach((modal) => closeModal(modal));
    });
})();
</script>
</body>
</html>
