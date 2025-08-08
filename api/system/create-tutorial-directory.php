<?php
// Set headers
header('Content-Type: application/json');

// Define tutorial image directory
$tutorialDir = __DIR__ . '/../../assets/images/tutorial';

try {
    // Create directory if it doesn't exist
    if (!file_exists($tutorialDir)) {
        // Try with 0777 permissions
        if (!@mkdir($tutorialDir, 0777, true)) {
            // If that fails, try shell command
            $cmd = "mkdir -p " . escapeshellarg($tutorialDir);
            exec($cmd, $output, $returnVar);
            
            if ($returnVar !== 0) {
                throw new Exception("Failed to create directory. Please check permissions.");
            }
        }
        
        // Set permissions
        @chmod($tutorialDir, 0777);
        
        echo json_encode([
            'success' => true,
            'message' => 'Tutorial directory created successfully',
            'path' => $tutorialDir
        ]);
    } else {
        // Directory exists, update permissions
        @chmod($tutorialDir, 0777);
        
        echo json_encode([
            'success' => true,
            'message' => 'Tutorial directory already exists, permissions updated',
            'path' => $tutorialDir
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
