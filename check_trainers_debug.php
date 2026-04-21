<?php
require_once __DIR__ . '/php/database.php';
header('Content-Type: text/plain');

if (!$conn) {
    die("Connection failed");
}

$sql = "SELECT id, name, role, active, qualification_status FROM accounts WHERE role = 'trainer'";
$result = $conn->query($sql);

echo "Total Trainers found: " . ($result ? $result->num_rows : 0) . "\n\n";

if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "ID: " . $row['id'] . " | Name: " . $row['name'] . " | Active: " . $row['active'] . " | Status: " . $row['qualification_status'] . "\n";
    }
} else {
    echo "Error: " . $conn->error . "\n";
}
