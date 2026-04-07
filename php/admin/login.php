<?php
require_once __DIR__ . '/../auth_common.php';
fitgym_redirect(fitgym_url('/php/login.php') . '?next=' . rawurlencode(fitgym_url('/php/admin/index.php')));
