<?php
// Buffer all output to prevent headers already sent errors
ob_start();

try {
    // Start session
    session_start();
    
    // Bypass authentication completely for this utility script
    // We don't need database access to generate tutorial images
    
    // Set headers
    header('Content-Type: application/json');
    
    // Define tutorial image directory
    $tutorialDir = __DIR__ . '/../../assets/images/tutorial';
    
    // Create directory if it doesn't exist
    if (!file_exists($tutorialDir)) {
        if (!mkdir($tutorialDir, 0755, true)) {
            throw new Exception("Failed to create tutorial images directory");
        }
    }
    
    // Define and create fonts directory if it doesn't exist
    $fontsDir = __DIR__ . '/../../assets/fonts';
    if (!file_exists($fontsDir)) {
        if (!mkdir($fontsDir, 0755, true)) {
            error_log("Failed to create fonts directory, will use system font");
        }
    }
    
    // Define tutorial images to create
    $tutorialImages = [
        'wallet-connect.png' => [
            'width' => 800,
            'height' => 500,
            'text' => 'Connect Your Wallet',
            'bg_color' => [41, 128, 185] // Blue
        ],
        'portfolio.png' => [
            'width' => 800,
            'height' => 500,
            'text' => 'Track Your Portfolio',
            'bg_color' => [39, 174, 96] // Green
        ],
        'trading.png' => [
            'width' => 800,
            'height' => 500,
            'text' => 'Trade Cryptocurrencies',
            'bg_color' => [142, 68, 173] // Purple
        ],
        'safety.png' => [
            'width' => 800,
            'height' => 500,
            'text' => 'Safety First',
            'bg_color' => [231, 76, 60] // Red
        ]
    ];
    
    $created = [];
    $errors = [];
    
    // Create each tutorial image
    foreach ($tutorialImages as $filename => $config) {
        $filepath = $tutorialDir . '/' . $filename;
        
        // Skip if file already exists
        if (file_exists($filepath)) {
            $created[] = $filename . ' (already exists)';
            continue;
        }
        
        // Create image
        $image = imagecreatetruecolor($config['width'], $config['height']);
        
        // Set background color
        $bg_color = imagecolorallocate($image, $config['bg_color'][0], $config['bg_color'][1], $config['bg_color'][2]);
        imagefill($image, 0, 0, $bg_color);
        
        // Add text
        $text_color = imagecolorallocate($image, 255, 255, 255);
        $font_size = 30;
        $font_path = $fontsDir . '/OpenSans-Bold.ttf';
        
        // Use default font if custom font doesn't exist
        if (!file_exists($font_path)) {
            // Draw text with built-in font
            $text = $config['text'];
            $text_width = imagefontwidth(5) * strlen($text);
            $text_height = imagefontheight(5);
            $x = ($config['width'] - $text_width) / 2;
            $y = ($config['height'] - $text_height) / 2;
            imagestring($image, 5, $x, $y, $text, $text_color);
        } else {
            // Draw text with TrueType font
            $text = $config['text'];
            $box = imagettfbbox($font_size, 0, $font_path, $text);
            $text_width = abs($box[4] - $box[0]);
            $text_height = abs($box[5] - $box[1]);
            $x = ($config['width'] - $text_width) / 2;
            $y = ($config['height'] + $text_height) / 2;
            imagettftext($image, $font_size, 0, $x, $y, $text_color, $font_path, $text);
        }
        
        // Add Night Stalker logo text at the bottom
        $logo_text = "Night Stalker Trading";
        if (!file_exists($font_path)) {
            $logo_width = imagefontwidth(3) * strlen($logo_text);
            $logo_x = ($config['width'] - $logo_width) / 2;
            $logo_y = $config['height'] - 30;
            imagestring($image, 3, $logo_x, $logo_y, $logo_text, $text_color);
        } else {
            $logo_size = 16;
            $box = imagettfbbox($logo_size, 0, $font_path, $logo_text);
            $logo_width = abs($box[4] - $box[0]);
            $logo_x = ($config['width'] - $logo_width) / 2;
            $logo_y = $config['height'] - 20;
            imagettftext($image, $logo_size, 0, $logo_x, $logo_y, $text_color, $font_path, $logo_text);
        }
        
        // Save image
        if (!imagepng($image, $filepath)) {
            $errors[] = "Failed to save $filename";
        } else {
            $created[] = $filename;
        }
        
        // Free memory
        imagedestroy($image);
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Tutorial images created successfully',
        'created' => $created,
        'errors' => $errors
    ]);
    
} catch (Exception $e) {
    // Log error
    error_log('Create Tutorial Images Error: ' . $e->getMessage());
    
    // Return error response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Failed to create tutorial images: ' . $e->getMessage()
    ]);
}

// End output buffering
ob_end_flush();
