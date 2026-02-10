<?php
session_start();
if (empty($_SESSION['user_email'])) {
    header('Location: /fitgym/php/login.php');
    exit;
}
