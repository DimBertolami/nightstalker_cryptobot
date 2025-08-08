// Night Stalker JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Add hover effect to coin cards
    const coinCards = document.querySelectorAll('.coin-card');
    
    coinCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            // You could add AJAX call here to get more detailed info
            // for the hover effect you requested
            const coinId = this.dataset.coinId;
            console.log('Hovering over:', coinId);
            
            // Example of adding a tooltip
            this.setAttribute('data-bs-toggle', 'tooltip');
            this.setAttribute('title', 'Loading details...');
            
            // In a real implementation, you'd fetch detailed info here
            // fetchCoinDetails(coinId).then(details => {
            //     this.setAttribute('title', 
            //         );
            //     bootstrap.Tooltip.getInstance(this).update();
            // });
        });
    });
    
    // Auto-refresh functionality is now handled in coins.js
    // No need for additional polling here
});

// Example function to fetch coin details
async function fetchCoinDetails(coinId) {
    try {
        const response = await fetch(`/NS/api/coin.php?id=${coinId}`);
        if (!response.ok) throw new Error('Network response was not ok');
        return await response.json();
    } catch (error) {
        console.error('Error fetching coin details:', error);
        return null;
    }
}

// Example function to refresh dashboard data
async function refreshData() {
    try {
        const response = await fetch('/NS/api/refresh.php');
        if (!response.ok) throw new Error('Network response was not ok');
        const data = await response.json();
        
        // Update the UI with new data
        // updateCoinCards(data.coins);
    } catch (error) {
        console.error('Error refreshing data:', error);
    }
}

// buyCoin function has been moved to coins.js to prevent duplicate execution

// sellCoin function has been moved to coins.js to prevent duplicate execution
