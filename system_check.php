<?php
require_once __DIR__ . '/../includes/config.php';

echo "System Check\n";
echo "===========\n";

// 1. Check DB
try {
    require_once __DIR__ . '/../includes/database.php';
    $db = connectDB();
    echo "[✓] Database connection successful\n";
    closeDB($db);
} catch (Exception $e) {
    echo "[✗] Database failed: " . $e->getMessage() . "\n";
}

// 2. Check API
try {
    require_once __DIR__ . '/../includes/functions.php';
    $data = fetchFromCMC();
    echo "[✓] API connection successful (" . count($data) . " coins)\n";
} catch (Exception $e) {
    echo "[✗] API failed: " . $e->getMessage() . "\n";
}

echo "Check complete\n";
