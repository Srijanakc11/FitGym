<?php
require_once __DIR__ . '/auth_common.php';

function trainer_esc($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
