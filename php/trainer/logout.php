<?php
require_once __DIR__ . '/../trainer_bootstrap.php';

fitgym_clear_auth_session();
session_destroy();
fitgym_redirect('/php/login.php');
