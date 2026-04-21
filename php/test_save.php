<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth_common.php';

$accountId = 1;
$data = [
    'age' => 25,
    'gender' => 'Male',
    'height_cm' => 180,
    'weight_kg' => 75,
    'activity' => 'high',
    'goal' => 'muscle',
    'training_days_per_week' => 5,
    'fitness_level' => 'intermediate',
    'joint_pain' => 'No',
    'duration_preference' => 60
];

$ok = fitgym_save_user_fitness_profile($accountId, $data);
if (!$ok) {
    echo "Error: " . $conn->error . "\n";
} else {
    echo "Success!\n";
}
