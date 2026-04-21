<?php
require_once __DIR__ . '/php/auth_common.php';

header('Content-Type: text/plain');
global $conn;

if (!$conn) {
    die("No connection");
}

$data = [
    'age' => 26,
    'gender' => 'female',
    'height_cm' => 170,
    'weight_kg' => 65,
    'activity' => 'active',
    'goal' => 'muscle_gain',
    'training_days_per_week' => 4,
    'fitness_level' => 'advanced',
    'joint_pain' => 'no',
    'duration_preference' => 60
];

echo "Updating existing row for account_id 1...\n";
$ok = fitgym_save_user_fitness_profile(1, $data);
if (!$ok) {
    echo "ERROR: " . $conn->error . "\n";
} else {
    echo "SUCCESS\n";
    $res = $conn->query("SELECT * FROM user_fitness_profiles WHERE account_id = 1");
    print_r($res->fetch_all(MYSQLI_ASSOC));
}
