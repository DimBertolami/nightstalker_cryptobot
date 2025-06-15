<?php
// Authentication functions
require_once __DIR__ . '/database.php';

/**
 * Database connection (using the main database connection function)
 */
function db_connect() {
    return getDBConnection();
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
        $userCount = $checkUsers->fetch_assoc()['count'];
        
        if ($userCount == 0 && $username == 'admin') {
            $hashedPassword = password_hash('admin', PASSWORD_DEFAULT);
            $insertAdmin = $db->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
            $adminEmail = 'admin@example.com';
            $insertAdmin->bind_param("sss", $username, $adminEmail, $hashedPassword);
            $insertAdmin->execute();
        }
    } catch (Exception $e) {
        error_log("Error creating users table: " . $e->getMessage());
    }
    
    // Proceed with login
    try {
        $stmt = $db->prepare("SELECT id, username, password FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                return true;
            }
        }
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
    }
    
    return false;
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
