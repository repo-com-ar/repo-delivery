<?php
require_once __DIR__ . '/lib/auth_check.php';
clearAuthCookie();
header('Location: login.php');
exit;
