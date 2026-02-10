<?php
require_once __DIR__ . '/_bootstrap.php';
session_destroy();
header('Location: /fitgym/php/login.php');
exit;
