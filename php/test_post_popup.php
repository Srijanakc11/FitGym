<?php
session_start();
$_SESSION['auth_role'] = 'client';
$_SESSION['auth_id'] = 1;

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'age' => '25',
    'gender' => 'male',
    'height_cm' => '175',
    'weight_kg' => '80',
    'activity' => 'moderate',
    'goal' => 'muscle_gain',
    'training_days_per_week' => '4',
    'fitness_level' => 'intermediate',
    'joint_pain' => 'no',
    'duration_preference' => '45',
];

ob_start();
require_once __DIR__ . '/profile_popup_handler.php';
$output = ob_get_clean();

echo "Output:\n" . $output . "\n";
