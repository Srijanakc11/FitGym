<?php
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'fitgym';

$db_error = null;
$conn = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if ($conn->connect_error) {
    $db_error = 'Database connection failed. Check credentials and ensure the database exists.';
} else {
    $conn->set_charset('utf8mb4');
}
