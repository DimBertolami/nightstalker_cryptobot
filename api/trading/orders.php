<?php
// Buffer all output to prevent headers already sent errors
//ob_start();

try {
    // Start session and check authentication
    session_start();
    require_once __DIR__ . '/../../includes/auth.php';
    requireAuth();
    
    // Set headers
    header('Content-Type: application/json');
    
    // Connect to database
    require_once __DIR__ . '/../../includes/database.php';
    $db = getDbConnection();
    
    // Get request method
    $method = $_SERVER['REQUEST_METHOD'];
    
    // Handle different request methods
    switch ($method) {
        case 'GET':
            // Get user ID from session
            $userId = $_SESSION['user_id'] ?? null;
            if (!$userId) {
                throw new Exception("User not authenticated");
            }
            
            // Get query parameters
            $symbol = $_GET['symbol'] ?? null;
            $status = $_GET['status'] ?? null;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
            
            // Build query
            $query = "SELECT * FROM orders WHERE user_id = :user_id";
            $params = [':user_id' => $userId];
            
            if ($symbol) {
                $query .= " AND symbol = :symbol";
                $params[':symbol'] = $symbol;
            }
            
            if ($status) {
                $query .= " AND status = :status";
                $params[':status'] = $status;
            }
            
            $query .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
            $params[':limit'] = $limit;
            $params[':offset'] = $offset;
            
            // Execute query
            $stmt = $db->prepare($query);
            foreach ($params as $key => $value) {
                if ($key === ':limit' || $key === ':offset') {
                    $stmt->bindValue($key, $value, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($key, $value);
                }
            }
            $stmt->execute();
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Return orders
            echo json_encode([
                'success' => true,
                'orders' => $orders
            ]);
            break;
            
        case 'POST':
            // Get user ID from session
            $userId = $_SESSION['user_id'] ?? null;
            if (!$userId) {
                throw new Exception("User not authenticated");
            }
            
            // Get request body
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                throw new Exception("Invalid request data");
            }
            
            // Validate required fields
            $requiredFields = ['symbol', 'type', 'side', 'quantity', 'price'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    throw new Exception("Missing required field: $field");
                }
            }
            
            // Extract order data
            $symbol = $data['symbol'];
            $type = $data['type'];
            $side = $data['side'];
            $quantity = (float)$data['quantity'];
            $price = (float)$data['price'];
            
            // Validate order data
            if ($quantity <= 0) {
                throw new Exception("Quantity must be greater than 0");
            }
            
            if ($price <= 0 && $type !== 'market') {
                throw new Exception("Price must be greater than 0 for limit orders");
            }
            
            // Insert order into database
            $stmt = $db->prepare("
                INSERT INTO orders (user_id, symbol, type, side, quantity, price, status, created_at)
                VALUES (:user_id, :symbol, :type, :side, :quantity, :price, 'pending', NOW())
            ");
            $stmt->execute([
                ':user_id' => $userId,
                ':symbol' => $symbol,
                ':type' => $type,
                ':side' => $side,
                ':quantity' => $quantity,
                ':price' => $price
            ]);
            
            $orderId = $db->lastInsertId();
            
            // Return success response
            echo json_encode([
                'success' => true,
                'message' => 'Order created successfully',
                'order_id' => $orderId
            ]);
            break;
            
        case 'PUT':
            // Get user ID from session
            $userId = $_SESSION['user_id'] ?? null;
            if (!$userId) {
                throw new Exception("User not authenticated");
            }
            
            // Get order ID from URL
            $orderId = $_GET['id'] ?? null;
            if (!$orderId) {
                throw new Exception("Order ID is required");
            }
            
            // Get request body
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                throw new Exception("Invalid request data");
            }
            
            // Validate order ownership
            $stmt = $db->prepare("SELECT * FROM orders WHERE id = :id AND user_id = :user_id");
            $stmt->execute([':id' => $orderId, ':user_id' => $userId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$order) {
                throw new Exception("Order not found or not owned by user");
            }
            
            // Update order status
            if (isset($data['status'])) {
                $allowedStatuses = ['pending', 'filled', 'cancelled', 'rejected'];
                if (!in_array($data['status'], $allowedStatuses)) {
                    throw new Exception("Invalid order status");
                }
                
                $stmt = $db->prepare("UPDATE orders SET status = :status WHERE id = :id");
                $stmt->execute([':status' => $data['status'], ':id' => $orderId]);
            }
            
            // Return success response
            echo json_encode([
                'success' => true,
                'message' => 'Order updated successfully'
            ]);
            break;
            
        case 'DELETE':
            // Get user ID from session
            $userId = $_SESSION['user_id'] ?? null;
            if (!$userId) {
                throw new Exception("User not authenticated");
            }
            
            // Get order ID from URL
            $orderId = $_GET['id'] ?? null;
            if (!$orderId) {
                throw new Exception("Order ID is required");
            }
            
            // Validate order ownership
            $stmt = $db->prepare("SELECT * FROM orders WHERE id = :id AND user_id = :user_id");
            $stmt->execute([':id' => $orderId, ':user_id' => $userId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$order) {
                throw new Exception("Order not found or not owned by user");
            }
            
            // Cancel order
            $stmt = $db->prepare("UPDATE orders SET status = 'cancelled' WHERE id = :id");
            $stmt->execute([':id' => $orderId]);
            
            // Return success response
            echo json_encode([
                'success' => true,
                'message' => 'Order cancelled successfully'
            ]);
            break;
            
        default:
            throw new Exception("Method not allowed");
    }
    
} catch (Exception $e) {
    // Log error
    error_log('Orders API Error: ' . $e->getMessage());
    
    // Return error response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// End output buffering
ob_end_flush();
