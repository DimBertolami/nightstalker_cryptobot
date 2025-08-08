<?php
// Start session
session_start();

// Define BASE_URL constant if not already defined
if (!defined('BASE_URL')) {
    // Auto-detect the base URL
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $baseFolder = dirname($_SERVER['SCRIPT_NAME']);
    $baseFolder = rtrim($baseFolder, '/');
    define('BASE_URL', $protocol . $host . $baseFolder);
}

// Set admin flag for this script only
$_SESSION['is_admin'] = true;

// Include header
$pageTitle = "Generate Wallet Icons";
include __DIR__ . '/includes/header.php';
?>

<div class="container mt-5">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4>Generate Wallet Icons</h4>
                </div>
                <div class="card-body">
                    <div id="result-container">
                        <div class="alert alert-info">
                            <p>This script will generate the wallet icons required for the Night Stalker wallet connection page.</p>
                            <p>Click the button below to generate the icons.</p>
                        </div>
                        <button id="generate-btn" class="btn btn-primary" onclick="generateIcons()">Generate Wallet Icons</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Function to generate icons - can be called directly from onclick
function generateIcons() {
    // Get button and container elements
    const btn = document.getElementById('generate-btn');
    const container = document.getElementById('result-container');
    
    // Disable button and show loading
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Generating...';
    
    // Create and send AJAX request
    const xhr = new XMLHttpRequest();
    xhr.open('GET', '<?php echo BASE_URL; ?>/api/system/create-wallet-icons.php', true);
    
    xhr.onload = function() {
        if (xhr.status >= 200 && xhr.status < 300) {
            // Success - parse response
            try {
                const response = JSON.parse(xhr.responseText);
                
                // Create success message
                let html = '<div class="alert alert-success mt-3"><h5>Wallet Icons Generated Successfully!</h5>';
                
                if (response.created && response.created.length) {
                    html += '<p>The following icons were created:</p><ul>';
                    response.created.forEach(function(image) {
                        html += '<li>' + image + '</li>';
                    });
                    html += '</ul>';
                }
                
                if (response.errors && response.errors.length) {
                    html += '<p>The following errors occurred:</p><ul class="text-danger">';
                    response.errors.forEach(function(error) {
                        html += '<li>' + error + '</li>';
                    });
                    html += '</ul>';
                }
                
                html += '<p>You can now connect your wallet by clicking the Connect Wallet button in the trading dashboard.</p>';
                html += '<a href="<?php echo BASE_URL; ?>/link-wallet.php" class="btn btn-primary mt-2">Go to Connect Wallet Page</a>';
                html += '</div>';
                
                // Add to container
                const successDiv = document.createElement('div');
                successDiv.innerHTML = html;
                container.appendChild(successDiv);
                
                // Re-enable button
                btn.disabled = false;
                btn.textContent = 'Generate Wallet Icons Again';
            } catch (e) {
                // JSON parse error
                const errorDiv = document.createElement('div');
                errorDiv.className = 'alert alert-danger mt-3';
                errorDiv.textContent = 'Error parsing server response';
                container.appendChild(errorDiv);
                
                // Re-enable button
                btn.disabled = false;
                btn.textContent = 'Try Again';
            }
        } else {
            // HTTP error
            const errorDiv = document.createElement('div');
            errorDiv.className = 'alert alert-danger mt-3';
            errorDiv.textContent = 'Failed to generate wallet icons: ' + xhr.status;
            container.appendChild(errorDiv);
            
            // Re-enable button
            btn.disabled = false;
            btn.textContent = 'Try Again';
        }
    };
    
    xhr.onerror = function() {
        // Network error
        const errorDiv = document.createElement('div');
        errorDiv.className = 'alert alert-danger mt-3';
        errorDiv.textContent = 'Network error occurred while trying to generate icons';
        container.appendChild(errorDiv);
        
        // Re-enable button
        btn.disabled = false;
        btn.textContent = 'Try Again';
    };
    
    // Send the request
    xhr.send();
}

// Also set up jQuery event handler as a backup if jQuery is available
document.addEventListener('DOMContentLoaded', function() {
    if (typeof jQuery !== 'undefined') {
        jQuery('#generate-btn').on('click', function(e) {
            e.preventDefault();
            generateIcons();
        });
    }
});
</script>

<?php
// Include footer
include __DIR__ . '/includes/footer.php';

// Reset admin flag
unset($_SESSION['is_admin']);
?>
