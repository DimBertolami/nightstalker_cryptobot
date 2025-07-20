<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/functions.php';

try {
    $db = getDBConnection();
    if (!$db) {
        throw new Exception("Database connection failed");
    }

    $stmt = $db->query("SELECT * FROM trade_log ORDER BY id DESC LIMIT 20");
    $trades = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'count' => count($trades),
        'trades' => $trades
    ]);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
