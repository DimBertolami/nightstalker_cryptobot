<?php
/**
 * Save Strategy API Endpoint
 * 
 * Creates or updates a trading strategy for the Night Stalker cryptobot
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

// Function to validate request parameters
function validateRequest($requiredParams) {
    foreach ($requiredParams as $param) {
        if (!isset($_POST[$param]) || empty($_POST[$param])) {
            throw new Exception("Missing required parameter: $param");
        }
    }
}

try {
    // Check if this is a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST requests are allowed');
    }
    
    // Validate required parameters
    validateRequest(['name', 'type', 'config']);
    
    // Get parameters
    $id = isset($_POST['id']) ? $_POST['id'] : null;
    $name = $_POST['name'];
    $description = isset($_POST['description']) ? $_POST['description'] : '';
    $type = $_POST['type'];
    $config = $_POST['config'];
    $isActive = isset($_POST['is_active']) ? (bool)$_POST['is_active'] : true;
    
    // Validate strategy type
    $validTypes = ['volume_spike', 'trending_coins'];
    if (!in_array($type, $validTypes)) {
        throw new Exception("Invalid strategy type: $type");
    }
    
    // Validate config is valid JSON
    if (!json_decode($config)) {
        throw new Exception('Invalid JSON configuration');
    }
    
    // Get database connection
    $db = getDBConnection();
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Check if strategies table exists, create if not
    $stmt = $db->prepare("SHOW TABLES LIKE 'strategies'");
    $stmt->execute();
    $hasStrategiesTable = $stmt->rowCount() > 0;
    
    if (!$hasStrategiesTable) {
        // Create strategies table
        $db->exec("CREATE TABLE strategies (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) NOT NULL UNIQUE,
            description TEXT,
            type VARCHAR(50) NOT NULL,
            config JSON NOT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            last_run TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
    }
    
    // Check if strategy with same name already exists (for new strategies)
    if (!$id) {
        $stmt = $db->prepare("SELECT id FROM strategies WHERE name = :name");
        $stmt->bindParam(':name', $name);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            throw new Exception("A strategy with the name '$name' already exists");
        }
    }
    
    if ($id) {
        // Update existing strategy
        $stmt = $db->prepare("UPDATE strategies SET 
            name = :name,
            description = :description,
            type = :type,
            config = :config,
            is_active = :is_active,
            updated_at = CURRENT_TIMESTAMP
            WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':config', $config);
        $stmt->bindParam(':is_active', $isActive, PDO::PARAM_BOOL);
        $stmt->execute();
        
        $message = "Strategy '$name' updated successfully";
    } else {
        // Create new strategy
        $stmt = $db->prepare("INSERT INTO strategies (name, description, type, config, is_active) 
            VALUES (:name, :description, :type, :config, :is_active)");
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':config', $config);
        $stmt->bindParam(':is_active', $isActive, PDO::PARAM_BOOL);
        $stmt->execute();
        
        $id = $db->lastInsertId();
        $message = "Strategy '$name' created successfully";
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => $message,
        'id' => $id
    ]);
    
} catch (Exception $e) {
    // Return error response
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
