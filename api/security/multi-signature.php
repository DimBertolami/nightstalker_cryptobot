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
        case 'create':
            // Create a new multi-signature wallet
            if (!isset($data['wallet_name']) || !isset($data['required_signatures']) || !isset($data['signers'])) {
                throw new Exception("Missing required parameters for multi-signature wallet creation");
            }
            
            $walletName = $data['wallet_name'];
            $requiredSignatures = intval($data['required_signatures']);
            $signers = $data['signers'];
            
            // Validate inputs
            if ($requiredSignatures < 2) {
                throw new Exception("Multi-signature wallets require at least 2 signatures");
            }
            
            if (count($signers) < $requiredSignatures) {
                throw new Exception("Number of signers must be at least equal to required signatures");
            }
            
            // Generate a unique wallet ID
            $walletId = 'multi_' . bin2hex(random_bytes(16));
            
            // In a real implementation, this would store the multi-signature wallet in the database
            // For simulation, we'll just return success
            $response = [
                'success' => true,
                'wallet_id' => $walletId,
                'wallet_name' => $walletName,
                'required_signatures' => $requiredSignatures,
                'signers' => $signers,
                'message' => 'Multi-signature wallet created successfully.'
            ];
            break;
            
        case 'propose_transaction':
            // Propose a new transaction that requires multiple signatures
            if (!isset($data['wallet_id']) || !isset($data['amount']) || !isset($data['recipient'])) {
                throw new Exception("Missing required parameters for transaction proposal");
            }
            
            $walletId = $data['wallet_id'];
            $amount = floatval($data['amount']);
            $recipient = $data['recipient'];
            
            // Validate inputs
            if ($amount <= 0) {
                throw new Exception("Transaction amount must be greater than zero");
            }
            
            // Generate a unique transaction ID
            $transactionId = 'tx_' . bin2hex(random_bytes(16));
            
            // In a real implementation, this would store the transaction proposal in the database
            // For simulation, we'll just return success
            $response = [
                'success' => true,
                'transaction_id' => $transactionId,
                'wallet_id' => $walletId,
                'amount' => $amount,
                'recipient' => $recipient,
                'status' => 'pending',
                'signatures' => 0,
                'message' => 'Transaction proposed successfully. Awaiting signatures.'
            ];
            break;
            
        case 'sign_transaction':
            // Sign a proposed transaction
            if (!isset($data['transaction_id']) || !isset($data['signer_id'])) {
                throw new Exception("Missing required parameters for transaction signing");
            }
            
            $transactionId = $data['transaction_id'];
            $signerId = $data['signer_id'];
            
            // In a real implementation, this would verify the signer and add their signature to the transaction
            // For simulation, we'll just return success
            $response = [
                'success' => true,
                'transaction_id' => $transactionId,
                'signer_id' => $signerId,
                'signatures' => rand(1, 2), // Simulate number of signatures collected
                'required_signatures' => 2, // Simulate required signatures
                'status' => 'pending',
                'message' => 'Transaction signed successfully. Awaiting additional signatures.'
            ];
            break;
            
        case 'execute_transaction':
            // Execute a fully signed transaction
            if (!isset($data['transaction_id'])) {
                throw new Exception("Missing required parameter: transaction_id");
            }
            
            $transactionId = $data['transaction_id'];
            
            // In a real implementation, this would verify all signatures and execute the transaction
            // For simulation, we'll just return success
            $response = [
                'success' => true,
                'transaction_id' => $transactionId,
                'status' => 'executed',
                'message' => 'Transaction executed successfully.'
            ];
            break;
            
        default:
            throw new Exception("Invalid action: $action");
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    // Log error
    error_log('Multi-Signature Error: ' . $e->getMessage());
    
    // Return error response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Operation failed: ' . $e->getMessage()
    ]);
}

// End output buffering
ob_end_flush();
?>
