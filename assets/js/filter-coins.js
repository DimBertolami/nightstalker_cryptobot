/* document.addEventListener('DOMContentLoaded', function() {
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
    // Get the table with coins
    const table = document.querySelector('#coins-table');
    if (!table) return;
    
    // Find the price column index
    let priceColumnIndex = -1;
    const headers = table.querySelectorAll('th');
    
    headers.forEach((header, index) => {
        if (header.textContent.trim().toLowerCase().includes('price')) {
            priceColumnIndex = index;
        }
    });
    
    // If price column not found, log error and return
    if (priceColumnIndex === -1) {
        console.error('Price column not found in table');
        return;
    }
    
    console.log(`Found price column at index ${priceColumnIndex}`);
    
    // Target all data rows in the table
    const rows = table.querySelectorAll('tbody tr');
    let hiddenCount = 0;
    
    rows.forEach(row => {
        // Get the price cell based on the identified column index
        const priceCell = row.querySelectorAll('td')[priceColumnIndex];
        
        if (priceCell) {
            // Check for zero price formats
            const priceText = priceCell.textContent.trim();
            const isZeroPrice = priceText === '$0.00' || priceText === '$0' || 
                               priceText === '$0.0' || priceText === '0.00' || 
                               priceText === '$0.000' || parseFloat(priceText.replace('$', '')) === 0;
            
            // Hide rows with zero price
            if (isZeroPrice) {
                row.style.display = 'none';
                row.setAttribute('data-zero-price-hidden', 'true');
                hiddenCount++;
            }
        }
    });
    
    console.log(`Hidden ${hiddenCount} rows with $0.00 price`);
}
 */