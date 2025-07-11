<?php
// Authentication functions
require_once __DIR__ . '/database.php';

/**
 * Database connection (using the main database connection function)
 */
function db_connect() {
    return getDbConnection();
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Redirect if not authenticated
 */
function requireAuth() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

/**
 * Login function
 */
function login($username, $password) {
    $db = db_connect();
    
    // Create users table if it doesn't exist
    try {
        $db->query("CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(100),
            password VARCHAR(255) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        // For first-time setup, create a default admin user if the table is empty
        $checkUsers = $db->query("SELECT COUNT(*) as count FROM users");
        $row = $checkUsers->fetch(PDO::FETCH_ASSOC);
        $userCount = $row['count'];
        
        if ($userCount == 0 && $username == 'admin') {
            $hashedPassword = password_hash('admin', PASSWORD_DEFAULT);
            $insertAdmin = $db->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
            $adminEmail = 'admin@example.com';
            $insertAdmin->execute([$username, $adminEmail, $hashedPassword]);
        }
        
        // Check if user exists
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            
            // Check if user has admin privileges
            if (isset($user['is_admin']) && $user['is_admin'] == 1) {
                $_SESSION['is_admin'] = 1;
            } else {
                $_SESSION['is_admin'] = 0;
            }
            
            return true;
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        return false;
    }
}

/**
 * Logout function
 */
function logout() {
    $_SESSION = [];
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
}
