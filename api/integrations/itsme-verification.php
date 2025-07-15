<?php
// Buffer all output to prevent headers already sent errors
ob_start();

try {
    // Start session
    session_start();
    
    // Set headers
    header('Content-Type: application/json');
    
    // Include configuration
    require_once '../../includes/config.php';
    
    // Get request data
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['action'])) {
        throw new Exception("Missing required parameter: action");
    }
    
    $action = $data['action'];
    $response = [];
    
    switch ($action) {
        case 'init':
            // Initialize itsme verification process
            // In a real implementation, this would connect to the itsme API
            $verificationId = 'itsme_' . bin2hex(random_bytes(16));
            $qrCode = 'https://api.itsme.be/qr/' . $verificationId; // Simulated QR code URL
            
            // Store verification ID in session
            $_SESSION['itsme_verification_id'] = $verificationId;
            
            $response = [
                'success' => true,
                'verification_id' => $verificationId,
                'qr_code' => $qrCode,
                'message' => 'Verification initiated. Scan the QR code with your itsme app.'
            ];
            break;
            
        case 'check_status':
            // Check verification status
            // In a real implementation, this would check the status with the itsme API
            if (!isset($data['verification_id'])) {
                throw new Exception("Missing required parameter: verification_id");
            }
            
            $verificationId = $data['verification_id'];
            
            // Simulate verification status check
            // In a real implementation, this would check with the itsme API
            if (isset($_SESSION['itsme_verification_id']) && $_SESSION['itsme_verification_id'] === $verificationId) {
                // Simulate a successful verification after a random delay
                $status = (time() % 10 > 7) ? 'pending' : 'verified';
                
                if ($status === 'verified') {
                    // Simulate user identity data
                    $userData = [
                        'first_name' => 'John',
                        'last_name' => 'Doe',
                        'national_number' => '12345678901',
                        'verified' => true
                    ];
                    
                    // Store verified user data in session
                    $_SESSION['itsme_verified_user'] = $userData;
                    
                    $response = [
                        'success' => true,
                        'status' => $status,
                        'user_data' => $userData,
                        'message' => 'Identity verified successfully.'
                    ];
                } else {
                    $response = [
                        'success' => true,
                        'status' => $status,
                        'message' => 'Verification is still pending. Please complete the process in your itsme app.'
                    ];
                }
            } else {
                $response = [
                    'success' => false,
                    'status' => 'invalid',
                    'message' => 'Invalid verification ID.'
                ];
            }
            break;
            
        case 'complete':
            // Complete the verification process and link to wallet/bank
            if (!isset($data['provider']) || !isset($data['account_id'])) {
                throw new Exception("Missing required parameters: provider or account_id");
            }
            
            $provider = $data['provider'];
            $accountId = $data['account_id'];
            
            // Check if user is verified with itsme
            if (!isset($_SESSION['itsme_verified_user']) || !$_SESSION['itsme_verified_user']['verified']) {
                throw new Exception("User identity not verified with itsme.");
            }
            
            // In a real implementation, this would link the verified identity to the bank account
            // For simulation, we'll just return success
            $response = [
                'success' => true,
                'provider' => $provider,
                'account_id' => $accountId,
                'message' => 'Bank account successfully linked with verified identity.'
            ];
            break;
            
        default:
            throw new Exception("Invalid action: $action");
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    // Log error
    error_log('itsme Verification Error: ' . $e->getMessage());
    
    // Return error response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Verification failed: ' . $e->getMessage()
    ]);
}

// End output buffering
ob_end_flush();
?>
