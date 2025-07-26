<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

try {
    $db = getDbConnection();
    $stmt = $db->query("SELECT id, coin_name FROM coins ORDER BY coin_name ASC");
    $coins = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formattedCoins = [];
    foreach ($coins as $coin) {
        $formattedCoins[] = [
            'id' => $coin['id'],
            'name' => $coin['coin_name']
        ];
    }

    echo json_encode($formattedCoins);

} catch (PDOException $e) {
    error_log("Error fetching coin list: " . $e->getMessage());
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("General error fetching coin list: " . $e->getMessage());
    echo json_encode(['error' => 'An unexpected error occurred: ' . $e->getMessage()]);
}
?>