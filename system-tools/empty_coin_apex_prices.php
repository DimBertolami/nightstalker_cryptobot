<?php
require_once '../includes/config.php';
require_once '../includes/database.php';

try {
    $db = getDBConnection();
    $stmt = $db->prepare("TRUNCATE TABLE coin_apex_prices");
    if ($stmt->execute()) {
        echo "Success: The 'coin_apex_prices' table has been emptied.";
    } else {
        echo "Error: Could not empty the table.";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>