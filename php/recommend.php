<?php
session_start();
require_once "database.php";

$activityMultipliers = [
    'sedentary' => 1.2,
    'light' => 1.375,
    'moderate' => 1.55,
    'active' => 1.725,
    'very_active' => 1.9,
];

$classSlugMap = [
    'Zumba' => 'zumba',
    'Yoga' => 'yoga',
    'Battle Ropes' => 'battle-ropes',
    'Boxing' => 'boxing',
    'HIIT' => 'hiit',
    'Swimming' => 'swimming',
    'Chair Yoga' => 'chair-yoga',
    'Gentle Yoga' => 'gentle-yoga',
    'Stretch & Mobility' => 'stretch-mobility',
    'Beginner Pilates' => 'beginner-pilates',
    'Power Yoga' => 'power-yoga',
    'Bodyweight Strength' => 'bodyweight-strength',
    'Core Conditioning' => 'core-conditioning',
    'Low-Impact Cardio' => 'low-impact-cardio',
    'Dance Fitness' => 'dance-fitness',
    'Functional Training' => 'functional-training',
    'Bootcamp' => 'bootcamp',
    'Spin / Indoor Cycling' => 'spin',
    'Cardio Kickboxing' => 'cardio-kickboxing',
];

$errors = [];
$result = null;

$input = [
    'age' => $_POST['age'] ?? '',
    'gender' => $_POST['gender'] ?? 'female',
    'height_cm' => $_POST['height_cm'] ?? '',
    'weight_kg' => $_POST['weight_kg'] ?? '',
    'activity' => $_POST['activity'] ?? 'moderate',
    'goal' => $_POST['goal'] ?? 'fat_loss',
    'sessions_per_week' => $_POST['sessions_per_week'] ?? '4',
    'experience' => $_POST['experience'] ?? 'beginner',
    'joint_pain' => $_POST['joint_pain'] ?? 'no',
];

function calc_bmr($gender, $weight, $height, $age) {
    // Mifflin-St Jeor
    if ($gender === 'male') {
        return 10 * $weight + 6.25 * $height - 5 * $age + 5;
    }
    return 10 * $weight + 6.25 * $height - 5 * $age - 161;
}

function goal_adjustment($goal) {
    switch ($goal) {
        case 'fat_loss':
            return 0.20; // 20% deficit
        case 'muscle_gain':
            return -0.12; // 12% surplus (negative deficit)
        default:
            return 0.0;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $age = (int)$input['age'];
    $height = (float)$input['height_cm'];
    $weight = (float)$input['weight_kg'];
    $sessions = (int)$input['sessions_per_week'];

    if ($age < 14 || $age > 90) $errors[] = 'Age must be between 14 and 90.';
    if ($height < 100 || $height > 250) $errors[] = 'Height must be between 100 and 250 cm.';
    if ($weight < 35 || $weight > 200) $errors[] = 'Weight must be between 35 and 200 kg.';
    if (!isset($activityMultipliers[$input['activity']])) $errors[] = 'Select a valid activity level.';
    if ($sessions < 1 || $sessions > 7) $errors[] = 'Sessions per week must be 1 to 7.';

    if (empty($errors)) {
        $bmr = calc_bmr($input['gender'], $weight, $height, $age);
        $tdee = $bmr * $activityMultipliers[$input['activity']];
        $deficit = goal_adjustment($input['goal']);
        $desired_intake = $tdee * (1 - $deficit);
        $weekly_deficit = $tdee - $desired_intake; // negative for surplus
        $per_session_burn = $sessions > 0 ? $weekly_deficit / $sessions : 0;

        $result = [
            'bmr' => round($bmr),
            'tdee' => round($tdee),
            'desired_intake' => round($desired_intake),
            'target_burn' => round(max($per_session_burn, 0)),
        ];

        // Build recommendation tags
        $safety_low_impact = ($input['experience'] === 'beginner' || $input['joint_pain'] === 'yes');
        $intensity_band = 'moderate';
        if ($result['target_burn'] <= 200) $intensity_band = 'low';
        elseif ($result['target_burn'] >= 450) $intensity_band = 'high';

        $stmt = $conn->prepare(
            "SELECT class_name, intensity_level, impact_level, goal_tags, avg_calories_per_hour
             FROM class_catalog
             WHERE (impact_level = 'low' OR ? = 0)
               AND (goal_tags LIKE CONCAT('%', ?, '%') OR ? = 'maintenance')
               AND intensity_level = ?
             ORDER BY avg_calories_per_hour ASC"
        );

        $goalTag = $input['goal'];
        $impactGate = $safety_low_impact ? 1 : 0;
        $stmt->bind_param('isss', $impactGate, $goalTag, $goalTag, $intensity_band);
        $stmt->execute();
        $rec = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $result['recommendations'] = $rec;
        $result['intensity'] = $intensity_band;
        $result['sessions'] = $sessions;
        $result['weekly_burn'] = round($result['target_burn'] * $sessions);

        $avg_kcal_per_hour = 300;
        if (!empty($rec)) {
            $avg_kcal_per_hour = (int)round(array_sum(array_column($rec, 'avg_calories_per_hour')) / count($rec));
        } elseif ($intensity_band === 'low') {
            $avg_kcal_per_hour = 240;
        } elseif ($intensity_band === 'high') {
            $avg_kcal_per_hour = 520;
        } else {
            $avg_kcal_per_hour = 360;
        }

        $daily_minutes = $avg_kcal_per_hour > 0 ? round(($result['target_burn'] / $avg_kcal_per_hour) * 60) : 30;
        if ($daily_minutes < 15) $daily_minutes = 15;
        if ($daily_minutes > 90) $daily_minutes = 90;

        $result['avg_kcal_per_hour'] = $avg_kcal_per_hour;
        $result['daily_minutes'] = $daily_minutes;
        $result['progress_weeks'] = $input['goal'] === 'fat_loss' ? 8 : ($input['goal'] === 'muscle_gain' ? 12 : 6);

        $weekDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $planDays = array_slice($weekDays, 0, min(7, max(1, $sessions)));
        $result['week_plan'] = $planDays;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Class Recommendations | FitGym</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/fitgym/css/header.css">
    <link rel="stylesheet" href="/fitgym/css/index.css">
    <link rel="stylesheet" href="/fitgym/css/recommend.css">
</head>
<body class="no-logo-page">
<?php include "header.php"; ?>

<main class="recommend-page">
    <section class="recommend-hero">
        <div class="hero-inner">
            <span class="hero-pill">FitGym Smart Planner</span>
            <h1>TDEE + Class Recommendations</h1>
            <p>Get science-backed class intensity and weekly frequency suggestions with recovery in mind.</p>
        </div>
    </section>

    <section class="recommend-shell">
        <div class="recommend-card">
            <form method="POST" class="recommend-form">
            <h3>Your Details</h3>

            <?php if (!empty($errors)): ?>
                <div class="form-errors">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="grid">
                <label>Age
                    <input type="number" name="age" value="<?= htmlspecialchars($input['age']) ?>" required>
                </label>
                <label>Gender
                    <select name="gender">
                        <option value="female" <?= $input['gender']==='female'?'selected':'' ?>>Female</option>
                        <option value="male" <?= $input['gender']==='male'?'selected':'' ?>>Male</option>
                    </select>
                </label>
                <label>Height (cm)
                    <input type="number" name="height_cm" min="100" max="250" value="<?= htmlspecialchars($input['height_cm']) ?>" required>
                </label>
                <label>Weight (kg)
                    <input type="number" name="weight_kg" value="<?= htmlspecialchars($input['weight_kg']) ?>" required>
                </label>
                <label>Activity Level
                    <select name="activity">
                        <option value="sedentary" <?= $input['activity']==='sedentary'?'selected':'' ?>>Sedentary</option>
                        <option value="light" <?= $input['activity']==='light'?'selected':'' ?>>Light</option>
                        <option value="moderate" <?= $input['activity']==='moderate'?'selected':'' ?>>Moderate</option>
                        <option value="active" <?= $input['activity']==='active'?'selected':'' ?>>Active</option>
                        <option value="very_active" <?= $input['activity']==='very_active'?'selected':'' ?>>Very Active</option>
                    </select>
                </label>
                <label>Goal
                    <select name="goal">
                        <option value="fat_loss" <?= $input['goal']==='fat_loss'?'selected':'' ?>>Fat Loss</option>
                        <option value="maintenance" <?= $input['goal']==='maintenance'?'selected':'' ?>>Maintenance</option>
                        <option value="muscle_gain" <?= $input['goal']==='muscle_gain'?'selected':'' ?>>Muscle Gain</option>
                    </select>
                </label>
                <label>Sessions / Week
                    <input type="number" name="sessions_per_week" min="1" max="7" value="<?= htmlspecialchars($input['sessions_per_week']) ?>" required>
                </label>
                <label>Experience
                    <select name="experience">
                        <option value="beginner" <?= $input['experience']==='beginner'?'selected':'' ?>>Beginner</option>
                        <option value="intermediate" <?= $input['experience']==='intermediate'?'selected':'' ?>>Intermediate</option>
                        <option value="advanced" <?= $input['experience']==='advanced'?'selected':'' ?>>Advanced</option>
                    </select>
                </label>
                <label>Joint Pain / Injury
                    <select name="joint_pain">
                        <option value="no" <?= $input['joint_pain']==='no'?'selected':'' ?>>No</option>
                        <option value="yes" <?= $input['joint_pain']==='yes'?'selected':'' ?>>Yes</option>
                    </select>
                </label>
            </div>

            <button type="submit" class="btn">Get Recommendations</button>
        </form>
        </div>

        <aside class="recommend-side">
            <h3>What You’ll Get</h3>
            <ul>
                <li>Daily TDEE estimate</li>
                <li>Target calorie burn per session</li>
                <li>Safe intensity based on recovery</li>
                <li>Goal-aligned class suggestions</li>
            </ul>
            <div class="side-note">
                <strong>Safety First</strong>
                <p>Beginner or joint pain = low‑impact class options only.</p>
            </div>
        </aside>
    </section>

    <?php if ($result): ?>
    <section class="result-card simple">
        <div class="result-head centered">
            <h3>Your Weekly Plan</h3>
            <span class="intensity-pill">Intensity: <?= htmlspecialchars($result['intensity']) ?></span>
        </div>
        <div class="summary-grid stats-grid">
            <div class="stat-card">
                <span class="stat-label">Weekly Sessions</span>
                <strong><?= htmlspecialchars($result['sessions']) ?></strong>
            </div>
            <div class="stat-card">
                <span class="stat-label">Weekly Burn</span>
                <strong><?= htmlspecialchars($result['weekly_burn']) ?> kcal</strong>
            </div>
            <div class="stat-card">
                <span class="stat-label">Daily Workout</span>
                <strong><?= htmlspecialchars($result['daily_minutes']) ?> min</strong>
            </div>
            <div class="stat-card">
                <span class="stat-label">Progress Check</span>
                <strong><?= htmlspecialchars($result['progress_weeks']) ?> weeks</strong>
            </div>
        </div>
        <p class="rec-note">Avg burn rate: <strong><?= htmlspecialchars($result['avg_kcal_per_hour']) ?> kcal/hr</strong></p>

        <h4>Weekly Schedule</h4>
        <ul class="week-plan">
            <?php foreach ($result['week_plan'] as $day): ?>
                <li><?= htmlspecialchars($day) ?></li>
            <?php endforeach; ?>
        </ul>

        <h4>Suggested Classes</h4>
        <ul class="rec-list">
            <?php if (empty($result['recommendations'])): ?>
                <li>No exact matches. Try adjusting activity level or goal.</li>
            <?php else: ?>
                <?php foreach ($result['recommendations'] as $r): ?>
                    <?php
                        $slug = $classSlugMap[$r['class_name']] ?? '';
                        $link = $slug ? "/fitgym/php/class_inside.php?class={$slug}" : "/fitgym/php/classes.php";
                    ?>
                    <li>
                        <a class="rec-link" href="<?= htmlspecialchars($link) ?>">
                            <?= htmlspecialchars($r['class_name']) ?>
                            <span><?= htmlspecialchars($r['intensity_level']) ?> intensity · <?= htmlspecialchars($r['impact_level']) ?> impact · <?= htmlspecialchars($r['avg_calories_per_hour']) ?> kcal/hr</span>
                        </a>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </section>
    <?php endif; ?>
</main>

<?php include "footer.php"; ?>
</body>
</html>
