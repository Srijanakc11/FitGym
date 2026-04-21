<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/auth_common.php';

header('Content-Type: application/json');

// Only authenticated clients
$role      = $_SESSION['auth_role'] ?? '';
$accountId = (int)($_SESSION['auth_id'] ?? 0);

if ($role !== 'client' || $accountId <= 0) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Not authorised.']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST required.']);
    exit;
}

// Validate required fields
$required = ['age', 'gender', 'height_cm', 'weight_kg', 'activity', 'goal', 'training_days_per_week', 'fitness_level', 'joint_pain'];
$errors   = [];

foreach ($required as $field) {
    $value = trim((string)($_POST[$field] ?? ''));
    if ($value === '') {
        $errors[] = $field . ' is required.';
    }
}

$age          = (int)($_POST['age'] ?? 0);
$trainingDays = (int)($_POST['training_days_per_week'] ?? 0);
$heightCm     = (float)($_POST['height_cm'] ?? 0);
$weightKg     = (float)($_POST['weight_kg'] ?? 0);

if ($age < 14 || $age > 100)                      { $errors[] = 'Age must be between 14 and 100.'; }
if ($heightCm < 100 || $heightCm > 250)           { $errors[] = 'Height must be between 100 and 250 cm.'; }
if ($weightKg < 20 || $weightKg > 300)            { $errors[] = 'Weight must be between 20 and 300 kg.'; }
if ($trainingDays < 1 || $trainingDays > 7)       { $errors[] = 'Training days must be between 1 and 7.'; }

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'errors' => $errors]);
    exit;
}

$data = [
    'age'                     => $_POST['age'],
    'gender'                  => $_POST['gender'],
    'height_cm'               => $_POST['height_cm'],
    'weight_kg'               => $_POST['weight_kg'],
    'activity'                => $_POST['activity'],
    'goal'                    => $_POST['goal'],
    'training_days_per_week'  => $_POST['training_days_per_week'],
    'fitness_level'           => $_POST['fitness_level'],
    'joint_pain'              => $_POST['joint_pain'],
    'duration_preference'     => $_POST['duration_preference'] ?? '',
];

$ok = fitgym_save_user_fitness_profile($accountId, $data);

if ($ok) {
    $_SESSION['profile_popup_dismissed'] = true;
    echo json_encode(['ok' => true]);
} else {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not save profile. Please try again.']);
}
