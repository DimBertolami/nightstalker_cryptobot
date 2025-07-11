import requests
from datetime import datetime, timedelta
import time
import subprocess

# Your CoinMarketCap API Key
API_KEY = '1758e18b-1744-4ad6-a2a9-908af2f33c8a'
headers = {'X-CMC_PRO_API_KEY': API_KEY}

# Cache variables
last_fetch_time = 0
cached_data = None
CACHE_DURATION = 900  # 15 minutes

# Track rate limits
last_rate_limit_time = 0
RATE_LIMIT_COOLDOWN = 300  # 5 minutes

def get_recent_cryptos(min_volume=1_500_000):
    """
    Get recent high-volume cryptocurrencies added within the last day
    
    Args:
        min_volume: Minimum 24h volume in USD
        
    Returns:
        List of dictionaries containing cryptocurrency data
    """
    global last_fetch_time, cached_data, last_rate_limit_time
    
    # Check if we're in rate limit cooldown
    if time.time() - last_rate_limit_time < RATE_LIMIT_COOLDOWN:
        print(f"In rate limit cooldown. Using cached data.")
        return cached_data or []
    
    # Check if we have cached data that's still fresh
    current_time = time.time()
    if cached_data and current_time - last_fetch_time < CACHE_DURATION:
        print(f"Using cached data from {datetime.fromtimestamp(last_fetch_time)}")
        return cached_data
    
    # Prepare to fetch new data
    url = 'https://pro-api.coinmarketcap.com/v1/cryptocurrency/listings/latest'
    parameters = {
        'start': '1',
        'limit': '100',
        'sort': 'date_added',
        'sort_dir': 'desc',
        'convert': 'USD'
    }
    
    try:
        response = requests.get(url, headers=headers, params=parameters)
        
        # Handle rate limits
        if response.status_code == 429:
            print(f"Rate limited by CoinMarketCap API. Using cached data.")
            last_rate_limit_time = time.time()
            return cached_data or []
            
        response.raise_for_status()
        data = response.json()
        
        # Calculate one day ago for filtering
        one_day_ago = datetime.now() - timedelta(days=1)
        
        # Filter for recent coins with high volume
        recent_high_volume = []
        for coin in data['data']:
            date_added = datetime.strptime(coin['date_added'], "%Y-%m-%dT%H:%M:%S.%fZ")
            volume_24h = coin['quote']['USD']['volume_24h']
            price = coin['quote']['USD']['price']
            percent_change_1h = coin['quote']['USD']['percent_change_1h']
            
            if date_added > one_day_ago and volume_24h > min_volume:
                recent_high_volume.append({
                    'name': coin['name'],
                    'symbol': coin['symbol'],
                    'date_added': coin['date_added'],
                    'volume_24h': volume_24h,
                    'price': price,
                    'percent_change_1h': percent_change_1h,
                    'cmc_rank': coin['cmc_rank'],
                    'market_cap': coin['quote']['USD']['market_cap']
                })
        
        # Update cache
        cached_data = recent_high_volume
        last_fetch_time = current_time
        
        print(f"Found {len(recent_high_volume)} recent high-volume cryptocurrencies")
        return recent_high_volume
        
    except requests.exceptions.RequestException as e:
        print(f"API Error: {e}")
        return cached_data or []  

def setup_tables(db_name="Crypto_Stalker_py"):
    """
    Set up the necessary database tables
    
    Args:
        db_name: Database name
    """
    setup_cmd = f"""
    mysql -u root -p1304 -e "
    -- Create newcoins table if it doesn't exist
    CREATE TABLE IF NOT EXISTS newcoins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        symbol VARCHAR(20) NOT NULL,
        name VARCHAR(100) NOT NULL,
        price DECIMAL(20,10) NOT NULL,
        volume_24h DECIMAL(20,2) NOT NULL,
        percent_change_1h DECIMAL(10,2),
        market_cap DECIMAL(20,2),
        timestamp DATETIME NOT NULL,
        date_added DATETIME,
        UNIQUE KEY unique_symbol (symbol)
    );
    
    -- Create price_history table if it doesn't exist
    CREATE TABLE IF NOT EXISTS price_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        newcoin_id INT NOT NULL,
        price DECIMAL(20,10) NOT NULL,
        timestamp DATETIME NOT NULL,
        FOREIGN KEY (newcoin_id) REFERENCES newcoins(id) ON DELETE CASCADE
    );
    " {db_name}
    """
    
    try:
        subprocess.run(setup_cmd, shell=True, check=True, text=True, capture_output=True)
        print(f"Successfully set up database tables")
        return True
    except subprocess.CalledProcessError as e:
        print(f"Error setting up tables: {e}")
        print(f"Error output: {e.stderr}")
        return False

def save_coins_to_db(coins, db_name="Crypto_Stalker_py", table_name="newcoins"):
    """
    Purge the newcoins table and save cryptocurrency data to MySQL database
    
    Args:
        coins: List of coin dictionaries from get_recent_cryptos()
        db_name: Database name
        table_name: Table name
    """
    if not coins:
        print("No coins to save to database")
        return False
    
    # First, purge the newcoins table
    purge_cmd = f"""
    mysql -u root -p1304 -e "
    -- Purge the newcoins table
    TRUNCATE TABLE {table_name};
    " {db_name}
    """
    
    try:
        subprocess.run(purge_cmd, shell=True, check=True, text=True, capture_output=True)
        print(f"Successfully purged {table_name} table")
    except subprocess.CalledProcessError as e:
        print(f"Error purging table: {e}")
        print(f"Error output: {e.stderr}")
        return False
        
    # Current timestamp for the database
    current_time = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    
    # Generate SQL for each coin
    sql_statements = []
    for coin in coins:
        # Format the date_added string to MySQL datetime format
        date_added = coin['date_added'].replace('T', ' ')
        if '.000Z' in date_added:
            date_added = date_added.replace('.000Z', '')
        
        # Create the SQL insert statement
        sql = f"""
        INSERT INTO {table_name} (symbol, name, price, volume_24h, percent_change_1h, market_cap, timestamp, date_added)
        VALUES ('{coin['symbol']}', '{coin['name'].replace("'", "''")}', {coin['price']}, {coin['volume_24h']}, {coin['percent_change_1h']}, {coin['market_cap']}, '{current_time}', '{date_added}');
        """
        
        sql_statements.append(sql)
    
    # Combine all SQL statements
    all_sql = ' '.join(sql_statements)
    
    # Execute the SQL using the command line
    mysql_cmd = f"mysql -u root -p1304 -e \"{all_sql}\" {db_name}"
    
    try:
        # Execute the command
        result = subprocess.run(mysql_cmd, shell=True, check=True, text=True, capture_output=True)
        print(f"Successfully added {len(coins)} coins to database")
        return True
    except subprocess.CalledProcessError as e:
        print(f"Error adding coins to database: {e}")
        print(f"Error output: {e.stderr}")
        return False

def update_price_history(symbols, db_name="Crypto_Stalker_py"):
    """
    Update price history for specified symbols in the price_history table
    
    Args:
        symbols: List of symbols to update
        db_name: Database name
    """
    if not symbols:
        print("No symbols to update")
        return False
    
    # Get current price data from CoinMarketCap
    url = 'https://pro-api.coinmarketcap.com/v1/cryptocurrency/quotes/latest'
    
    # Convert symbols list to comma-separated string
    symbol_string = ','.join(symbols)
    
    params = {
        'symbol': symbol_string,
        'convert': 'USD'
    }
    
    try:
        response = requests.get(url, headers=headers, params=params)
        
        # Handle rate limits
        if response.status_code == 429:
            print(f"Rate limited by CoinMarketCap API - waiting before retry")
            time.sleep(10)  # Wait longer if rate limited
            return False
            
        response.raise_for_status()
        data = response.json()
        
        # Current timestamp for the database
        current_time = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
        
        # Generate SQL for price updates and insertions
        update_sql = []
        insert_sql = []
        
        for symbol in symbols:
            if symbol in data['data']:
                coin_data = data['data'][symbol]
                price = coin_data['quote']['USD']['price']
                volume_24h = coin_data['quote']['USD']['volume_24h']
                percent_change_1h = coin_data['quote']['USD']['percent_change_1h']
                market_cap = coin_data['quote']['USD']['market_cap']
                
                # Update the newcoins table with all data
                update_sql.append(f"""
                UPDATE newcoins 
                SET price = {price}, 
                    percent_change_1h = {percent_change_1h}, 
                    volume_24h = {volume_24h},
                    market_cap = {market_cap},
                    timestamp = '{current_time}'
                WHERE symbol = '{symbol}';
                """)
                
                # Insert into price_history table - only price and foreign key
                insert_sql.append(f"""
                INSERT INTO price_history (newcoin_id, price, timestamp)
                SELECT id, {price}, '{current_time}'
                FROM newcoins
                WHERE symbol = '{symbol}';
                """)
        
        # Combine all SQL statements
        all_sql = ' '.join(update_sql + insert_sql)
        
        # Execute the SQL using the command line
        mysql_cmd = f"mysql -u root -p1304 -e \"{all_sql}\" {db_name}"
        
        try:
            # Execute the command
            result = subprocess.run(mysql_cmd, shell=True, check=True, text=True, capture_output=True)
            print(f"Successfully updated prices for {len(symbols)} coins")
            return True
        except subprocess.CalledProcessError as e:
            print(f"Error updating prices: {e}")
            print(f"Error output: {e.stderr}")
            return False
    
    except requests.exceptions.RequestException as e:
        print(f"API Error: {e}")
        return False

def get_price_history_for_symbol(symbol, limit=20, db_name="Crypto_Stalker_py"):
    """
    Get price history for a specific symbol
    
    Args:
        symbol: Cryptocurrency symbol
        limit: Maximum number of price points to retrieve
        db_name: Database name
        
    Returns:
        List of prices
    """
    query = f"""
    SELECT ph.price 
    FROM price_history ph
    JOIN newcoins nc ON ph.newcoin_id = nc.id
    WHERE nc.symbol = '{symbol}'
    ORDER BY ph.timestamp DESC
    LIMIT {limit};
    """
    
    mysql_cmd = f"mysql -u root -p1304 -e \"{query}\" {db_name}"
    
    try:
        result = subprocess.run(mysql_cmd, shell=True, check=True, text=True, capture_output=True)
        lines = result.stdout.strip().split('\n')
        
        # Skip header row
        if len(lines) <= 1:
            return []
            
        # Extract prices and convert to float
        prices = []
        for line in lines[1:]:
            try:
                prices.append(float(line.strip()))
            except ValueError:
                continue
                
        # Return prices in chronological order (oldest first)
        return list(reversed(prices))
    except subprocess.CalledProcessError as e:
        print(f"Error getting price history: {e}")
        return []

# Example usage
if __name__ == "__main__":
    import sys
    
    # Check if any arguments were provided
    if len(sys.argv) > 1 and sys.argv[1] == "--help":
        print("Usage: python cmc.py [command]")
        print("Commands:")
        print("  setup   - Set up database tables")
        print("  add     - Fetch and add recent high-volume cryptocurrencies to database")
        print("  update  - Update prices for all cryptocurrencies in database")
        print("  chart   - Display price chart for a specific symbol")
        print("  No command will just fetch recent cryptocurrencies without saving")
        sys.exit(0)
    
    # If command is 'setup', set up database tables
    if len(sys.argv) > 1 and sys.argv[1] == "setup":
        setup_tables()
        sys.exit(0)
    
    # Default behavior - just fetch recent cryptos
    coins = get_recent_cryptos()
    
    # If command is 'add', add coins to database
    if len(sys.argv) > 1 and sys.argv[1] == "add":
        print("Fetching recent high-volume cryptocurrencies...")
        coins = get_recent_cryptos(min_volume=1_500_000)
        print(f"Found {len(coins)} cryptocurrencies to add to database")
        save_coins_to_db(coins)
        
        # Verify the data was added
        verify_cmd = "mysql -u root -p1304 -e \"SELECT id, symbol, name, price, volume_24h, market_cap, timestamp FROM newcoins\" Crypto_Stalker_py"
        try:
            result = subprocess.run(verify_cmd, shell=True, check=True, text=True, capture_output=True)
            print("\nCurrent database contents:")
            print(result.stdout)
        except subprocess.CalledProcessError as e:
            print(f"Error verifying data: {e}")
    
    # If command is 'update', update prices for all coins in database
    elif len(sys.argv) > 1 and sys.argv[1] == "update":
        # Get symbols from database
        get_symbols_cmd = "mysql -u root -p1304 -e \"SELECT symbol FROM newcoins\" Crypto_Stalker_py"
        try:
            result = subprocess.run(get_symbols_cmd, shell=True, check=True, text=True, capture_output=True)
            lines = result.stdout.strip().split('\n')
            if len(lines) <= 1:  # Only header row or empty
                print("No symbols found in database")
                sys.exit(1)
                
            symbols = [line.strip() for line in lines[1:]]  # Skip header row
            print(f"Updating prices for {len(symbols)} cryptocurrencies...")
            update_price_history(symbols)
            
            # Verify the data was updated
            verify_cmd = "mysql -u root -p1304 -e \"SELECT symbol, price, percent_change_1h, volume_24h, market_cap, timestamp FROM newcoins\" Crypto_Stalker_py"
            result = subprocess.run(verify_cmd, shell=True, check=True, text=True, capture_output=True)
            print("\nUpdated prices:")
            print(result.stdout)
        except subprocess.CalledProcessError as e:
            print(f"Error: {e}")
    
    # If command is 'chart', display price chart for a specific symbol
    elif len(sys.argv) > 1 and sys.argv[1] == "chart":
        if len(sys.argv) < 3:
            print("Please specify a symbol to chart")
            print("Example: python cmc.py chart BTC")
            sys.exit(1)
            
        symbol = sys.argv[2].upper()
        print(f"Getting price history for {symbol}...")
        
        prices = get_price_history_for_symbol(symbol)
        if not prices:
            print(f"No price history found for {symbol}")
            sys.exit(1)
            
        print(f"Price history for {symbol} (most recent {len(prices)} data points):")
        print(f"Current price: ${prices[-1]:.6f}")
        print(f"Min price: ${min(prices):.6f}")
        print(f"Max price: ${max(prices):.6f}")
        
        # TODO: Implement ASCII chart plotting using asciichart.py
        print("Price chart will be implemented in the next version")
    else:
        # Just print the coins that were found
        print(f"Found {len(coins)} recent high-volume cryptocurrencies:")
        for coin in coins:
            print(f"{coin['symbol']}: {coin['name']} - ${coin['price']:.6f} | Volume: ${coin['volume_24h']:.2f} | Market Cap: ${coin['market_cap']:.2f} | Added: {coin['date_added']}")
