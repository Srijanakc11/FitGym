<?php
require_once __DIR__ . '/_bootstrap.php';
session_destroy();
fitgym_clear_auth_session();
fitgym_redirect('/php/login.php');
