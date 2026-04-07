<?php
require_once __DIR__ . '/auth_common.php';

fitgym_clear_auth_session();
session_destroy();
fitgym_redirect('/php/login.php');
