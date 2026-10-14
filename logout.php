<?php
require_once __DIR__ . '/includes/helpers.php';
if (is_logged()) { log_activity('Logout dari sistem', 'auth'); }
$_SESSION = [];
session_destroy();
redirect('login.php');
