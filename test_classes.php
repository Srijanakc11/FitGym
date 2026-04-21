<?php
require_once __DIR__ . '/php/class_recommendation_helpers.php';
require_once __DIR__ . '/php/database.php';

$allClasses = array_map('fitgym_normalize_class_row', fitgym_get_classes());
$recommendableClasses = array_values(array_filter(
    $allClasses,
    static fn(array $r): bool => !empty($r['recommendation_ready'])
));

print_r(count($recommendableClasses));
