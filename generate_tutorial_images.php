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
$pageTitle = "Generate Tutorial Images";
include __DIR__ . '/includes/header.php';
?>

<div class="container mt-5">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4>Generate Tutorial Images</h4>
                </div>
                <div class="card-body">
                    <div id="result-container">
                        <div class="alert alert-info">
                            <p>This script will generate the tutorial images required for the Night Stalker trading dashboard.</p>
                            <p>Click the button below to generate the images.</p>
                        </div>
                        <button id="generate-btn" class="btn btn-primary" onclick="generateImages()">Generate Tutorial Images</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Function to generate images - can be called directly from onclick
function generateImages() {
    // Get button and container elements
    const btn = document.getElementById('generate-btn');
    const container = document.getElementById('result-container');
    
    // Disable button and show loading
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Generating...';
    
    // Create and send AJAX request
    const xhr = new XMLHttpRequest();
    xhr.open('GET', '<?php echo BASE_URL; ?>/api/system/create-tutorial-images.php', true);
    
    xhr.onload = function() {
        if (xhr.status >= 200 && xhr.status < 300) {
            // Success - parse response
            try {
                const response = JSON.parse(xhr.responseText);
                
                // Create success message
                let html = '<div class="alert alert-success mt-3"><h5>Images Generated Successfully!</h5>';
                
                if (response.created && response.created.length) {
                    html += '<p>The following images were created:</p><ul>';
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
                
                html += '<p>You can now view the tutorial by clicking the tutorial button in the trading dashboard.</p>';
                html += '<a href="<?php echo BASE_URL; ?>/dashboard/trading_dashboard.php" class="btn btn-primary mt-2">Go to Trading Dashboard</a>';
                html += '</div>';
                
                // Add to container
                const successDiv = document.createElement('div');
                successDiv.innerHTML = html;
                container.appendChild(successDiv);
                
                // Re-enable button
                btn.disabled = false;
                btn.textContent = 'Generate Tutorial Images Again';
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
            errorDiv.textContent = 'Failed to generate tutorial images: ' + xhr.status;
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
        errorDiv.textContent = 'Network error occurred while trying to generate images';
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
            generateImages();
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
