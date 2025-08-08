<?php
// delete_coins.php

// --- CONFIGURATION ---
$dbConfig = [
    'host' => 'localhost',
    'user' => 'dimi',
    'password' => '1304',
    'database' => 'NS',
    'unix_socket' => '/opt/lampp/var/mysql/mysql.sock'
];

// --- SCRIPT ---

// Turn off error reporting to prevent HTML in output
error_reporting(0);
ini_set('display_errors', 0);

// Set header to plain text for clean output
header('Content-Type: text/plain');

// Establish database connection
$mysqli = new mysqli(
    $dbConfig['host'],
    $dbConfig['user'],
    $dbConfig['password'],
    $dbConfig['database'],
    null,
    $dbConfig['unix_socket']
);

// Check for connection errors
if ($mysqli->connect_error) {
    echo "Connection failed: " . $mysqli->connect_error . "\n";
    exit(1);
}

// SQL to delete all records from the coins table
$sql = "DELETE FROM coins";

// Execute the query
if ($mysqli->query($sql) === TRUE) {
    $affected_rows = $mysqli->affected_rows;
    echo "Successfully deleted all {$affected_rows} coins from the table.\n";
} else {
    echo "Error deleting records from coins table: " . $mysqli->error . "\n";
}

// SQL to delete all records from the portfolio table
$sql_portfolio = "DELETE FROM portfolio";

// Execute the query
if ($mysqli->query($sql_portfolio) === TRUE) {
    $affected_rows_portfolio = $mysqli->affected_rows;
    echo "Successfully deleted all {$affected_rows_portfolio} records from the portfolio table.\n";
} else {
    echo "Error deleting records from portfolio table: " . $mysqli->error . "\n";
}

// Close the connection
$mysqli->close();
?>
