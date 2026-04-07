<?php
require_once __DIR__ . '/../dynamic_content.php';
require_once __DIR__ . '/../class_recommendation_helpers.php';

$classes = array_map('fitgym_normalize_class_row', fitgym_get_classes());
$recommendableClasses = array_values(array_filter(
    $classes,
    static fn(array $classRow): bool => !empty($classRow['recommendation_ready'])
));

$scenarios = [
    'beginner_fat_loss_joint_pain' => [
        'label' => 'Beginner + fat loss + joint pain',
        'input' => [
            'age' => 29,
            'gender' => 'female',
            'height_cm' => 162,
            'weight_kg' => 68,
            'activity' => 'light',
            'goal' => 'fat_loss',
            'training_days_per_week' => 3,
            'fitness_level' => 'beginner',
            'joint_pain' => 'yes',
            'duration_preference' => 45,
        ],
    ],
    'beginner_maintenance_safe' => [
        'label' => 'Beginner + maintenance + no joint pain',
        'input' => [
            'age' => 33,
            'gender' => 'male',
            'height_cm' => 174,
            'weight_kg' => 74,
            'activity' => 'moderate',
            'goal' => 'maintenance',
            'training_days_per_week' => 3,
            'fitness_level' => 'beginner',
            'joint_pain' => 'no',
            'duration_preference' => 45,
        ],
    ],
    'intermediate_fat_loss_4_days' => [
        'label' => 'Intermediate + fat loss + 4 sessions/week',
        'input' => [
            'age' => 31,
            'gender' => 'male',
            'height_cm' => 178,
            'weight_kg' => 82,
            'activity' => 'moderate',
            'goal' => 'fat_loss',
            'training_days_per_week' => 4,
            'fitness_level' => 'intermediate',
            'joint_pain' => 'no',
            'duration_preference' => 45,
        ],
    ],
    'advanced_muscle_gain' => [
        'label' => 'Advanced + muscle gain',
        'input' => [
            'age' => 28,
            'gender' => 'male',
            'height_cm' => 181,
            'weight_kg' => 86,
            'activity' => 'active',
            'goal' => 'muscle_gain',
            'training_days_per_week' => 4,
            'fitness_level' => 'advanced',
            'joint_pain' => 'no',
            'duration_preference' => 60,
        ],
    ],
    'beginner_mobility_joint_pain' => [
        'label' => 'Beginner + mobility + joint pain',
        'input' => [
            'age' => 41,
            'gender' => 'female',
            'height_cm' => 159,
            'weight_kg' => 63,
            'activity' => 'light',
            'goal' => 'mobility',
            'training_days_per_week' => 3,
            'fitness_level' => 'beginner',
            'joint_pain' => 'yes',
            'duration_preference' => 45,
        ],
    ],
    'intermediate_endurance' => [
        'label' => 'Intermediate + endurance',
        'input' => [
            'age' => 34,
            'gender' => 'male',
            'height_cm' => 176,
            'weight_kg' => 78,
            'activity' => 'active',
            'goal' => 'endurance',
            'training_days_per_week' => 4,
            'fitness_level' => 'intermediate',
            'joint_pain' => 'no',
            'duration_preference' => 45,
        ],
    ],
    'stress_relief_low_burn' => [
        'label' => 'User with stress relief goal',
        'input' => [
            'age' => 38,
            'gender' => 'female',
            'height_cm' => 164,
            'weight_kg' => 61,
            'activity' => 'light',
            'goal' => 'stress_relief',
            'training_days_per_week' => 2,
            'fitness_level' => 'beginner',
            'joint_pain' => 'no',
            'duration_preference' => 40,
        ],
    ],
    'low_burn_target' => [
        'label' => 'User with low burn target',
        'input' => [
            'age' => 45,
            'gender' => 'female',
            'height_cm' => 160,
            'weight_kg' => 58,
            'activity' => 'sedentary',
            'goal' => 'maintenance',
            'training_days_per_week' => 2,
            'fitness_level' => 'beginner',
            'joint_pain' => 'yes',
            'duration_preference' => 40,
        ],
    ],
    'high_burn_target' => [
        'label' => 'User with high burn target',
        'input' => [
            'age' => 27,
            'gender' => 'male',
            'height_cm' => 183,
            'weight_kg' => 92,
            'activity' => 'very_active',
            'goal' => 'fat_loss',
            'training_days_per_week' => 5,
            'fitness_level' => 'advanced',
            'joint_pain' => 'no',
            'duration_preference' => 45,
        ],
    ],
    'no_exact_match' => [
        'label' => 'No exact match case',
        'input' => [
            'age' => 30,
            'gender' => 'male',
            'height_cm' => 185,
            'weight_kg' => 95,
            'activity' => 'active',
            'goal' => 'muscle_gain',
            'training_days_per_week' => 5,
            'fitness_level' => 'beginner',
            'joint_pain' => 'yes',
            'duration_preference' => 45,
        ],
    ],
];

header('Content-Type: text/plain; charset=UTF-8');

foreach ($scenarios as $scenarioKey => $scenario) {
    $calculation = fitgym_calculate_tdee_context($scenario['input']);
    echo "=== {$scenario['label']} ({$scenarioKey}) ===\n";

    if (!empty($calculation['errors'])) {
        echo "Validation errors: " . implode('; ', $calculation['errors']) . "\n\n";
        continue;
    }

    $context = $calculation['context'];
    $result = fitgym_get_recommended_classes($recommendableClasses, $context);
    $rows = $result['has_exact_match'] ? $result['exact_matches'] : $result['fallback_alternatives'];
    $mode = $result['has_exact_match'] ? 'Exact matches' : 'Fallback alternatives';

    echo "TDEE: {$context['tdee']} kcal | Burn/session: {$context['target_burn_per_session']} kcal | Intensity: {$context['intensity_preference']}\n";
    echo $mode . ":\n";

    if (empty($rows)) {
        echo "- none\n\n";
        continue;
    }

    foreach (array_slice($rows, 0, 3) as $index => $row) {
        $rank = $index + 1;
        $burnRange = ($row['burn_min_resolved'] ?? null) !== null || ($row['burn_max_resolved'] ?? null) !== null
            ? (($row['burn_min_resolved'] ?? $row['burn_max_resolved']) . '-' . ($row['burn_max_resolved'] ?? $row['burn_min_resolved']) . ' kcal')
            : 'n/a';
        echo "{$rank}. {$row['class_name']} | score {$row['score']} | {$row['fitness_level']} | {$row['intensity_level']} | {$burnRange}\n";
        echo "   {$row['reason']}\n";
    }

    echo "\n";
}
