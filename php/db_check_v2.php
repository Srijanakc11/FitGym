<?php
// Mocking session and other globals if needed
$_SESSION = [];
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/dynamic_content.php';

if (!$conn) {
    echo "Database connection failed!\n";
    exit;
}

echo "Database connected.\n";

$activeBookingWhere = fitgym_booking_active_sql();
echo "Active Booking Where: $activeBookingWhere\n";

// Attempt to run the query manually and see error
$hasScheduleConfigColumn = fitgym_table_has_column('classes_admin', 'schedule_config');
$hasImagePathColumn = fitgym_table_has_column('classes_admin', 'image_path');
$hasImageMimeColumn = fitgym_table_has_column('classes_admin', 'image_mime');
$hasImageDataColumn = fitgym_table_has_column('classes_admin', 'image_data');
$hasCategoryColumn = fitgym_table_has_column('classes_admin', 'category');
$hasDescriptionColumn = fitgym_table_has_column('classes_admin', 'description');
$hasIntensityLevelColumn = fitgym_table_has_column('classes_admin', 'intensity_level');
$hasFitnessLevelColumn = fitgym_table_has_column('classes_admin', 'fitness_level');

$scheduleConfigSelect = $hasScheduleConfigColumn ? ', c.schedule_config' : '';
$imageSelect = $hasImagePathColumn ? ', c.image_path' : '';
$imageMimeSelect = $hasImageMimeColumn ? ', c.image_mime' : '';
$imageDataSelect = $hasImageDataColumn ? ', c.image_data' : '';
$categorySelect = $hasCategoryColumn ? ', c.category' : '';
$descriptionSelect = $hasDescriptionColumn ? ', c.description' : '';
$intensityLevelSelect = $hasIntensityLevelColumn ? ', c.intensity_level' : '';
$fitnessLevelSelect = $hasFitnessLevelColumn ? ', c.fitness_level' : '';

$sql = "SELECT c.id, c.slug, c.name, c.max_participants, c.trainer_account_id, c.weekly_schedule, c.active{$scheduleConfigSelect}{$imageSelect}{$imageMimeSelect}{$imageDataSelect},
               a.name AS trainer_name,
               COALESCE(stats.total_bookings, 0) AS total_bookings,
               COALESCE(stats.total_clients, 0) AS total_clients
        FROM classes_admin c
        LEFT JOIN (
            SELECT class_slug,
                   COUNT(*) AS total_bookings,
                   COALESCE(SUM(participants), 0) AS total_clients
            FROM bookings
            WHERE {$activeBookingWhere}
            GROUP BY class_slug
        ) stats ON stats.class_slug = c.slug
        LEFT JOIN accounts a ON c.trainer_account_id = a.id AND a.role = 'trainer'
        WHERE c.active = 1
        ORDER BY c.created_at DESC";

echo "Executing SQL:\n$sql\n";

$query = $conn->query($sql);
if (!$query) {
    echo "Query FAILED: " . $conn->error . "\n";
} else {
    echo "Query SUCCESS. Found " . $query->num_rows . " rows.\n";
    while ($row = $query->fetch_assoc()) {
        echo "- {$row['name']} ({$row['slug']})\n";
    }
}
