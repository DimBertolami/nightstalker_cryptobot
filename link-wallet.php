<?php
require_once __DIR__ . '/includes/auth.php';
requireAuth();

$title = "Link Crypto Wallet";
require_once __DIR__ . '/includes/header.php';
?>

<div class="container mt-5">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4><i class="fas fa-wallet me-2"></i>Connect Your Crypto Wallet</h4>
                </div>
                <div class="card-body">
                    <!-- Beginner-friendly explanation -->
                    <div class="alert alert-info mb-4">
                        <h5><i class="fas fa-info-circle me-2"></i>What is a wallet connection?</h5>
                        <p>Connecting your wallet allows Night Stalker to:</p>
                        <ul>
                            <li>View your crypto balances (but not your private keys)</li>
                            <li>Execute trades on your behalf (only after your confirmation)</li>
                            <li>Track your portfolio performance over time</li>
                        </ul>
                        <p class="mb-0"><strong>Safety Note:</strong> We never store your private keys or seed phrases. You'll always need to confirm transactions in your wallet app.</p>
                    </div>
                    
                    <!-- Wallet connection options -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <img src="/NS/assets/images/wallets/phantom.png" alt="Phantom" class="mb-3" style="height: 60px;">
                                    <h5>Phantom Wallet</h5>
                                    <p class="text-muted">Connect your Solana assets</p>
                                    <button id="link-phantom" class="btn btn-primary">
                                        <i class="fas fa-plug me-2"></i>Connect Phantom
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <img src="/NS/assets/images/wallets/metamask.png" alt="MetaMask" class="mb-3" style="height: 60px;">
                                    <h5>MetaMask</h5>
                                    <p class="text-muted">Connect your Ethereum assets</p>
                                    <button id="link-metamask" class="btn btn-secondary">
                                        <i class="fas fa-plug me-2"></i>Connect MetaMask
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Status messages -->
                    <div id="wallet-status" class="alert alert-info d-none"></div>
                    
                    <!-- Safety tips -->
                    <div class="mt-4">
                        <h5><i class="fas fa-shield-alt me-2"></i>Safety Tips</h5>
                        <ul class="list-group">
                            <li class="list-group-item list-group-item-light">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                Always verify the website URL before connecting your wallet
                            </li>
                            <li class="list-group-item list-group-item-light">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                Never share your seed phrase or private keys with anyone
                            </li>
                            <li class="list-group-item list-group-item-light">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                Start with small amounts until you're comfortable with the platform
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Phantom Wallet Connection
document.getElementById('link-phantom').addEventListener('click', async () => {
    if (window?.phantom?.solana?.isPhantom) {
        try {
            const statusEl = document.getElementById('wallet-status');
            statusEl.classList.remove('d-none', 'alert-success', 'alert-danger');
            statusEl.classList.add('alert-info');
            statusEl.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Connecting to Phantom Wallet...';
            
            const response = await window.phantom.solana.connect();
            const publicKey = response.publicKey.toString();
            
            statusEl.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Linking wallet to your account...';
            
            const link = await fetch('wallet-auth.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    provider: 'phantom',
                    publicKey: publicKey
                })
            });
            
            const result = await link.json();
            
            if (result.success) {
                statusEl.classList.remove('alert-info');
                statusEl.classList.add('alert-success');
                statusEl.innerHTML = '<i class="fas fa-check-circle me-2"></i>' + 
                    'Wallet linked successfully! <a href="/NS/dashboard/trading_dashboard.php" class="alert-link">Go to Trading Dashboard</a>';
            } else {
                statusEl.classList.remove('alert-info');
                statusEl.classList.add('alert-danger');
                statusEl.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>' + 
                    (result.message || 'Linking failed');
            }
        } catch (err) {
            console.error(err);
            const statusEl = document.getElementById('wallet-status');
            statusEl.classList.remove('d-none', 'alert-info');
            statusEl.classList.add('alert-danger');
            statusEl.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>' + 
                'Connection failed: ' + (err.message || 'Unknown error');
        }
    } else {
        const statusEl = document.getElementById('wallet-status');
        statusEl.classList.remove('d-none', 'alert-info');
        statusEl.classList.add('alert-warning');
        statusEl.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>' + 
            'Phantom Wallet not detected. <a href="https://phantom.app/" target="_blank" class="alert-link">Install Phantom Wallet</a> first.';
    }
});

// MetaMask Connection (placeholder for future implementation)
document.getElementById('link-metamask').addEventListener('click', function() {
    const statusEl = document.getElementById('wallet-status');
    statusEl.classList.remove('d-none');
    statusEl.classList.add('alert-info');
    statusEl.innerHTML = '<i class="fas fa-info-circle me-2"></i>MetaMask integration coming soon!';
});
</script>

<?php 
    require_once __DIR__ . '/includes/footer.php'; 
    // If we encounter an error after starting to render the page
    // Output a minimal error message
    if (!headers_sent()) {
        header('Content-Type: text/html');
    }
    echo '<div class="container mt-5">';
    echo '<div class="alert alert-danger">';
    echo '<h4><i class="fas fa-exclamation-triangle me-2"></i>Error</h4>';
    echo '<p>We encountered an error while loading this page. Please try again later.</p>';
    echo '<p><a href="/NS/dashboard/trading_dashboard.php" class="btn btn-primary">Return to Dashboard</a></p>';
    echo '</div></div>';
    
    // Log the error
    error_log("Error in link-wallet.php: " . $e->getMessage());
    

// End output buffering
ob_end_flush();
?>
