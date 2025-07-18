/**
 * Table sorting functionality for Night Stalker
 * Allows sorting of table columns by clicking on headers
 * Integrates with existing filtering and pagination system
 */
document.addEventListener('DOMContentLoaded', function() {
    // Store the current sort state
    window.tableSortState = {
        column: null,
        direction: 'asc'
    };
    
    // Add click event listeners to sortable headers
    document.querySelectorAll('.sortable').forEach(header => {
        header.addEventListener('click', function() {
            const sortKey = this.getAttribute('data-sort');
            const sortIcon = this.querySelector('i');
            
            // Update sort direction
            if (window.tableSortState.column === sortKey) {
                window.tableSortState.direction = window.tableSortState.direction === 'asc' ? 'desc' : 'asc';
            } else {
                window.tableSortState.column = sortKey;
                window.tableSortState.direction = 'asc';
            }
            
            // Update all icons to default
            document.querySelectorAll('.sortable i').forEach(icon => {
                icon.className = 'fas fa-sort';
            });
            
            // Update this icon to show sort direction
            sortIcon.className = 'fas fa-sort-' + (window.tableSortState.direction === 'asc' ? 'up' : 'down');
            
            // Sort the table
            sortTable(sortKey, window.tableSortState.direction);
            
            // After sorting, reapply any active filters
            if (typeof applyCustomFilters === 'function') {
                applyCustomFilters();
            }
            
            // Reapply pagination if it exists
            if (typeof applyPagination === 'function') {
                applyPagination();
            }
            
            // Update entries info if the function exists
            if (typeof updateEntriesInfo === 'function') {
                updateEntriesInfo();
            }
        });
    });
    
    // Make the sort function available globally
    window.sortTable = function(sortKey, direction) {
        console.log(`%c🔄 Sorting table by ${sortKey} in ${direction} order`, 'color: #00bfff; font-weight: bold;');
        
        const table = document.getElementById('coins-table');
        if (!table) {
            console.error('Could not find coins-table element');
            return;
        }
        
        const tbody = table.querySelector('tbody');
        if (!tbody) {
            console.error('Could not find tbody element');
            return;
        }
        
        const rows = Array.from(tbody.querySelectorAll('tr'));
        console.log(`Found ${rows.length} rows to sort`);
        
        // Sort rows based on the selected column
        rows.sort((a, b) => {
            let aValue, bValue;
            
            switch(sortKey) {
                case 'coin':
                    // Sort by coin name (column 0)
                    aValue = a.querySelector('td:nth-child(1)').textContent.trim();
                    bValue = b.querySelector('td:nth-child(1)').textContent.trim();
                    return direction === 'asc' ? 
                        aValue.localeCompare(bValue) : 
                        bValue.localeCompare(aValue);
                
                case 'price':
                    // Sort by price (column 1)
                    aValue = parseFloat(a.querySelector('td:nth-child(2)').textContent.replace('$', '').replace(/,/g, ''));
                    bValue = parseFloat(b.querySelector('td:nth-child(2)').textContent.replace('$', '').replace(/,/g, ''));
                    break;
                
                case '24h-change':
                    // Sort by 24h change percentage (column 2)
                    const aText = a.querySelector('td:nth-child(3)').textContent.trim();
                    const bText = b.querySelector('td:nth-child(3)').textContent.trim();
                    
                    // Log the values for debugging
                    console.log(`Comparing 24h change values: '${aText}' vs '${bText}'`);
                    
                    // Handle percentage values correctly
                    aValue = parseFloat(aText.replace('%', '').replace(/[+]/g, ''));
                    bValue = parseFloat(bText.replace('%', '').replace(/[+]/g, ''));
                    
                    // Handle NaN values
                    if (isNaN(aValue)) aValue = 0;
                    if (isNaN(bValue)) bValue = 0;
                    break;
                    
                case 'volume':
                    // Sort by volume (column 3)
                    aValue = parseFloat(a.querySelector('td:nth-child(4)').textContent.replace('$', '').replace(/,/g, ''));
                    bValue = parseFloat(b.querySelector('td:nth-child(4)').textContent.replace('$', '').replace(/,/g, ''));
                    break;
                    
                case 'market-cap':
                    // Sort by market cap (column 4)
                    aValue = parseFloat(a.querySelector('td:nth-child(5)').textContent.replace('$', '').replace(/,/g, ''));
                    bValue = parseFloat(b.querySelector('td:nth-child(5)').textContent.replace('$', '').replace(/,/g, ''));
                    break;
                case 'age':
                    // Sort by Age (column 6, using data-sort timestamp if present)
                    const aAgeCell = a.querySelector('td:nth-child(6)');
                    const bAgeCell = b.querySelector('td:nth-child(6)');
                    aValue = aAgeCell.getAttribute('data-sort') ? Number(aAgeCell.getAttribute('data-sort')) : new Date(aAgeCell.textContent).getTime();
                    bValue = bAgeCell.getAttribute('data-sort') ? Number(bAgeCell.getAttribute('data-sort')) : new Date(bAgeCell.textContent).getTime();
                    break;
            }
            
            // Handle NaN values
            if (isNaN(aValue)) aValue = 0;
            if (isNaN(bValue)) bValue = 0;
            
            return direction === 'asc' ? aValue - bValue : bValue - aValue;
        });
        
        // Re-append rows in the new order
        rows.forEach(row => tbody.appendChild(row));
        
        // Trigger a custom event that other scripts can listen for
        const sortEvent = new CustomEvent('tableSorted', {
            detail: { sortKey, direction }
        });
        document.dispatchEvent(sortEvent);
        
        return true;
    };
    
    // Add event listener for custom filters
    document.addEventListener('filtersApplied', function() {
        // If we have an active sort, reapply it after filters change
        if (window.tableSortState.column) {
            sortTable(window.tableSortState.column, window.tableSortState.direction);
        }
    });
    
    // Add event listener for pagination events
    document.addEventListener('paginationApplied', function() {
        // We don't need to resort here, just ensure the sort icons are correct
        if (window.tableSortState.column) {
            // Update all icons to default
            document.querySelectorAll('.sortable i').forEach(icon => {
                icon.className = 'fas fa-sort';
            });
            
            // Find the current sorted column and update its icon
            const sortHeader = document.querySelector(`.sortable[data-sort="${window.tableSortState.column}"]`);
            if (sortHeader && sortHeader.querySelector('i')) {
                sortHeader.querySelector('i').className = 'fas fa-sort-' + 
                    (window.tableSortState.direction === 'asc' ? 'up' : 'down');
            }
        }
    });
});
