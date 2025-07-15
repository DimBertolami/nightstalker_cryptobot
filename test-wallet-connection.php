<?php
// Buffer all output to prevent headers already sent errors
ob_start();

// Define BASE_URL constant if not already defined
if (!defined('BASE_URL')) {
    // Auto-detect the base URL
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $baseFolder = dirname($_SERVER['SCRIPT_NAME']);
    $baseFolder = rtrim($baseFolder, '/');
    define('BASE_URL', $protocol . $host . $baseFolder);
}

// Set a test session to bypass authentication
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'test_user';
$_SESSION['is_admin'] = true;

// Include header
$pageTitle = "Test Wallet Connection";
include __DIR__ . '/includes/header.php';
?>

<div class="container mt-5">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4>Wallet Connection Test</h4>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <p>This page tests the wallet connection functionality. Click the buttons below to test each component:</p>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header">
                                    <h5>1. Test Wallet Icons</h5>
                                </div>
                                <div class="card-body">
                                    <p>Generate the wallet icons required for the wallet connection page:</p>
                                    <button id="generate-icons-btn" class="btn btn-primary" onclick="generateWalletIcons()">Generate Wallet Icons</button>
                                    <div id="icons-result" class="mt-3"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header">
                                    <h5>2. Test Wallet Connection Page</h5>
                                </div>
                                <div class="card-body">
                                    <p>Test if the wallet connection page loads properly:</p>
                                    <button id="test-page-btn" class="btn btn-primary" onclick="testWalletPage()">Test Wallet Page</button>
                                    <div id="page-result" class="mt-3"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header">
                                    <h5>3. Test Wallet Authentication API</h5>
                                </div>
                                <div class="card-body">
                                    <p>Test if the wallet authentication API works:</p>
                                    <button id="test-api-btn" class="btn btn-primary" onclick="testWalletAPI()">Test Wallet API</button>
                                    <div id="api-result" class="mt-3"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header">
                                    <h5>4. Go to Wallet Connection</h5>
                                </div>
                                <div class="card-body">
                                    <p>Once all tests pass, you can go to the wallet connection page:</p>
                                    <a href="<?php echo BASE_URL; ?>/link-wallet.php" class="btn btn-success">Go to Wallet Connection</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Function to generate wallet icons
function generateWalletIcons() {
    const btn = document.getElementById('generate-icons-btn');
    const resultDiv = document.getElementById('icons-result');
    
    // Show loading state
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Generating...';
    resultDiv.innerHTML = '<div class="alert alert-info">Generating wallet icons...</div>';
    
    // Make AJAX request
    fetch('<?php echo BASE_URL; ?>/api/system/create-wallet-icons.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let html = '<div class="alert alert-success">';
                html += '<h5>Wallet Icons Generated Successfully!</h5>';
                
                if (data.created && data.created.length) {
                    html += '<p>Created icons:</p><ul>';
                    data.created.forEach(icon => {
                        html += `<li>${icon}</li>`;
                    });
                    html += '</ul>';
                } else {
                    html += '<p>No new icons were created. They may already exist.</p>';
                }
                
                html += '</div>';
                resultDiv.innerHTML = html;
            } else {
                resultDiv.innerHTML = `<div class="alert alert-danger">Error: ${data.message || 'Unknown error'}</div>`;
            }
        })
        .catch(error => {
            resultDiv.innerHTML = `<div class="alert alert-danger">Error: ${error.message}</div>`;
        })
        .finally(() => {
            // Reset button
            btn.disabled = false;
            btn.innerHTML = 'Generate Wallet Icons';
        });
}

// Function to test wallet connection page
function testWalletPage() {
    const btn = document.getElementById('test-page-btn');
    const resultDiv = document.getElementById('page-result');
    
    // Show loading state
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Testing...';
    resultDiv.innerHTML = '<div class="alert alert-info">Testing wallet connection page...</div>';
    
    // Make AJAX request to check if page loads
    fetch('<?php echo BASE_URL; ?>/link-wallet.php', { method: 'HEAD' })
        .then(response => {
            if (response.ok) {
                resultDiv.innerHTML = `
                    <div class="alert alert-success">
                        <h5>Success!</h5>
                        <p>The wallet connection page loaded successfully with status code: ${response.status}</p>
                    </div>
                `;
            } else {
                resultDiv.innerHTML = `
                    <div class="alert alert-warning">
                        <h5>Warning</h5>
                        <p>The wallet connection page returned status code: ${response.status}</p>
                        <p>This might be normal if it's redirecting to login.</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            resultDiv.innerHTML = `<div class="alert alert-danger">Error: ${error.message}</div>`;
        })
        .finally(() => {
            // Reset button
            btn.disabled = false;
            btn.innerHTML = 'Test Wallet Page';
        });
}

// Function to test wallet authentication API
function testWalletAPI() {
    const btn = document.getElementById('test-api-btn');
    const resultDiv = document.getElementById('api-result');
    
    // Show loading state
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Testing...';
    resultDiv.innerHTML = '<div class="alert alert-info">Testing wallet authentication API...</div>';
    
    // Make AJAX request with test data
    fetch('<?php echo BASE_URL; ?>/wallet-auth.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            provider: 'phantom',
            publicKey: 'TEST_WALLET_' + Date.now()
        })
    })
        .then(response => response.json())
        .then(data => {
            let html = '<div class="alert ';
            
            if (data.success) {
                html += 'alert-success"><h5>Success!</h5>';
                html += '<p>The wallet authentication API is working properly.</p>';
            } else {
                html += 'alert-warning"><h5>Warning</h5>';
                html += `<p>The API returned an error: ${data.message || 'Unknown error'}</p>`;
                html += '<p>This might be expected if you\'re not fully authenticated.</p>';
            }
            
            html += '<pre>' + JSON.stringify(data, null, 2) + '</pre></div>';
            resultDiv.innerHTML = html;
        })
        .catch(error => {
            resultDiv.innerHTML = `<div class="alert alert-danger">Error: ${error.message}</div>`;
        })
        .finally(() => {
            // Reset button
            btn.disabled = false;
            btn.innerHTML = 'Test Wallet API';
        });
}
</script>

<?php
// Include footer
include __DIR__ . '/includes/footer.php';

// End output buffering
ob_end_flush();
?>
