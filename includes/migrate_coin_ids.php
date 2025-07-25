<?php
/**
 * Migration script to normalize coin_id values by removing "COIN_" prefix
 * from cryptocurrencies.id and updating related tables accordingly.
 */

require_once 'pdo_functions.php';
require_once 'functions.php';  // Add this line to include getDBConnection if not in pdo_functions.php

function migrateCoinIds() {
    $db = getDBConnection();
    if (!$db) {
        echo "Database connection failed\n";
        return false;
    }

    try {
        // Disable foreign key checks
        $db->exec("SET FOREIGN_KEY_CHECKS=0");

        $db->beginTransaction();

        // Update cryptocurrencies table: remove "COIN_" prefix from id
        $updateCrypto = $db->prepare("UPDATE cryptocurrencies SET id = SUBSTRING(id, 6) WHERE id LIKE 'COIN_%'");
        $updateCrypto->execute();

        // Update portfolio table: remove "COIN_" prefix from coin_id
        $updatePortfolio = $db->prepare("UPDATE portfolio SET coin_id = SUBSTRING(coin_id, 6) WHERE coin_id LIKE 'COIN_%'");
        $updatePortfolio->execute();

        // Update trades table: remove "COIN_" prefix from coin_id
        $updateTrades = $db->prepare("UPDATE trades SET coin_id = SUBSTRING(coin_id, 6) WHERE coin_id LIKE 'COIN_%'");
        $updateTrades->execute();

        // Update price_history table: remove "COIN_" prefix from coin_id
        $updatePriceHistory = $db->prepare("UPDATE price_history SET coin_id = SUBSTRING(coin_id, 6) WHERE coin_id LIKE 'COIN_%'");
        $updatePriceHistory->execute();

        $db->commit();

        // Re-enable foreign key checks
        $db->exec("SET FOREIGN_KEY_CHECKS=1");

        echo "Migration completed successfully.\n";
        return true;
    } catch (Exception $e) {
        $db->rollBack();

        // Re-enable foreign key checks in case of failure
        $db->exec("SET FOREIGN_KEY_CHECKS=1");

        echo "Migration failed: " . $e->getMessage() . "\n";
        return false;
    }
}

migrateCoinIds();
