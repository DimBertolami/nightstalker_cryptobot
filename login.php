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
$success = '';
$username = '';

// Check for registration success message
if (isset($_SESSION['register_success'])) {
    $success = $_SESSION['register_success'];
    unset($_SESSION['register_success']);
}

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password";
    } else {
        if (login($username, $password)) {
            // Redirect to dashboard or coins page
            header("Location: nightstalker.php");
            exit;
        } else {
            $error = "Invalid username or password";
        }
    }
}

// Set page title before including header
$title = "Night Stalker: Built from a decommissioned early-warning tsunami Artificial Intelligence system with new mission objectives: to track, target and exploit new crypto's success or failure prediction on exchanges around the world, starting with Bitvavo and Binance";
require_once __DIR__ . '/includes/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-4">
            <div class="card shadow">
                <div class="card-body">
                    <h2 class="card-title text-center mb-4">Login</h2>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                    <?php endif; ?>
<!-- Add this right before the <form> tag in login.php -->
<div class="mb-4 text-center">
    <h6 class="text-muted mb-3">Or connect with:</h6>
    
    <!-- Phantom Wallet Button -->
    <button id="phantom-login" class="btn btn-outline-primary mb-2" style="width:100%">
        <i class="fas fa-wallet"></i> Phantom Wallet
    </button>
    
    <!-- WalletConnect/MetaMask alternative -->
    <button id="walletconnect-btn" class="btn btn-outline-secondary" style="width:100%">
        <i class="fas fa-link"></i> WalletConnect
    </button>
</div>

<!-- Add this JavaScript right before including footer.php -->
<script>
// Phantom Wallet Login
document.getElementById('phantom-login').addEventListener('click', async () => {
    if (window?.phantom?.solana?.isPhantom) {
        try {
            const response = await window.phantom.solana.connect();
            const publicKey = response.publicKey.toString();
            
            // Verify wallet on server
            const verify = await fetch('wallet-auth.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    provider: 'phantom',
                    publicKey: publicKey 
                })
            });
            
            const result = await verify.json();
            
            if (result.success) {
                window.location.href = 'coins.php';
            } else {
                alert('Wallet not registered: ' + result.message);
            }
        } catch (err) {
            console.error("Connection error:", err);
            alert("Wallet connection failed");
        }
    } else {
        alert("Phantom wallet not found! Install from https://phantom.app");
    }
});

// WalletConnect/MetaMask (basic implementation)
document.getElementById('walletconnect-btn').addEventListener('click', async () => {
    if (window.ethereum) {
        try {
            const accounts = await window.ethereum.request({ method: 'eth_requestAccounts' });
            const address = accounts[0];
            
            const verify = await fetch('wallet-auth.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    provider: 'ethereum',
                    address: address 
                })
            });
            
            const result = await verify.json();
            
            if (result.success) {
                window.location.href = 'coins.php';
            } else {
                alert('Wallet not registered: ' + result.message);
            }
        } catch (err) {
            console.error("Connection error:", err);
            alert("Connection failed: " + err.message);
        }
    } else {
        alert("Ethereum wallet not detected");
    }
});
</script>

                    
                    <form method="POST" action="login.php">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?= htmlspecialchars($username) ?>" required autofocus>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Login</button>
                    </form>
                    
                    <div class="mt-3 text-center">
                        <p class="small text-muted">
                            Don't have an account? <a href="register.php">Register here</a>
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
