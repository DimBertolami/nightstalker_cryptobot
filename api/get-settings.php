<?php
/**
 * Get Settings API
 * 
 * Retrieves settings from the database.
 * Can retrieve a specific setting by name or all settings.
 * 
 * @return JSON Response with setting value(s)
 */

// Set headers
header('Content-Type: application/json');

// Include required files
require_once '../includes/config.php';
require_once '../includes/database.php';

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

try {
    // Get database connection
    $db = getDBConnection();
    
    // Check if a specific setting is requested
    if (isset($_GET['setting'])) {
        $setting = $_GET['setting'];
        
        // Whitelist of allowed settings to retrieve
        $allowedSettings = ['masterFetchToggle'];
        
        if (!in_array($setting, $allowedSettings)) {
            throw new Exception("Setting '{$setting}' is not allowed to be retrieved");
        }
        
        // Prepare and execute query
        $stmt = $db->prepare("SELECT setting_name, setting_value FROM settings WHERE setting_name = :setting_name");
        $stmt->bindParam(':setting_name', $setting);
        $stmt->execute();
        
        // Get result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $response['success'] = true;
            $response['data'] = [
                'name' => $result['setting_name'],
                'value' => $result['setting_value']
            ];
        } else {
            // Setting not found, return default value based on setting name
            $defaultValue = null;
            
            if ($setting === 'masterFetchToggle') {
                $defaultValue = true; // Default to enabled (true)
            }
            
            $response['success'] = true;
            $response['data'] = [
                'name' => $setting,
                'value' => $defaultValue
            ];
        }
    } else {
        // Get all allowed settings
        $stmt = $db->prepare("
            SELECT setting_name, setting_value 
            FROM settings 
            WHERE setting_name IN ('masterFetchToggle')
        ");
        $stmt->execute();
        
        // Get results
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format response
        $settings = [];
        foreach ($results as $row) {
            $settings[$row['setting_name']] = $row['setting_value'];
        }
        
        // Add default values for missing settings
        if (!isset($settings['masterFetchToggle'])) {
            $settings['masterFetchToggle'] = true; // Default to enabled (true)
        }
        
        $response['success'] = true;
        $response['data'] = $settings;
    }
} catch (Exception $e) {
    // Log error
    error_log("Error in get-settings.php: " . $e->getMessage());
    
    // Set error response
    $response['success'] = false;
    $response['message'] = "Failed to retrieve settings: " . $e->getMessage();
}

// Return JSON response
echo json_encode($response);
exit;
