<?php
session_start();
session_unset();
session_destroy();
header('Location: /fitgym/php/login.php');
exit;
