<?php
// CryptoTradingBot config.php
// Loads secrets and environment config from .env file in project root

// --- .env Loader (simple, no dependencies) ---
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (!array_key_exists($name, $_ENV)) {
            putenv("{$name}={$value}");
            $_ENV[$name] = $value;
        }
    }
}

// Define required constants from .env or fail with clear error
$required_env = ['DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME', 'BASE_URL'];
foreach ($required_env as $var) {
    if (isset($_ENV[$var]) && $_ENV[$var] !== '') {
        define($var, $_ENV[$var]);
    } else {
        die("FATAL: Required environment variable '$var' is missing or empty. Please check your .env file.");
    }
}

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
