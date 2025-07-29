window.onload = function() {
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
    async function handleWalletResponse(providerName, response) {
        try {
            const publicKey = response.publicKey.toString();
            
            updateWalletStatus('<i class="fas fa-spinner fa-spin me-2"></i>Linking wallet to your account...', 'info');
            
            const link = await fetch('/NS/api/wallet-auth.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    provider: providerName,
                    publicKey: publicKey
                })
            });
            
            if (!link.ok) {
                throw new Error(`HTTP error! status: ${link.status}`);
            }
            
            const result = await link.json();
            
            if (result.success) {
                updateWalletStatus(
                    '<i class="fas fa-check-circle me-2"></i>Wallet linked successfully! <a href="/NS/dashboard/trading_dashboard.php" class="alert-link">Go to Trading Dashboard</a>',
                    'success'
                );
                // Store all connected wallets in session storage
                sessionStorage.setItem('connectedWallets', JSON.stringify(result.connectedWallets));
                console.log('Wallets linked successfully:', result.connectedWallets);
                // Redirect after a short delay
                setTimeout(() => {
                    window.location.href = '/NS/dashboard/trading_dashboard.php';
                }, 1500);
            } else {
                throw new Error(result.message || 'Failed to link wallet');
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
        try {
            // More robust provider detection for multi-wallet environments
            const provider = window.phantom?.solana || window.solana?.providers?.find(p => p.isPhantom);

            if (!provider) {
                throw new Error('Phantom Wallet not detected. Please install Phantom Wallet.');
            }

            updateWalletStatus('<i class="fas fa-spinner fa-spin me-2"></i>Connecting to Phantom Wallet...', 'info');
            
            const resp = await provider.connect();
            handleWalletResponse('phantom', resp);

        } catch (err) {
            console.error('Phantom connection error:', err);
            updateWalletStatus(
                '<i class="fas fa-exclamation-triangle me-2"></i>' +
                'Connection failed: ' + (err.message || 'User rejected the request'),
                'danger'
            );
        }
    }

    // Solflare Wallet Connection
    async function connectSolflareWallet() {
        try {
            // More robust provider detection for multi-wallet environments
            const provider = window.solflare || window.solana?.providers?.find(p => p.isSolflare);

            if (!provider) {
                throw new Error('Solflare Wallet not detected. Please install Solflare Wallet.');
            }

            updateWalletStatus('<i class="fas fa-spinner fa-spin me-2"></i>Connecting to Solflare Wallet...', 'info');

            await provider.connect();
            // After successful connection, publicKey should be available on the provider
            if (!provider.publicKey) {
                throw new Error('Public key not found after Solflare connection.');
            }
            handleWalletResponse('solflare', { publicKey: provider.publicKey });

        } catch (err) {
            console.error('Solflare connection error:', err);
            updateWalletStatus(
                '<i class="fas fa-exclamation-triangle me-2"></i>' +
                'Connection failed: ' + (err.message || 'User rejected the request'),
                'danger'
            );
        }
    }

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
};