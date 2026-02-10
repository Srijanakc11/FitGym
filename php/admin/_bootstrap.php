<?php
session_start();
require_once __DIR__ . '/../database.php';

function esc($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if (!isset($db_error)) {
    $db_error = null;
}
