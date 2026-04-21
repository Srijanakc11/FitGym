<?php
require_once __DIR__ . '/php/auth_common.php';
global $conn;
$res = $conn->query("SELECT id, email, name, role FROM accounts WHERE name LIKE '%Joyesh%'");
print_r($res->fetch_all(MYSQLI_ASSOC));
