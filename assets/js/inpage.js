// inpage.js - Phantom wallet injection simulation and error handling

(function() {
    // Simulate Phantom wallet injection
    function injectPhantomWallet() {
        try {
            if (window.solana) {
                console.warn('window.solana already exists, skipping Phantom simulation injection.');
                return;
            }
            // Simulate Phantom wallet object
            window.solana = {
                isPhantom: true,
                publicKey: null,
                connect: function() {
                    return new Promise((resolve, reject) => {
                        // Simulate successful connection
                        this.publicKey = 'FakePublicKey1234567890';
                        resolve(this.publicKey);
                    });
                },
                disconnect: function() {
                    this.publicKey = null;
                }
            };
            console.log('Phantom wallet injected (simulation).');
        } catch (e) {
            console.log('Error setting window.solana for Phantom simulation:', e.message); // Changed from console.error
        }
    }

    // Simulate Phantom wallet detection
    function detectPhantomWallet() {
        if (!window.solana || !window.solana.isPhantom) {
            console.log('Phantom simulation not detected or not fully injected.'); // Changed from console.error
        } else {
            console.log('Phantom wallet detected (simulation).');
        }
    }

    // Inject Phantom wallet on script load
    injectPhantomWallet();

    // Detect Phantom wallet after injection
    detectPhantomWallet();
})();
