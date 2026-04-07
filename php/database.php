<?php
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'fitgym';

$db_error = null;
mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if (!$conn || $conn->connect_error) {
    $db_error = 'Database connection failed. Check credentials and ensure the database exists.';
    $conn = null;
} else {
    $conn->set_charset('utf8mb4');
}
