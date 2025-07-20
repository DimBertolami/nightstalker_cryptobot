<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/vendor/autoload.php';
// Initialize variables
$error = '';
$username = '';
$email = '';

// Process registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate input
    if (empty($username) || empty($password) || empty($email)) {
        $error = "Please fill in all required fields";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } else {
        // Check if username already exists
        $db = db_connect();
        
        // Create users table if it doesn't exist
        try {
            $db->query("CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) NOT NULL UNIQUE,
                email VARCHAR(100) NOT NULL,
                password VARCHAR(255) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
        } catch (Exception $e) {
            error_log("Error creating users table: " . $e->getMessage());
        }
        
        $check = $db->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        $check->bind_param("s", $username);
        $check->execute();
        $check->store_result();
        
        if ($check->num_rows > 0) {
            $error = "Username already exists. Please choose another one.";
        } else {
            // Create users table if it doesn't exist
            $db->query("CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) NOT NULL UNIQUE,
                email VARCHAR(100) NOT NULL,
                password VARCHAR(255) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
            
            // Insert new user
            $stmt = $db->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt->bind_param("sss", $username, $email, $hashed);
            
            if ($stmt->execute()) {
                // Redirect to login page with success message
                $_SESSION['register_success'] = "Registration successful! You can now log in.";
                header("Location: login.php");
                exit;
            } else {
                $error = "Registration failed: " . $db->error;
            }
        }
    }
}

// Set page title before including header
$title = "Register - Night Stalker";
require_once __DIR__ . '/includes/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow">
                <div class="card-body">
                    <h2 class="card-title text-center mb-4">Create Account</h2>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="register.php">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?= htmlspecialchars($username) ?>" required autofocus>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?= htmlspecialchars($email) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Register</button>
                    </form>
                    
                    <div class="mt-3 text-center">
                        <p class="small text-muted">
                            Already have an account? <a href="login.php">Login here</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
// Include footer
require_once __DIR__ . '/includes/footer.php';
?>
