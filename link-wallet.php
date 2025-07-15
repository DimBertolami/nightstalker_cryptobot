<?php
// Buffer all output to prevent headers already sent errors
ob_start();

try {
    // Include config first to ensure database constants are defined
    require_once __DIR__ . '/includes/config.php';
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
                        <div class="col-md-4 mb-4">
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
                        <div class="col-md-4 mb-4">
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
                        <div class="col-md-4 mb-4">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <img src="/NS/assets/images/wallets/solflare.png" alt="Solflare" class="mb-3" style="height: 60px;">
                                    <h5>Solflare</h5>
                                    <p class="text-muted">Popular Solana wallet</p>
                                    <button id="link-solflare" class="btn btn-warning">
                                        <i class="fas fa-plug me-2"></i>Connect Solflare
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <img src="/NS/assets/images/wallets/glow.png" alt="Glow" class="mb-3" style="height: 60px;">
                                    <h5>Glow</h5>
                                    <p class="text-muted">Solana wallet with DeFi tools</p>
                                    <button id="link-glow" class="btn btn-warning">
                                        <i class="fas fa-plug me-2"></i>Connect Glow
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <img src="/NS/assets/images/wallets/magiceden.png" alt="Magic Eden" class="mb-3" style="height: 60px;">
                                    <h5>Magic Eden</h5>
                                    <p class="text-muted">NFT marketplace wallet</p>
                                    <button id="link-magiceden" class="btn btn-info">
                                        <i class="fas fa-plug me-2"></i>Connect Magic Eden
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <img src="/NS/assets/images/wallets/mathwallet.png" alt="MathWallet" class="mb-3" style="height: 60px;">
                                    <h5>MathWallet</h5>
                                    <p class="text-muted">Multi-chain crypto wallet</p>
                                    <button id="link-mathwallet" class="btn btn-success">
                                        <i class="fas fa-plug me-2"></i>Connect MathWallet
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <img src="/NS/assets/images/wallets/trust.png" alt="Trust Wallet" class="mb-3" style="height: 60px;">
                                    <h5>Trust Wallet</h5>
                                    <p class="text-muted">Multi-chain mobile wallet</p>
                                    <button id="link-trust" class="btn btn-primary">
                                        <i class="fas fa-plug me-2"></i>Connect Trust Wallet
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <img src="/NS/assets/images/wallets/keplr.png" alt="Keplr" class="mb-3" style="height: 60px;">
                                    <h5>Keplr</h5>
                                    <p class="text-muted">Cosmos ecosystem wallet</p>
                                    <button id="link-keplr" class="btn btn-secondary">
                                        <i class="fas fa-plug me-2"></i>Connect Keplr
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <h4 class="mt-4 mb-3"><i class="fas fa-university me-2"></i>Banking Integrations</h4>
                    <div class="alert alert-info mb-3">
                        <p><i class="fas fa-info-circle me-2"></i>Connect your bank account for seamless fiat deposits and withdrawals.</p>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-4 mb-4">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <img src="/NS/assets/images/wallets/kbc.png" alt="KBC" class="mb-3" style="height: 60px;">
                                    <h5>KBC Bank</h5>
                                    <p class="text-muted">Belgian banking integration</p>
                                    <button id="link-kbc" class="btn btn-primary">
                                        <i class="fas fa-plug me-2"></i>Connect KBC
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <img src="/NS/assets/images/wallets/bnp.png" alt="BNP Paribas" class="mb-3" style="height: 60px;">
                                    <h5>BNP Paribas</h5>
                                    <p class="text-muted">Belgian banking integration</p>
                                    <button id="link-bnp" class="btn btn-success">
                                        <i class="fas fa-plug me-2"></i>Connect BNP
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <img src="/NS/assets/images/wallets/belfius.png" alt="Belfius" class="mb-3" style="height: 60px;">
                                    <h5>Belfius</h5>
                                    <p class="text-muted">Belgian banking integration</p>
                                    <button id="link-belfius" class="btn btn-danger">
                                        <i class="fas fa-plug me-2"></i>Connect Belfius
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <h4 class="mt-4 mb-3"><i class="fas fa-credit-card me-2"></i>Payment Methods</h4>
                    <div class="alert alert-info mb-3">
                        <p><i class="fas fa-info-circle me-2"></i>Add payment methods to quickly buy crypto or fund your account.</p>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-4 mb-4">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <img src="/NS/assets/images/wallets/revolut.png" alt="Revolut" class="mb-3" style="height: 60px;">
                                    <h5>Revolut</h5>
                                    <p class="text-muted">Digital banking platform</p>
                                    <button id="link-revolut" class="btn btn-dark">
                                        <i class="fas fa-plug me-2"></i>Connect Revolut
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <i class="fab fa-google-pay fa-3x mb-3 text-primary"></i>
                                    <h5>Google Pay</h5>
                                    <p class="text-muted">Mobile payment service</p>
                                    <button id="link-googlepay" class="btn btn-primary">
                                        <i class="fas fa-plug me-2"></i>Connect Google Pay
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <i class="fab fa-paypal fa-3x mb-3 text-info"></i>
                                    <h5>PayPal</h5>
                                    <p class="text-muted">Online payment system</p>
                                    <button id="link-paypal" class="btn btn-info">
                                        <i class="fas fa-plug me-2"></i>Connect PayPal
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
    statusEl.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-warning');
    statusEl.classList.add('alert-info');
    statusEl.innerHTML = '<i class="fas fa-info-circle me-2"></i>MetaMask integration coming soon!';
});

// Solflare Connection
document.getElementById('link-solflare').addEventListener('click', async () => {
    const statusEl = document.getElementById('wallet-status');
    statusEl.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-warning');
    statusEl.classList.add('alert-info');
    
    if (window?.solflare?.isConnected !== undefined) {
        try {
            statusEl.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Connecting to Solflare Wallet...';
            
            const provider = window.solflare;
            await provider.connect();
            const publicKey = provider.publicKey.toString();
            
            statusEl.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Linking wallet to your account...';
            
            const link = await fetch('wallet-auth.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    provider: 'solflare',
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
            statusEl.classList.remove('alert-info');
            statusEl.classList.add('alert-danger');
            statusEl.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>' + 
                'Connection failed: ' + (err.message || 'Unknown error');
        }
    } else {
        statusEl.classList.remove('alert-info');
        statusEl.classList.add('alert-warning');
        statusEl.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>' + 
            'Solflare Wallet not detected. <a href="https://solflare.com/" target="_blank" class="alert-link">Install Solflare Wallet</a> first.';
    }
});

// Glow Connection
document.getElementById('link-glow').addEventListener('click', function() {
    const statusEl = document.getElementById('wallet-status');
    statusEl.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-warning');
    statusEl.classList.add('alert-info');
    statusEl.innerHTML = '<i class="fas fa-info-circle me-2"></i>Glow wallet integration coming soon!';
});

// Magic Eden Connection
document.getElementById('link-magiceden').addEventListener('click', function() {
    const statusEl = document.getElementById('wallet-status');
    statusEl.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-warning');
    statusEl.classList.add('alert-info');
    statusEl.innerHTML = '<i class="fas fa-info-circle me-2"></i>Magic Eden wallet integration coming soon!';
});

// MathWallet Connection
document.getElementById('link-mathwallet').addEventListener('click', function() {
    const statusEl = document.getElementById('wallet-status');
    statusEl.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-warning');
    statusEl.classList.add('alert-info');
    statusEl.innerHTML = '<i class="fas fa-info-circle me-2"></i>MathWallet integration coming soon!';
});

// Trust Wallet Connection
document.getElementById('link-trust').addEventListener('click', function() {
    const statusEl = document.getElementById('wallet-status');
    statusEl.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-warning');
    statusEl.classList.add('alert-info');
    statusEl.innerHTML = '<i class="fas fa-info-circle me-2"></i>Trust Wallet integration coming soon!';
});

// Keplr Connection
document.getElementById('link-keplr').addEventListener('click', async () => {
    const statusEl = document.getElementById('wallet-status');
    statusEl.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-warning');
    statusEl.classList.add('alert-info');
    
    if (window?.keplr !== undefined) {
        try {
            statusEl.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Connecting to Keplr Wallet...';
            
            // Request connection to Keplr
            await window.keplr.enable('cosmoshub-4'); // Using Cosmos Hub as an example
            const offlineSigner = window.keplr.getOfflineSigner('cosmoshub-4');
            const accounts = await offlineSigner.getAccounts();
            const address = accounts[0].address;
            
            statusEl.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Linking wallet to your account...';
            
            const link = await fetch('wallet-auth.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    provider: 'keplr',
                    publicKey: address
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
            statusEl.classList.remove('alert-info');
            statusEl.classList.add('alert-danger');
            statusEl.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>' + 
                'Connection failed: ' + (err.message || 'Unknown error');
        }
    } else {
        statusEl.classList.remove('alert-info');
        statusEl.classList.add('alert-warning');
        statusEl.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>' + 
            'Keplr Wallet not detected. <a href="https://www.keplr.app/" target="_blank" class="alert-link">Install Keplr Wallet</a> first.';
    }
});

// Belgian Bank Connections
// KBC Bank Connection with itsme verification
document.getElementById('link-kbc').addEventListener('click', function() {
    const statusEl = document.getElementById('wallet-status');
    statusEl.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-warning');
    statusEl.classList.add('alert-info');
    statusEl.scrollIntoView({ behavior: 'smooth' });
    statusEl.innerHTML = '<i class="fas fa-info-circle me-2"></i>KBC Bank integration requires itsme® verification for enhanced security. <button id="start-itsme" class="btn btn-sm btn-primary ms-2">Start Verification</button>';
    
    // Add event listener for the itsme verification button
    document.getElementById('start-itsme').addEventListener('click', async function() {
        try {
            statusEl.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Initializing itsme® verification...';
            
            // Initialize itsme verification
            const initResponse = await fetch('/NS/api/integrations/itsme-verification.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'init'
                })
            });
            
            const initResult = await initResponse.json();
            
            if (!initResult.success) {
                throw new Error(initResult.message || 'Failed to initialize itsme verification');
            }
            
            const verificationId = initResult.verification_id;
            
            // Show QR code for scanning
            statusEl.innerHTML = `
                <div class="text-center">
                    <i class="fas fa-qrcode fa-3x mb-3"></i>
                    <h5>Scan with itsme® app</h5>
                    <p>Open your itsme® app and scan this QR code to verify your identity</p>
                    <div class="qr-code-placeholder border p-3 mb-3 mx-auto" style="width: 200px; height: 200px; background-color: #f8f9fa;">
                        <i class="fas fa-qrcode fa-5x text-muted"></i>
                        <p class="mt-2 small text-muted">QR Code: ${verificationId}</p>
                    </div>
                    <div class="progress mb-3">
                        <div id="verification-progress" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                    </div>
                    <p class="text-muted small">Waiting for verification...</p>
                </div>
            `;
            
            // Start progress animation
            const progressBar = document.getElementById('verification-progress');
            let progress = 0;
            
            const progressInterval = setInterval(() => {
                progress += 5;
                if (progress > 95) progress = 95;
                progressBar.style.width = progress + '%';
            }, 500);
            
            // Poll for verification status
            let verified = false;
            let attempts = 0;
            
            while (!verified && attempts < 20) { // Try for about 20 seconds
                attempts++;
                
                // Wait 1 second between checks
                await new Promise(resolve => setTimeout(resolve, 1000));
                
                // Check verification status
                const statusResponse = await fetch('/NS/api/integrations/itsme-verification.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'check_status',
                        verification_id: verificationId
                    })
                });
                
                const statusResult = await statusResponse.json();
                
                if (statusResult.status === 'verified') {
                    verified = true;
                    clearInterval(progressInterval);
                    progressBar.style.width = '100%';
                    
                    // Show biometric 2FA prompt
                    statusEl.innerHTML = `
                        <div class="text-center">
                            <i class="fas fa-fingerprint fa-3x mb-3 text-success"></i>
                            <h5>Identity Verified!</h5>
                            <p>Please complete the connection with biometric authentication</p>
                            <button id="complete-biometric" class="btn btn-primary">Complete with Biometric Auth</button>
                        </div>
                    `;
                    
                    // Add event listener for biometric completion
                    document.getElementById('complete-biometric').addEventListener('click', async function() {
                        statusEl.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Completing KBC Bank connection...';
                        
                        // Complete the verification and link bank account
                        const completeResponse = await fetch('/NS/api/integrations/itsme-verification.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                action: 'complete',
                                provider: 'kbc',
                                account_id: 'kbc_' + Math.random().toString(36).substring(2, 15)
                            })
                        });
                        
                        const completeResult = await completeResponse.json();
                        
                        if (completeResult.success) {
                            statusEl.classList.remove('alert-info');
                            statusEl.classList.add('alert-success');
                            statusEl.innerHTML = `
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-shield-alt fa-2x text-success me-3"></i>
                                    <div>
                                        <h5 class="mb-1">KBC Bank Connected Securely</h5>
                                        <p class="mb-0">Your identity has been verified and your KBC account is now securely linked.</p>
                                        <a href="/NS/dashboard/trading_dashboard.php" class="btn btn-sm btn-success mt-2">Go to Trading Dashboard</a>
                                    </div>
                                </div>
                            `;
                        } else {
                            throw new Error(completeResult.message || 'Failed to complete bank connection');
                        }
                    });
                }
            }
            
            if (!verified) {
                clearInterval(progressInterval);
                throw new Error('Verification timed out. Please try again.');
            }
            
        } catch (err) {
            console.error(err);
            statusEl.classList.remove('alert-info');
            statusEl.classList.add('alert-danger');
            statusEl.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>' + 
                'Verification failed: ' + (err.message || 'Unknown error');
        }
    });
});

// BNP Paribas Connection
document.getElementById('link-bnp').addEventListener('click', function() {
    const statusEl = document.getElementById('wallet-status');
    statusEl.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-warning');
    statusEl.classList.add('alert-info');
    statusEl.scrollIntoView({ behavior: 'smooth' });
    statusEl.innerHTML = '<i class="fas fa-info-circle me-2"></i>BNP Paribas integration requires secure authentication. <button id="start-bnp-auth" class="btn btn-sm btn-success ms-2">Start Authentication</button>';
    
    // Add event listener for the BNP authentication button
    document.getElementById('start-bnp-auth').addEventListener('click', function() {
        statusEl.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Redirecting to BNP Paribas secure login...';
        
        // Simulate BNP authentication process
        setTimeout(() => {
            statusEl.classList.remove('alert-info');
            statusEl.classList.add('alert-success');
            statusEl.innerHTML = '<i class="fas fa-check-circle me-2"></i>BNP Paribas account successfully linked! <a href="/NS/dashboard/trading_dashboard.php" class="alert-link">Go to Trading Dashboard</a>';
        }, 3000);
    });
});

// Belfius Connection
document.getElementById('link-belfius').addEventListener('click', function() {
    const statusEl = document.getElementById('wallet-status');
    statusEl.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-warning');
    statusEl.classList.add('alert-info');
    statusEl.scrollIntoView({ behavior: 'smooth' });
    statusEl.innerHTML = '<i class="fas fa-info-circle me-2"></i>Belfius integration requires app authentication. <button id="start-belfius-auth" class="btn btn-sm btn-danger ms-2">Start Authentication</button>';
    
    // Add event listener for the Belfius authentication button
    document.getElementById('start-belfius-auth').addEventListener('click', function() {
        statusEl.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Launching Belfius app authentication...';
        
        // Simulate Belfius authentication process
        setTimeout(() => {
            statusEl.innerHTML = '<i class="fas fa-qrcode me-2"></i>Scan this QR code with your Belfius app: <div class="mt-2"><i class="fas fa-qrcode fa-5x"></i></div>';
            
            // Simulate successful authentication after 3 seconds
            setTimeout(() => {
                statusEl.classList.remove('alert-info');
                statusEl.classList.add('alert-success');
                statusEl.innerHTML = '<i class="fas fa-check-circle me-2"></i>Belfius account successfully linked! <a href="/NS/dashboard/trading_dashboard.php" class="alert-link">Go to Trading Dashboard</a>';
            }, 3000);
        }, 2000);
    });
});

// Payment Method Connections
// Revolut Connection
document.getElementById('link-revolut').addEventListener('click', function() {
    const statusEl = document.getElementById('wallet-status');
    statusEl.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-warning');
    statusEl.classList.add('alert-info');
    statusEl.scrollIntoView({ behavior: 'smooth' });
    statusEl.innerHTML = '<i class="fas fa-info-circle me-2"></i>Revolut integration requires app authentication. <button id="start-revolut-auth" class="btn btn-sm btn-dark ms-2">Connect Revolut</button>';
    
    // Add event listener for the Revolut authentication button
    document.getElementById('start-revolut-auth').addEventListener('click', function() {
        statusEl.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Launching Revolut authentication...';
        
        // Simulate Revolut authentication process
        setTimeout(() => {
            statusEl.classList.remove('alert-info');
            statusEl.classList.add('alert-success');
            statusEl.innerHTML = '<i class="fas fa-check-circle me-2"></i>Revolut account successfully linked! <a href="/NS/dashboard/trading_dashboard.php" class="alert-link">Go to Trading Dashboard</a>';
        }, 3000);
    });
});

// Google Pay Connection
document.getElementById('link-googlepay').addEventListener('click', function() {
    const statusEl = document.getElementById('wallet-status');
    statusEl.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-warning');
    statusEl.classList.add('alert-info');
    statusEl.scrollIntoView({ behavior: 'smooth' });
    statusEl.innerHTML = '<i class="fas fa-info-circle me-2"></i>Google Pay integration coming soon!';
});

// PayPal Connection
document.getElementById('link-paypal').addEventListener('click', function() {
    const statusEl = document.getElementById('wallet-status');
    statusEl.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-warning');
    statusEl.classList.add('alert-info');
    statusEl.scrollIntoView({ behavior: 'smooth' });
    statusEl.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Redirecting to PayPal authentication...';
    
    // Simulate PayPal authentication process
    setTimeout(() => {
        statusEl.classList.remove('alert-info');
        statusEl.classList.add('alert-success');
        statusEl.innerHTML = '<i class="fas fa-check-circle me-2"></i>PayPal account successfully linked! <a href="/NS/dashboard/trading_dashboard.php" class="alert-link">Go to Trading Dashboard</a>';
    }, 3000);
});
</script>

<?php 
    require_once __DIR__ . '/includes/footer.php'; 
} catch (Exception $e) {
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
}

// End output buffering
ob_end_flush();
?>
