<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';

try {
    initDB();
    echo "Database initialized successfully!";
} catch (Exception $e) {
    echo "Initialization failed: " . $e->getMessage();
}
