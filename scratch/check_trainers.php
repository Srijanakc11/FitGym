<?php
require_once __DIR__ . '/php/database.php';

if (!$conn) {
    die("Connection failed");
}

$sql = "SELECT id, name, role, active, qualification_status FROM accounts WHERE role = 'trainer'";
$result = $conn->query($sql);

echo "Total Trainers found: " . ($result ? $result->num_rows : 0) . "\n\n";

if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "ID: " . $row['id'] . "\n";
        echo "Name: " . $row['name'] . "\n";
        echo "Active: " . $row['active'] . "\n";
        echo "Qualification Status: " . $row['qualification_status'] . "\n";
        echo "--------------------------\n";
    }
} else {
    echo "Error: " . $conn->error . "\n";
}
