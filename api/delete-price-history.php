<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

header('Content-Type: application/json');

$coinId = $_POST['coin_id'] ?? null;

if (!$coinId) {
    echo json_encode(['success' => false, 'error' => 'Coin ID is required.']);
    exit();
}

try {
    $db = getDbConnection();
    $stmt = $db->prepare("DELETE FROM price_history WHERE coin_id = ?");
    $stmt->execute([$coinId]);

    echo json_encode(['success' => true, 'message' => 'Price history deleted successfully for coin ID: ' . $coinId]);

} catch (PDOException $e) {
    error_log("Database error deleting price history: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("General error deleting price history: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'An unexpected error occurred: ' . $e->getMessage()]);
}
?>