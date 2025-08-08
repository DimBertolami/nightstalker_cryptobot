<?php
require_once __DIR__ . '/../includes/database.php';

header('Content-Type: application/json');

try {
    $db = getDbConnection();
    $stmt = $db->query("SELECT id, name FROM cryptocurrencies ORDER BY name ASC");
    $coins = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($coins);

} catch (PDOException $e) {
    error_log("Error fetching coin list: " . $e->getMessage());
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("General error fetching coin list: " . $e->getMessage());
    echo json_encode(['error' => 'An unexpected error occurred: ' . $e->getMessage()]);
}
?>