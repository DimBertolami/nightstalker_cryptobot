<?php
/**
 * Delete Strategy API Endpoint
 * 
 * Deletes a trading strategy from the Night Stalker cryptobot
 */

header('Content-Type: application/json');

// Enable error reporting but don't display errors to the client
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/opt/lampp/htdocs/NS/logs/php-error.log');

// Include configuration
require_once '../includes/config.php';

// Direct database connection to avoid circular dependencies
function getDBConnection() {
    static $db = null;
    
    if ($db === null) {
        try {
            $db = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            return false;
        }
    }
    
    return $db;
}

try {
    // Check if this is a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST requests are allowed');
    }
    
    // Check if ID parameter is provided
    if (!isset($_POST['id']) || empty($_POST['id'])) {
        throw new Exception('Strategy ID is required');
    }
    
    $id = $_POST['id'];
    
    // Get database connection
    $db = getDBConnection();
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Check if strategy exists
    $stmt = $db->prepare("SELECT name FROM strategies WHERE id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $strategy = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$strategy) {
        throw new Exception("Strategy with ID $id not found");
    }
    
    // Delete the strategy
    $stmt = $db->prepare("DELETE FROM strategies WHERE id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => "Strategy '{$strategy['name']}' has been deleted"
    ]);
    
} catch (Exception $e) {
    // Return error response
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
