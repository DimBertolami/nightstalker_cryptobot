<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/vendor/autoload.php';
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

logout();
header("Location: login.php");
exit;
