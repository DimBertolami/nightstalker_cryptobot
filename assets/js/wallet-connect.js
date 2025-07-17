// Wallet connection handlers
let walletProvider = null;

// Initialize wallet providers
async function initializeWalletProviders() {
    // Check for Phantom wallet
    if (window?.phantom?.solana) {
        console.log('Phantom wallet initialization complete');
        if (window.phantom.solana.isPhantom) {
            console.log('Phantom wallet detected');
        }
    }
}

// Generic function to show wallet status
function updateWalletStatus(message, type = 'info') {
    const statusEl = document.getElementById('wallet-status');
    if (statusEl) {
        statusEl.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-warning', 'alert-info');
        statusEl.classList.add(`alert-${type}`);
        statusEl.innerHTML = message;
    }
}

// Handle wallet connection response
async function handleWalletResponse(provider, response) {
    try {
        const publicKey = response.publicKey.toString();
        
        updateWalletStatus('<i class="fas fa-spinner fa-spin me-2"></i>Linking wallet to your account...', 'info');
        
        const link = await fetch('/NS/wallet-auth.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                provider: provider,
                publicKey: publicKey
            })
        });
        
        if (!link.ok) {
            throw new Error(`HTTP error! status: ${link.status}`);
        }
        
        const result = await link.json();
        
        if (result.success) {
            updateWalletStatus(
                '<i class="fas fa-check-circle me-2"></i>Wallet linked successfully! ' + 
                '<a href="/NS/dashboard/trading_dashboard.php" class="alert-link">Go to Trading Dashboard</a>',
                'success'
            );
            // Store wallet info in session
            sessionStorage.setItem('walletProvider', provider);
            sessionStorage.setItem('walletAddress', publicKey);
        } else {
            updateWalletStatus(
                '<i class="fas fa-exclamation-triangle me-2"></i>' + 
                (result.message || 'Failed to link wallet'),
                'danger'
            );
        }
    } catch (err) {
        console.error('Wallet connection error:', err);
        updateWalletStatus(
            '<i class="fas fa-exclamation-triangle me-2"></i>' + 
            'Connection failed: ' + (err.message || 'Unknown error'),
            'danger'
        );
    }
}

// Phantom Wallet Connection
async function connectPhantomWallet() {
    if (!window?.phantom?.solana?.isPhantom) {
        updateWalletStatus(
            '<i class="fas fa-exclamation-triangle me-2"></i>' + 
            'Phantom Wallet not detected. <a href="https://phantom.app/" target="_blank" class="alert-link">Install Phantom Wallet</a> first.',
            'warning'
        );
        return;
    }

    try {
        updateWalletStatus('<i class="fas fa-spinner fa-spin me-2"></i>Connecting to Phantom Wallet...', 'info');
        const response = await window.phantom.solana.connect();
        await handleWalletResponse('phantom', response);
    } catch (err) {
        console.error('Phantom connection error:', err);
        updateWalletStatus(
            '<i class="fas fa-exclamation-triangle me-2"></i>' + 
            'Connection failed: ' + (err.message || 'Unknown error'),
            'danger'
        );
    }
}

// Solflare Wallet Connection
async function connectSolflareWallet() {
    if (!window?.solflare?.isConnected) {
        updateWalletStatus(
            '<i class="fas fa-exclamation-triangle me-2"></i>' + 
            'Solflare Wallet not detected. <a href="https://solflare.com/" target="_blank" class="alert-link">Install Solflare Wallet</a> first.',
            'warning'
        );
        return;
    }

    try {
        updateWalletStatus('<i class="fas fa-spinner fa-spin me-2"></i>Connecting to Solflare Wallet...', 'info');
        await window.solflare.connect();
        const publicKey = window.solflare.publicKey.toString();
        await handleWalletResponse('solflare', { publicKey: window.solflare.publicKey });
    } catch (err) {
        console.error('Solflare connection error:', err);
        updateWalletStatus(
            '<i class="fas fa-exclamation-triangle me-2"></i>' + 
            'Connection failed: ' + (err.message || 'Unknown error'),
            'danger'
        );
    }
}

// Initialize wallet connections when document is ready
document.addEventListener('DOMContentLoaded', () => {
    initializeWalletProviders();

    // Set up event listeners for wallet buttons
    const phantomBtn = document.getElementById('link-phantom');
    if (phantomBtn) {
        phantomBtn.addEventListener('click', connectPhantomWallet);
    }

    const solflareBtn = document.getElementById('link-solflare');
    if (solflareBtn) {
        solflareBtn.addEventListener('click', connectSolflareWallet);
    }

    // Add "Coming Soon" handlers for other wallet buttons
    const comingSoonWallets = [
        'link-metamask', 'link-glow', 'link-magiceden',
        'link-mathwallet', 'link-trust', 'link-keplr'
    ];

    comingSoonWallets.forEach(id => {
        const btn = document.getElementById(id);
        if (btn) {
            btn.addEventListener('click', () => {
                updateWalletStatus(
                    '<i class="fas fa-info-circle me-2"></i>' +
                    `${btn.textContent.trim().replace('Connect ', '')} integration coming soon!`,
                    'info'
                );
            });
        }
    });
});
