<?php
session_start();
require_once __DIR__ . '/dynamic_content.php';

$id = (int)($_POST['id'] ?? 0);
$userId = (int)($_SESSION['auth_id'] ?? $_SESSION['user_id'] ?? 0);

if ($id > 0 && $userId > 0) {
    fitgym_mark_notification_as_read($id);
    echo json_encode(['ok' => true]);
} else {
    echo json_encode(['ok' => false]);
}
