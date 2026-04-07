<?php
session_start();
require_once __DIR__ . '/dynamic_content.php';
require_once __DIR__ . '/class_recommendation_helpers.php';

$availableClasses = array_map('fitgym_normalize_class_row', fitgym_get_classes());
$recommendableClasses = array_values(array_filter(
    $availableClasses,
    static fn(array $classRow): bool => !empty($classRow['recommendation_ready'])
));

$goalOptions = fitgym_goal_options();
$availableGoalOptions = $goalOptions;

if (!function_exists('fitgym_recommendation_score_percent')) {
    function fitgym_recommendation_score_percent(int $score): int
    {
        $maxScore = 122;
        return max(0, min(100, (int)round(($score / $maxScore) * 100)));
    }
}

if (!function_exists('fitgym_recommendation_match_state')) {
    function fitgym_recommendation_match_state(string $type, array $classRow, array $context): array
    {
        $goalLabel = fitgym_goal_options()[$context['goal'] ?? 'maintenance'] ?? 'Maintenance';
        $classIntensity = fitgym_normalize_intensity((string)($classRow['intensity_level'] ?? ''));
        $targetIntensity = fitgym_normalize_intensity((string)($context['intensity_preference'] ?? ''));
        $classRank = fitgym_level_rank((string)($classRow['fitness_level'] ?? ''));
        $userRank = fitgym_level_rank((string)($context['fitness_level'] ?? ''));
        $burnTarget = max(0, (int)($context['target_burn_per_session'] ?? 0));
        [$burnMin, $burnMax] = fitgym_class_burn_range($classRow);
        $frequency = fitgym_nullable_int($classRow['recommended_frequency_per_week'] ?? null, 1);
        $targetFrequency = max(0, (int)($context['weekly_training_days'] ?? 0));
        $burnDistance = (int)($classRow['_burn_distance'] ?? PHP_INT_MAX);
        $intensityGap = abs(fitgym_intensity_rank($classIntensity) - fitgym_intensity_rank($targetIntensity));

        if ($type === 'goal') {
            if (!empty($classRow['_goal_match'])) {
                return [
                    'state' => 'match',
                    'title' => 'Supports ' . $goalLabel,
                    'detail' => 'This class is marked by the admin as suitable for your main goal.',
                ];
            }

            return [
                'state' => 'fallback',
                'title' => 'Closest goal fit available',
                'detail' => 'It can still work, but it is not tagged as an exact ' . strtolower($goalLabel) . ' class.',
            ];
        }

        if ($type === 'burn') {
            $rangeLabel = ($burnMin ?? $burnMax ?? 0) . '-' . ($burnMax ?? $burnMin ?? 0) . ' kcal / session';
            if ($burnDistance === 0) {
                return [
                    'state' => 'match',
                    'title' => $rangeLabel,
                    'detail' => 'Your target burn of ' . $burnTarget . ' kcal sits inside this class range.',
                ];
            }
            if ($burnDistance <= 40) {
                return [
                    'state' => 'close',
                    'title' => $rangeLabel,
                    'detail' => 'Very close to your target burn of ' . $burnTarget . ' kcal per session.',
                ];
            }
            if ($burnDistance <= 120) {
                return [
                    'state' => 'close',
                    'title' => $rangeLabel,
                    'detail' => 'Reasonably close to your target burn of ' . $burnTarget . ' kcal.',
                ];
            }

            return [
                'state' => 'fallback',
                'title' => $rangeLabel,
                'detail' => 'Further away from your target burn of ' . $burnTarget . ' kcal.',
            ];
        }

        if ($type === 'intensity') {
            $targetLabel = fitgym_labelize_token($targetIntensity !== '' ? $targetIntensity : 'medium') . ' target';
            $classLabel = fitgym_labelize_token($classIntensity !== '' ? $classIntensity : 'unspecified') . ' class';
            if ($intensityGap === 0) {
                return [
                    'state' => 'match',
                    'title' => $classLabel,
                    'detail' => 'Matches your ' . strtolower($targetLabel) . '.',
                ];
            }
            if ($intensityGap === 1) {
                return [
                    'state' => 'close',
                    'title' => $classLabel,
                    'detail' => 'One step away from your ' . strtolower($targetLabel) . '.',
                ];
            }

            return [
                'state' => 'fallback',
                'title' => $classLabel,
                'detail' => 'Not a strong intensity fit for your current plan.',
            ];
        }

        if ($type === 'fitness') {
            $classLabel = fitgym_labelize_token((string)($classRow['fitness_level'] ?? 'beginner'));
            $userLabel = fitgym_labelize_token((string)($context['fitness_level'] ?? 'beginner'));
            if ($classRank === $userRank) {
                return [
                    'state' => 'match',
                    'title' => $classLabel . ' level',
                    'detail' => 'This class is built right at your current training level.',
                ];
            }

            return [
                'state' => 'close',
                'title' => $classLabel . ' level',
                'detail' => 'Safe for your ' . strtolower($userLabel) . ' level, even if it is a bit easier.',
            ];
        }

        if ($type === 'frequency') {
            $classLabel = ($frequency ?? 0) . ' day' . (($frequency ?? 0) === 1 ? '' : 's') . ' / week';
            $frequencyGap = abs($targetFrequency - (int)$frequency);
            if ($frequency !== null && $frequencyGap === 0) {
                return [
                    'state' => 'match',
                    'title' => $classLabel,
                    'detail' => 'Lined up with your weekly training plan.',
                ];
            }
            if ($frequency !== null && $frequencyGap === 1) {
                return [
                    'state' => 'close',
                    'title' => $classLabel,
                    'detail' => 'Very close to your plan of ' . $targetFrequency . ' day' . ($targetFrequency === 1 ? '' : 's') . ' per week.',
                ];
            }

            return [
                'state' => 'neutral',
                'title' => $classLabel,
                'detail' => 'You can still use this class, but its ideal rhythm is different from your weekly plan.',
            ];
        }

        if (!empty($context['needs_low_impact']) || !empty($context['joint_friendly_required'])) {
            return [
                'state' => 'match',
                'title' => 'Safety requirement met',
                'detail' => 'This class passed your low-impact and joint-friendly safety filter.',
            ];
        }

        if ((int)($classRow['low_impact'] ?? 0) === 1 || (int)($classRow['joint_friendly'] ?? 0) === 1) {
            return [
                'state' => 'close',
                'title' => 'Joint-friendlier option',
                'detail' => 'This class has a gentler profile than many standard-intensity choices.',
            ];
        }

        return [
            'state' => 'neutral',
            'title' => 'Standard impact profile',
            'detail' => 'No extra safety restrictions were required for your current plan.',
        ];
    }
}

if (!function_exists('fitgym_recommendation_card_payload')) {
    function fitgym_recommendation_card_payload(array $classRow, array $context, bool $hasExactMatch): array
    {
        $classRow = fitgym_normalize_class_row($classRow);
        $scorePercent = fitgym_recommendation_score_percent((int)($classRow['score'] ?? 0));
        $durationLabel = !empty($classRow['duration_minutes']) ? (int)$classRow['duration_minutes'] . ' min' : 'Flexible';
        $burnLabel = 'Burn not specified';
        if (($classRow['burn_min_resolved'] ?? null) !== null || ($classRow['burn_max_resolved'] ?? null) !== null) {
            $burnLabel = (int)($classRow['burn_min_resolved'] ?? $classRow['burn_max_resolved'] ?? 0)
                . '-' . (int)($classRow['burn_max_resolved'] ?? $classRow['burn_min_resolved'] ?? 0)
                . ' kcal';
        }

        $tags = array_values(array_filter([
            (string)($classRow['category_label'] ?? 'General'),
            $durationLabel,
            $burnLabel,
            ((int)($classRow['low_impact'] ?? 0) === 1) ? 'Low impact' : '',
            ((int)($classRow['joint_friendly'] ?? 0) === 1) ? 'Joint friendly' : '',
            ((int)($classRow['requires_equipment'] ?? 0) === 1) ? 'Equipment required' : 'No equipment needed',
        ]));

        $criteria = [
            ['label' => 'Goal'] + fitgym_recommendation_match_state('goal', $classRow, $context),
            ['label' => 'Calories Burn'] + fitgym_recommendation_match_state('burn', $classRow, $context),
            ['label' => 'Intensity'] + fitgym_recommendation_match_state('intensity', $classRow, $context),
            ['label' => 'Fitness Level'] + fitgym_recommendation_match_state('fitness', $classRow, $context),
            ['label' => 'Weekly Rhythm'] + fitgym_recommendation_match_state('frequency', $classRow, $context),
            ['label' => 'Safety'] + fitgym_recommendation_match_state('safety', $classRow, $context),
        ];
        $statePriority = ['match' => 1, 'close' => 2, 'neutral' => 3, 'fallback' => 4];
        $highlights = $criteria;
        usort($highlights, static function (array $left, array $right) use ($statePriority): int {
            $leftPriority = $statePriority[$left['state'] ?? 'neutral'] ?? 99;
            $rightPriority = $statePriority[$right['state'] ?? 'neutral'] ?? 99;
            if ($leftPriority !== $rightPriority) {
                return $leftPriority <=> $rightPriority;
            }

            return 0;
        });
        $highlights = array_slice($highlights, 0, 3);

        $fallbackFlags = array_values(array_unique(array_filter((array)($classRow['_fallback_flags'] ?? []))));
        $fallbackNote = '';
        if (!$hasExactMatch && !empty($fallbackFlags)) {
            $fallbackNote = 'Closest available option because ' . implode(' and ', $fallbackFlags) . '.';
        }

        return [
            'score_percent' => $scorePercent,
            'score_label' => $hasExactMatch ? 'Match strength' : 'Closest option',
            'tags' => $tags,
            'highlights' => $highlights,
            'criteria' => $criteria,
            'fallback_note' => $fallbackNote,
            'link' => fitgym_url('/php/class_inside.php') . '?class=' . rawurlencode((string)$classRow['slug']),
        ];
    }
}

$recommendationFieldNames = [
    'age',
    'gender',
    'height_cm',
    'weight_kg',
    'activity',
    'goal',
    'training_days_per_week',
    'fitness_level',
    'joint_pain',
    'duration_preference',
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $redirectPayload = ['run' => '1'];
    foreach ($recommendationFieldNames as $field) {
        $value = $_POST[$field] ?? '';
        if (is_string($value)) {
            $value = trim($value);
        }
        if ($value === '' || $value === null) {
            continue;
        }
        $redirectPayload[$field] = $value;
    }

    $query = http_build_query($redirectPayload);
    header('Location: ' . fitgym_url('/php/recommend.php') . ($query !== '' ? '?' . $query : ''));
    exit;
}

$input = [
    'age' => $_GET['age'] ?? '',
    'gender' => $_GET['gender'] ?? 'female',
    'height_cm' => $_GET['height_cm'] ?? '',
    'weight_kg' => $_GET['weight_kg'] ?? '',
    'activity' => $_GET['activity'] ?? 'moderate',
    'goal' => $_GET['goal'] ?? 'fat_loss',
    'training_days_per_week' => $_GET['training_days_per_week'] ?? '4',
    'fitness_level' => $_GET['fitness_level'] ?? 'beginner',
    'joint_pain' => $_GET['joint_pain'] ?? 'no',
    'duration_preference' => $_GET['duration_preference'] ?? '',
];

$errors = [];
$result = null;

$shouldCalculate = (string)($_GET['run'] ?? '') === '1';

if ($shouldCalculate) {
    $calculation = fitgym_calculate_tdee_context($input);
    $errors = $calculation['errors'];

    if (empty($errors)) {
        $context = $calculation['context'];
        $recommendationSet = fitgym_get_recommended_classes($recommendableClasses, $context);
        $displayRows = $recommendationSet['has_exact_match']
            ? $recommendationSet['exact_matches']
            : $recommendationSet['fallback_alternatives'];
        $weekDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

        $result = [
            'context' => $context,
            'has_exact_match' => $recommendationSet['has_exact_match'],
            'recommendations' => $displayRows,
            'fallback_alternatives' => $recommendationSet['fallback_alternatives'],
            'week_plan' => array_slice($weekDays, 0, min(7, max(1, (int)$context['weekly_training_days']))),
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Class Recommendations | FitGym</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="<?= fitgym_esc(fitgym_asset_url('/css/header.css')) ?>">
    <link rel="stylesheet" href="<?= fitgym_esc(fitgym_asset_url('/css/index.css')) ?>">
    <link rel="stylesheet" href="<?= fitgym_esc(fitgym_asset_url('/css/recommend.css')) ?>">
</head>
<body>
<?php include 'header.php'; ?>

<main class="recommend-page">
    <section class="recommend-hero">
        <div class="hero-inner">
            <span class="hero-pill">FitGym Smart Planner</span>
            <h1>TDEE + Class Recommendations</h1>
            <p>Get a structured calorie plan, a clear recommendation context, and class matches backed by the admin-defined class profile.</p>
        </div>
    </section>

    <section class="recommend-shell">
        <div class="recommend-card">
            <form method="GET" class="recommend-form">
                <input type="hidden" name="run" value="1">
                <h3>Your Details</h3>

                <?php if (!empty($errors)): ?>
                    <div class="form-errors">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?= fitgym_esc($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="grid">
                    <label>Age
                        <input type="number" name="age" min="14" max="90" value="<?= fitgym_esc($input['age']) ?>" required>
                    </label>
                    <label>Gender
                        <select name="gender">
                            <option value="female" <?= $input['gender'] === 'female' ? 'selected' : '' ?>>Female</option>
                            <option value="male" <?= $input['gender'] === 'male' ? 'selected' : '' ?>>Male</option>
                        </select>
                    </label>
                    <label>Height (cm)
                        <input type="number" name="height_cm" min="100" max="250" value="<?= fitgym_esc($input['height_cm']) ?>" required>
                    </label>
                    <label>Weight (kg)
                        <input type="number" name="weight_kg" min="35" max="200" step="0.1" value="<?= fitgym_esc($input['weight_kg']) ?>" required>
                    </label>
                    <label>Activity Level
                        <select name="activity">
                            <option value="sedentary" <?= $input['activity'] === 'sedentary' ? 'selected' : '' ?>>Sedentary</option>
                            <option value="light" <?= $input['activity'] === 'light' ? 'selected' : '' ?>>Light</option>
                            <option value="moderate" <?= $input['activity'] === 'moderate' ? 'selected' : '' ?>>Moderate</option>
                            <option value="active" <?= $input['activity'] === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="very_active" <?= $input['activity'] === 'very_active' ? 'selected' : '' ?>>Very Active</option>
                        </select>
                    </label>
                    <label>Goal
                        <select name="goal">
                            <?php foreach ($availableGoalOptions as $goalValue => $goalLabel): ?>
                                <option value="<?= fitgym_esc($goalValue) ?>" <?= $input['goal'] === $goalValue ? 'selected' : '' ?>><?= fitgym_esc($goalLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Training Days / Week
                        <input type="number" name="training_days_per_week" min="1" max="7" value="<?= fitgym_esc($input['training_days_per_week']) ?>" required>
                    </label>
                    <label>Fitness Level
                        <select name="fitness_level">
                            <option value="beginner" <?= $input['fitness_level'] === 'beginner' ? 'selected' : '' ?>>Beginner</option>
                            <option value="intermediate" <?= $input['fitness_level'] === 'intermediate' ? 'selected' : '' ?>>Intermediate</option>
                            <option value="advanced" <?= $input['fitness_level'] === 'advanced' ? 'selected' : '' ?>>Advanced</option>
                        </select>
                    </label>
                    <label>Joint Pain / Need Low Impact
                        <select name="joint_pain">
                            <option value="no" <?= $input['joint_pain'] === 'no' ? 'selected' : '' ?>>No</option>
                            <option value="yes" <?= $input['joint_pain'] === 'yes' ? 'selected' : '' ?>>Yes</option>
                        </select>
                    </label>
                    <label>Preferred Duration (optional)
                        <input type="number" name="duration_preference" min="15" max="120" step="5" value="<?= fitgym_esc($input['duration_preference']) ?>" placeholder="e.g. 45">
                    </label>
                </div>

                <button type="submit" class="btn">Get Recommendations</button>
            </form>
        </div>

        <aside class="recommend-side">
            <h3>How Matching Works</h3>
            <ul>
                <li>Only classes with a complete recommendation profile can be recommended.</li>
                <li>Goal fit, calorie burn range, fitness level, intensity, and safety rules drive the match.</li>
                <li>TDEE band is only an optional eligibility gate, not the main score.</li>
                <li>If no exact match exists, fallback alternatives are clearly labeled.</li>
            </ul>
            <div class="side-note">
                <strong>Safety First</strong>
                <p>Joint-pain requests only consider classes flagged low-impact and joint-friendly.</p>
            </div>
        </aside>
    </section>

    <?php if ($result !== null): ?>
        <?php $context = $result['context']; ?>
        <section class="result-card">
            <div class="result-head">
                <div>
                    <h3>Your Recommendation Context</h3>
                    <p class="rec-meta">This normalized context is what the class matcher uses.</p>
                </div>
                <span class="intensity-pill"><?= fitgym_esc($context['intensity_preference']) ?> intensity target</span>
            </div>

            <div class="summary-grid stats-grid">
                <div class="stat-card">
                    <span class="stat-label">BMR</span>
                    <strong><?= fitgym_esc((string)$context['bmr']) ?> kcal</strong>
                </div>
                <div class="stat-card">
                    <span class="stat-label">TDEE</span>
                    <strong><?= fitgym_esc((string)$context['tdee']) ?> kcal</strong>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Target Daily Calories</span>
                    <strong><?= fitgym_esc((string)$context['target_daily_calories']) ?> kcal</strong>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Target Burn / Session</span>
                    <strong><?= fitgym_esc((string)$context['target_burn_per_session']) ?> kcal</strong>
                </div>
            </div>

            <div class="summary-grid">
                <div class="stat-card">
                    <span class="stat-label">Goal</span>
                    <strong><?= fitgym_esc($context['goal_label']) ?></strong>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Fitness Level</span>
                    <strong><?= fitgym_esc(fitgym_labelize_token($context['fitness_level'])) ?></strong>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Training Days</span>
                    <strong><?= fitgym_esc((string)$context['weekly_training_days']) ?> / week</strong>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Safety Need</span>
                    <strong><?= !empty($context['needs_low_impact']) ? 'Low impact required' : 'Standard options allowed' ?></strong>
                </div>
            </div>

            <p class="rec-note">
                Weekly calorie gap: <strong><?= fitgym_esc((string)$context['weekly_target_burn']) ?> kcal</strong>
                <?php if (!empty($context['duration_preference'])): ?>
                    <span class="inline-note">Preferred duration: <?= fitgym_esc((string)$context['duration_preference']) ?> min</span>
                <?php endif; ?>
            </p>

            <h4>Weekly Schedule</h4>
            <ul class="week-plan">
                <?php foreach ($result['week_plan'] as $day): ?>
                    <li><?= fitgym_esc($day) ?></li>
                <?php endforeach; ?>
            </ul>

            <?php if ($result['has_exact_match']): ?>
                <h4>Best Class Matches</h4>
                <p class="rec-meta">These classes passed the strict filters and ranked highest for your current plan. Each card shows exactly what matched.</p>
            <?php else: ?>
                <div class="fallback-banner">
                    <strong>No exact class match found.</strong>
                    <span>These fallback alternatives are the closest available options from the current class catalog. They are shown with a clear reason, not as exact matches.</span>
                </div>
            <?php endif; ?>

            <ul class="rec-list">
                <?php if (empty($result['recommendations'])): ?>
                    <li class="rec-empty">No recommendation-ready class is available for this combination right now.</li>
                <?php else: ?>
                    <?php foreach ($result['recommendations'] as $index => $classRow): ?>
                        <?php $card = fitgym_recommendation_card_payload($classRow, $context, $result['has_exact_match']); ?>
                        <li class="rec-item <?= !$result['has_exact_match'] ? 'is-fallback' : '' ?>">
                            <div class="rec-card">
                                <div class="rec-card-head">
                                    <div class="rec-card-title">
                                        <div class="rec-rank">#<?= fitgym_esc((string)($index + 1)) ?></div>
                                        <div>
                                            <div class="rec-title-row">
                                                <h5 class="rec-title"><?= fitgym_esc((string)$classRow['class_name']) ?></h5>
                                                <?php if (!$result['has_exact_match']): ?>
                                                    <span class="rec-tag fallback">Fallback</span>
                                                <?php else: ?>
                                                    <span class="rec-tag exact">Exact match</span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="rec-subtitle">
                                                <?= fitgym_esc((string)($classRow['category_label'] ?: 'General')) ?> |
                                                <?= fitgym_esc(fitgym_labelize_token((string)$classRow['fitness_level'])) ?> |
                                                <?= fitgym_esc(fitgym_labelize_token((string)$classRow['intensity_level'])) ?> intensity
                                            </p>
                                        </div>
                                    </div>

                                    <div class="rec-score-block">
                                        <span><?= fitgym_esc($card['score_label']) ?></span>
                                        <strong><?= fitgym_esc((string)$card['score_percent']) ?>%</strong>
                                    </div>
                                </div>

                                <?php if (!empty($card['tags'])): ?>
                                    <div class="rec-tag-row">
                                        <?php foreach ($card['tags'] as $tag): ?>
                                            <span class="mini-tag"><?= fitgym_esc($tag) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="rec-highlights">
                                    <?php foreach ($card['highlights'] as $highlight): ?>
                                        <div class="rec-highlight is-<?= fitgym_esc($highlight['state']) ?>">
                                            <span class="criterion-label"><?= fitgym_esc($highlight['label']) ?></span>
                                            <strong><?= fitgym_esc($highlight['title']) ?></strong>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="rec-footer">
                                    <div>
                                        <p class="rec-why">Why it was picked: <?= fitgym_esc((string)$classRow['reason']) ?></p>
                                        <?php if ($card['fallback_note'] !== ''): ?>
                                            <p class="rec-fallback-note"><?= fitgym_esc($card['fallback_note']) ?></p>
                                        <?php endif; ?>
                                    </div>

                                    <a class="rec-cta" href="<?= fitgym_esc($card['link']) ?>">View Class</a>
                                </div>

                                <details class="rec-details">
                                    <summary>See full match details</summary>
                                    <div class="rec-breakdown">
                                        <?php foreach ($card['criteria'] as $criterion): ?>
                                            <div class="rec-breakdown-card is-<?= fitgym_esc($criterion['state']) ?>">
                                                <span class="criterion-label"><?= fitgym_esc($criterion['label']) ?></span>
                                                <strong><?= fitgym_esc($criterion['title']) ?></strong>
                                                <small><?= fitgym_esc($criterion['detail']) ?></small>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </details>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </section>
    <?php endif; ?>
</main>

<?php include 'footer.php'; ?>
</body>
</html>
