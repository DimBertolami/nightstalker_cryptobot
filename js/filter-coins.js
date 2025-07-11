document.addEventListener('DOMContentLoaded', function() {
    // Get the filter-zero-price toggle element
    const filterZeroPriceToggle = document.getElementById('filter-zero-price');
    
    if (filterZeroPriceToggle) {
        // Get initial state from cookie or default to false
        const hideZeroPrice = document.cookie.includes('hide_zero_price=1');
        filterZeroPriceToggle.checked = hideZeroPrice;
        
        // Apply initial filter if enabled
        if (hideZeroPrice) {
            filterZeroPriceCoins();
        }
        
        // Add event listener for toggle
        filterZeroPriceToggle.addEventListener('change', function() {
            const shouldHide = this.checked;
            // Store preference in cookie (expires in 30 days)
            document.cookie = `hide_zero_price=${shouldHide ? '1' : '0'}; path=/; max-age=${30 * 24 * 60 * 60}`;
            
            if (shouldHide) {
                filterZeroPriceCoins();
            } else {
                // Show all hidden rows
                document.querySelectorAll('tr[data-zero-price-hidden]').forEach(row => {
                    row.style.display = '';
                    row.removeAttribute('data-zero-price-hidden');
                });
            }
        });

        // Add immediate click handler to ensure it works right away
        setTimeout(function() {
            if (filterZeroPriceToggle.checked) {
                filterZeroPriceCoins();
            }
        }, 1000);
    }
});

function filterZeroPriceCoins() {
    // Target all rows in any table
    const allRows = document.querySelectorAll('tr');
    let hiddenCount = 0;
    
    allRows.forEach(row => {
        // Skip header rows
        if (row.querySelector('th')) return;
        
        // Check all cells in the row for $0.00 price
        const cells = row.querySelectorAll('td');
        let hasZeroPrice = false;
        
        cells.forEach(cell => {
            // Check for both $0.00 and $0 formats
            const text = cell.textContent.trim();
            if (text === '$0.00' || text === '$0' || text === '$0.0' || text === '0.00' || text === '$0.000') {
                hasZeroPrice = true;
            }
        });
        
        // Hide rows with zero price
        if (hasZeroPrice) {
            row.style.display = 'none';
            row.setAttribute('data-zero-price-hidden', 'true');
            hiddenCount++;
        }
    });
    
    console.log(`Hidden ${hiddenCount} rows with $0.00 price`);
}
