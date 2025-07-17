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
// Moved all JavaScript to wallet-connect.js for better organization and maintenance
</script>

<?php
} catch (Exception $e) {
    error_log('Error in link-wallet.php: ' . $e->getMessage());
    echo '<div class="alert alert-danger">An error occurred while loading the page. Please try again later.</div>';
}
?>

<?php
// End output buffering
ob_end_flush();
?>
