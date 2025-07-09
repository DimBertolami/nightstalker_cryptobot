<?php
// Test script to debug getUserCoinBalancePDO function

// Include required files
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/pdo_functions.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get coin ID from query parameter or use default
$coinId = isset($_GET['coinId']) ? $_GET['coinId'] : '995';
echo "Testing getUserCoinBalancePDO with coin ID: $coinId\n";

// Get the database connection
$db = getDBConnection();
if (!$db) {
    die("Database connection failed\n");
}

// Directly query the database to see what's in the portfolio table
echo "Direct database query for coin ID $coinId:\n";
$stmt = $db->prepare("SELECT * FROM portfolio WHERE coin_id = :coinId");
$stmt->bindValue(':coinId', $coinId, PDO::PARAM_STR);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Direct query result: " . json_encode($result, JSON_PRETTY_PRINT) . "\n\n";

// Test the getUserCoinBalancePDO function
echo "Testing getUserCoinBalancePDO function:\n";
$portfolioData = getUserCoinBalancePDO($coinId);
echo "Function result: " . json_encode($portfolioData, JSON_PRETTY_PRINT) . "\n";

// Show all portfolio entries
echo "\nAll portfolio entries:\n";
$allStmt = $db->query("SELECT * FROM portfolio");
$allResults = $allStmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($allResults, JSON_PRETTY_PRINT) . "\n";
