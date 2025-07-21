<?php
/**
 * Purge Coins Table Script
 * 
 * This script truncates the coins table to start fresh before fetching new data
 * from Bitvavo or other sources.
 */

// Include database connection
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

// Connect to database
$db = getDBConnection();

if (!$db) {
    die("Database connection failed. Please check your configuration.");
}

try {
    // Begin transaction
    $db->beginTransaction();
    
    // Option 1: Truncate the table (faster, resets auto-increment)
    $result = $db->exec("TRUNCATE TABLE coins");
    
    // Option 2: Alternative if TRUNCATE permissions are restricted
    // $result = $db->exec("DELETE FROM coins");
    
    // Commit transaction
    $db->commit();
    
    echo "Success: Coins table has been purged successfully.\n";
    echo "The table is now empty and ready for fresh data.\n";
    
} catch (PDOException $e) {
    // Rollback transaction on error
    $db->rollBack();
    
    echo "Error: Failed to purge coins table: " . $e->getMessage() . "\n";
    exit(1);
}
?>
