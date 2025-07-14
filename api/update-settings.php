<?php
/**
 * API Endpoint to Update Settings
 * 
 * This endpoint updates settings in the database.
 * Currently supports updating the masterFetchToggle setting.
 */

// Include required files
require_once '../includes/config.php';
require_once '../includes/database.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Only POST requests are accepted.'
    ]);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

// Check if data is valid
if (!$data || !isset($data['setting']) || !isset($data['value'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request. Required parameters: setting, value'
    ]);
    exit;
}

// Extract setting name and value
$setting = $data['setting'];
$value = $data['value'];

// Validate setting name (whitelist approach)
$allowedSettings = ['masterFetchToggle'];
if (!in_array($setting, $allowedSettings)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid setting name. Allowed settings: ' . implode(', ', $allowedSettings)
    ]);
    exit;
}

// Validate value based on setting type
if ($setting === 'masterFetchToggle') {
    // Convert to boolean then to string '1' or '0'
    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
}

try {
    // Get database connection
    $db = getDBConnection();
    
    // Check if setting exists
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM settings WHERE name = :name");
    $stmt->bindParam(':name', $setting);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result && $result['count'] > 0) {
        // Update existing setting
        $stmt = $db->prepare("UPDATE settings SET value = :value WHERE name = :name");
    } else {
        // Insert new setting
        $stmt = $db->prepare("INSERT INTO settings (name, value) VALUES (:name, :value)");
    }
    
    $stmt->bindParam(':name', $setting);
    $stmt->bindParam(':value', $value);
    $stmt->execute();
    
    // Log the setting change
    error_log("Setting '$setting' updated to '$value'");
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => "Setting '$setting' updated successfully",
        'data' => [
            'setting' => $setting,
            'value' => $value
        ]
    ]);
    
} catch (PDOException $e) {
    // Log error
    error_log("Error updating setting: " . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
