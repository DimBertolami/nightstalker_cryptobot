<?php
// Buffer all output to prevent headers already sent errors
ob_start();

try {
    // Start session
    session_start();
    
    // Set headers
    header('Content-Type: application/json');
    
    // Define wallet icons directory
    $walletsDir = __DIR__ . '/../../assets/images/wallets';
    
    // Create directory if it doesn't exist
    if (!file_exists($walletsDir)) {
        if (!mkdir($walletsDir, 0755, true)) {
            throw new Exception("Failed to create wallets images directory");
        }
    }
    
    // Define and create fonts directory if it doesn't exist
    $fontsDir = __DIR__ . '/../../assets/fonts';
    if (!file_exists($fontsDir)) {
        if (!mkdir($fontsDir, 0755, true)) {
            error_log("Failed to create fonts directory, will use system font");
        }
    }
    
    // Define wallet icons to create
    $walletIcons = array(
        'phantom.png' => array(
            'width' => 200,
            'height' => 200,
            'text' => 'Phantom',
            'bg_color' => array(115, 92, 255), // Purple
            'icon' => '👻'
        ),
        'metamask.png' => array(
            'width' => 200,
            'height' => 200,
            'text' => 'MetaMask',
            'bg_color' => array(242, 153, 74), // Orange
            'icon' => '🦊'
        ),
        'keplr.png' => array(
            'width' => 200,
            'height' => 200,
            'text' => 'Keplr',
            'bg_color' => array(41, 128, 185), // Blue
            'icon' => '🔑'
        ),
        'glow.png' => array(
            'width' => 200,
            'height' => 200,
            'text' => 'Glow',
            'bg_color' => array(255, 215, 0), // Gold
            'icon' => '✨'
        ),
        'magiceden.png' => array(
            'width' => 200,
            'height' => 200,
            'text' => 'Magic Eden',
            'bg_color' => array(255, 105, 180), // Hot Pink
            'icon' => '🪄'
        ),
        'mathwallet.png' => array(
            'width' => 200,
            'height' => 200,
            'text' => 'MathWallet',
            'bg_color' => array(46, 204, 113), // Green
            'icon' => '��'
        ),
        'solflare.png' => array(
            'width' => 200,
            'height' => 200,
            'text' => 'Solflare',
            'bg_color' => array(255, 140, 0), // Dark Orange
            'icon' => '☀️'
        ),
        'trust.png' => array(
            'width' => 200,
            'height' => 200,
            'text' => 'Trust',
            'bg_color' => array(52, 152, 219), // Light Blue
            'icon' => '🛡️'
        ),
        'kbc.png' => array(
            'width' => 200,
            'height' => 200,
            'text' => 'KBC',
            'bg_color' => array(0, 87, 184), // KBC Blue
            'icon' => '��🇪'
        ),
        'bnp.png' => array(
            'width' => 200,
            'height' => 200,
            'text' => 'BNP Paribas',
            'bg_color' => array(0, 120, 51), // BNP Green
            'icon' => '🇧🇪'
        ),
        'belfius.png' => array(
            'width' => 200,
            'height' => 200,
            'text' => 'Belfius',
            'bg_color' => array(227, 6, 19), // Belfius Red
            'icon' => '🇧🇪'
        ),
        'revolut.png' => array(
            'width' => 200,
            'height' => 200,
            'text' => 'Revolut',
            'bg_color' => array(0, 0, 0), // Black
            'icon' => '💳'
        ),
        'multi-signature.png' => array(
            'width' => 200,
            'height' => 200,
            'text' => 'Multi-Sig',
            'bg_color' => array(75, 0, 130), // Indigo
            'icon' => '🛡'
        )
    );
    
    $created = array();
    $errors = array();
    
    // Create each wallet icon
    foreach ($walletIcons as $filename => $config) {
        $filepath = $walletsDir . '/' . $filename;
        
        // Create image even if it exists but is empty (0 bytes)
        $fileEmpty = file_exists($filepath) && filesize($filepath) === 0;
        
        if (file_exists($filepath) && !$fileEmpty) {
            $created[] = $filename . ' (already exists)';
            continue;
        }
        
        // Create image
        $image = imagecreatetruecolor($config['width'], $config['height']);
        
        // Set background color
        if (isset($config['bg_color']) && is_array($config['bg_color']) && count($config['bg_color']) >= 3) {
            $bg_color = imagecolorallocate($image, $config['bg_color'][0], $config['bg_color'][1], $config['bg_color'][2]);
            imagefill($image, 0, 0, $bg_color);
        }
        
        // Add rounded corners by creating a mask
        $mask = imagecreatetruecolor($config['width'], $config['height']);
        $transparent = imagecolorallocate($mask, 255, 0, 0);
        $black = imagecolorallocate($mask, 0, 0, 0);
        imagecolortransparent($mask, $transparent);
        imagefill($mask, 0, 0, $transparent);
        
        // Draw rounded rectangle on mask
        $radius = 30;
        imagefilledrectangle($mask, $radius, 0, $config['width'] - $radius, $config['height'], $black);
        imagefilledrectangle($mask, 0, $radius, $config['width'], $config['height'] - $radius, $black);
        imagefilledellipse($mask, $radius, $radius, $radius * 2, $radius * 2, $black);
        imagefilledellipse($mask, $config['width'] - $radius, $radius, $radius * 2, $radius * 2, $black);
        imagefilledellipse($mask, $radius, $config['height'] - $radius, $radius * 2, $radius * 2, $black);
        imagefilledellipse($mask, $config['width'] - $radius, $config['height'] - $radius, $radius * 2, $radius * 2, $black);
        
        // Apply mask to image
        imagecopymerge($image, $mask, 0, 0, 0, 0, $config['width'], $config['height'], 100);
        imagecolortransparent($image, $transparent);
        
        // Add text
        $text_color = imagecolorallocate($image, 255, 255, 255);
        $font_size = 24;
        $font_path = $fontsDir . '/OpenSans-Bold.ttf';
        
        // Use default font if custom font doesn't exist
        if (!file_exists($font_path)) {
            // Draw text with built-in font
            $text = $config['text'];
            $text_width = imagefontwidth(5) * strlen($text);
            $text_height = imagefontheight(5);
            $x = ($config['width'] - $text_width) / 2;
            $y = ($config['height'] - $text_height) / 2 + 40; // Position below icon
            imagestring($image, 5, $x, $y, $text, $text_color);
            
            // Draw icon placeholder
            $icon_text = $config['icon'];
            $icon_width = imagefontwidth(5) * strlen($icon_text);
            $icon_x = ($config['width'] - $icon_width) / 2;
            $icon_y = $y - 40; // Position above text
            imagestring($image, 5, $icon_x, $icon_y, $icon_text, $text_color);
        } else {
            // Draw text with TrueType font
            $text = $config['text'];
            $box = imagettfbbox($font_size, 0, $font_path, $text);
            $text_width = abs($box[4] - $box[0]);
            $text_height = abs($box[5] - $box[1]);
            $x = ($config['width'] - $text_width) / 2;
            $y = ($config['height'] + $text_height) / 2 + 40; // Position below icon
            imagettftext($image, $font_size, 0, $x, $y, $text_color, $font_path, $text);
            
            // Draw icon placeholder
            $icon_size = 60;
            $icon_text = $config['icon'];
            $box = imagettfbbox($icon_size, 0, $font_path, $icon_text);
            $icon_width = abs($box[4] - $box[0]);
            $icon_x = ($config['width'] - $icon_width) / 2;
            $icon_y = $y - 60; // Position above text
            imagettftext($image, $icon_size, 0, $icon_x, $icon_y, $text_color, $font_path, $icon_text);
        }
        
        // Save image
        if (!imagepng($image, $filepath)) {
            $errors[] = "Failed to save $filename";
        } else {
            $created[] = $filename;
        }
        
        // Free memory
        imagedestroy($image);
        imagedestroy($mask);
    }
    
    // Return success response
    echo json_encode(array(
        'success' => true,
        'message' => 'Wallet icons created successfully',
        'created' => $created,
        'errors' => $errors
    ));
    
} catch (Exception $e) {
    // Log error
    error_log('Create Wallet Icons Error: ' . $e->getMessage());
    
    // Return error response
    header('Content-Type: application/json');
    echo json_encode(array(
        'success' => false,
        'message' => 'Failed to create wallet icons: ' . $e->getMessage()
    ));
}

// End output buffering
ob_end_flush();
