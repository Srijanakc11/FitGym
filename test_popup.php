<?php
session_start();
$_SESSION['auth_role'] = 'client';
$_SESSION['auth_id'] = 4;

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'age' => '25',
    'gender' => 'male',
    'height_cm' => '170',
    'weight_kg' => '65',
    'activity' => 'moderate',
    'goal' => 'fat_loss',
    'training_days_per_week' => '4',
    'fitness_level' => 'intermediate',
    'joint_pain' => 'no',
    'duration_preference' => '45'
];

require_once __DIR__ . '/php/profile_popup_handler.php';
