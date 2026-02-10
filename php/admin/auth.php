<?php
require_once __DIR__ . '/_bootstrap.php';

if (empty($_SESSION['admin_id'])) {
    header('Location: /fitgym/php/admin/login.php');
    exit;
}
