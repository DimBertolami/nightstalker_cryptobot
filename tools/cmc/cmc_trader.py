import requests
from datetime import datetime, timedelta
import time
import subprocess
import sys
from typing import List, Dict, Optional

# Configuration
API_KEY = '1758e18b-1744-4ad6-a2a9-908af2f33c8a'
DB_NAME = "Crypto_Stalker_py"
WALLET_BALANCE = 1000.00  # Starting balance in USD
MIN_VOLUME = 1_500_000  # Minimum 24h volume in USD
PRICE_UPDATE_INTERVAL = 15  # Seconds
DECLINE_DURATION_TO_SELL = 120  # Seconds of declining prices before selling

headers = {'X-CMC_PRO_API_KEY': API_KEY}

class TradingBot:
    def __init__(self):
        self.current_holdings: Dict[str, Dict] = {}  # symbol: {buy_price, quantity, peak_price}
        self.wallet_balance = WALLET_BALANCE
        self.setup_complete = False
    
    def _get_timestamp(self):
        """Helper method to get current timestamp in YYYY-MM-DD HH:MM:SS format"""
        return datetime.now().strftime('%Y-%m-%d %H:%M:%S')

    def setup_tables(self):
        """Create required database tables if they don't exist"""
        setup_cmd = f"""
        mysql -u root -p1304 -e "
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
        
        CREATE TABLE IF NOT EXISTS price_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            newcoin_id INT NOT NULL,
            price DECIMAL(20,10) NOT NULL,
            timestamp DATETIME NOT NULL,
            FOREIGN KEY (newcoin_id) REFERENCES newcoins(id) ON DELETE CASCADE
        );
        
        CREATE TABLE IF NOT EXISTS trades (
            id INT AUTO_INCREMENT PRIMARY KEY,
            symbol VARCHAR(20) NOT NULL,
            buy_price DECIMAL(20,10) NOT NULL,
            sell_price DECIMAL(20,10),
            quantity DECIMAL(20,10) NOT NULL,
            buy_time DATETIME NOT NULL,
            sell_time DATETIME,
            profit DECIMAL(20,10),
            status ENUM('open', 'closed') NOT NULL
        );
        " {DB_NAME}
        """
        
        try:
            subprocess.run(setup_cmd, shell=True, check=True)
            print("Database tables setup complete")
            self.setup_complete = True
            return True
        except subprocess.CalledProcessError as e:
            print(f"Error setting up tables: {e}")
            return False

    def get_recent_cryptos(self) -> List[Dict]:
        """Fetch recent high-volume cryptocurrencies"""
        url = 'https://pro-api.coinmarketcap.com/v1/cryptocurrency/listings/latest'
        params = {
            'start': '1',
            'limit': '100',
            'sort': 'date_added',
            'sort_dir': 'desc',
            'convert': 'USD'
        }
        
        try:
            response = requests.get(url, headers=headers, params=params)
            response.raise_for_status()
            data = response.json()
            
            one_day_ago = datetime.now() - timedelta(days=1)
            recent_coins = []
            
            for coin in data['data']:
                date_added = datetime.strptime(coin['date_added'], "%Y-%m-%dT%H:%M:%S.%fZ")
                volume = coin['quote']['USD']['volume_24h']
                
                if date_added > one_day_ago and volume > MIN_VOLUME:
                    recent_coins.append({
                        'symbol': coin['symbol'],
                        'name': coin['name'],
                        'price': coin['quote']['USD']['price'],
                        'volume_24h': volume,
                        'percent_change_1h': coin['quote']['USD']['percent_change_1h'],
                        'market_cap': coin['quote']['USD']['market_cap'],
                        'date_added': coin['date_added']
                    })
            
            return recent_coins
            
        except requests.exceptions.RequestException as e:
            print(f"API Error: {e}")
            return []

    def purge_and_add_coins(self, coins: List[Dict]):
        """Purge newcoins table and add new coins"""
        if not coins:
            return False
            
        try:
            # Drop foreign key constraint first
            drop_fk_cmd = f"""
            mysql -u root -p1304 -e \"
            ALTER TABLE price_history DROP FOREIGN KEY price_history_ibfk_1;
            \" {DB_NAME}
            """
            subprocess.run(drop_fk_cmd, shell=True, check=True)
            
            # Now truncate tables
            truncate_cmd = f"""
            mysql -u root -p1304 -e \"
            TRUNCATE TABLE price_history;
            TRUNCATE TABLE newcoins;
            \" {DB_NAME}
            """
            subprocess.run(truncate_cmd, shell=True, check=True)
            
            # Add new coins
            current_time = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
            sql_statements = []
            
            for coin in coins:
                date_added = coin['date_added'].replace('T', ' ').replace('.000Z', '')
                sql = f"""
                INSERT INTO newcoins 
                (symbol, name, price, volume_24h, percent_change_1h, market_cap, timestamp, date_added)
                VALUES ('{coin['symbol']}', '{coin['name'].replace("'", "''")}', 
                    {coin['price']}, {coin['volume_24h']}, {coin['percent_change_1h']}, 
                    {coin['market_cap']}, '{current_time}', '{date_added}');
                """
                sql_statements.append(sql)
            
            all_sql = ' '.join(sql_statements)
            mysql_cmd = f"mysql -u root -p1304 -e \"{all_sql}\" {DB_NAME}"
            subprocess.run(mysql_cmd, shell=True, check=True)
            
            # Recreate foreign key constraint
            add_fk_cmd = f"""
            mysql -u root -p1304 -e \"
            ALTER TABLE price_history 
            ADD CONSTRAINT price_history_ibfk_1 
            FOREIGN KEY (newcoin_id) REFERENCES newcoins(id) ON DELETE CASCADE;
            \" {DB_NAME}
            """
            subprocess.run(add_fk_cmd, shell=True, check=True)
            
        except subprocess.CalledProcessError as e:
            print(f"Error during purge/add operation: {e}")
            return False
            
        return True

    def record_trade(self, symbol: str, buy_price: float, quantity: float, status: str):
        """Record a trade in the database"""
        try:
            current_time = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
            sql = f"""
            INSERT INTO trades 
            (symbol, buy_price, quantity, buy_time, status)
            VALUES ('{symbol}', {buy_price}, {quantity}, '{current_time}', '{status}');
            """
            mysql_cmd = f"mysql -u root -p1304 -e \"{sql}\" {DB_NAME}"
            subprocess.run(mysql_cmd, shell=True, check=True)
        except subprocess.CalledProcessError as e:
            print(f"Error recording trade: {e}")

    def buy_coins(self, coins: List[Dict]):
        """Simulate buying coins with available wallet balance"""
        if not coins or not self.wallet_balance:
            return
            
        print(f"{self._get_timestamp()}: Buying {len(coins)} coins")
        
        per_coin_amount = self.wallet_balance / len(coins)
        
        for coin in coins:
            symbol = coin['symbol']
            buy_price = coin['price']
            quantity = per_coin_amount / buy_price
            
            self.current_holdings[symbol] = {
                'buy_price': buy_price,
                'quantity': quantity,
                'peak_price': buy_price,
                'last_decline_time': None
            }
            
            # Record trade in database
            self.record_trade(symbol, buy_price, quantity, 'open')
            print(f"{self._get_timestamp()}: Bought {coin['name']} ({symbol}) at ${buy_price:.6f}")
            
        self.wallet_balance = 0

    def update_price_history(self):
        """Update price history for all held coins"""
        if not self.current_holdings:
            return
            
        symbols = list(self.current_holdings.keys())
        url = 'https://pro-api.coinmarketcap.com/v1/cryptocurrency/quotes/latest'
        params = {'symbol': ','.join(symbols), 'convert': 'USD'}
        
        try:
            response = requests.get(url, headers=headers, params=params)
            response.raise_for_status()
            data = response.json()
            
            current_time = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
            sql_statements = []
            
            # Store previous prices for comparison
            if not hasattr(self, 'previous_prices'):
                self.previous_prices = {}
            
            # Set up table headers
            if not hasattr(self, 'table_initialized') or not self.table_initialized:
                self._print_table_header(symbols)
                self.table_initialized = True
            
            # Prepare row data
            row_data = {'timestamp': current_time}
            
            for symbol in symbols:
                if symbol in data['data']:
                    coin_data = data['data'][symbol]
                    current_price = coin_data['quote']['USD']['price']
                    
                    # Check if price changed
                    price_changed = True
                    price_direction = "same"
                    
                    if symbol in self.previous_prices:
                        if self.previous_prices[symbol] == current_price:
                            price_changed = False
                        elif current_price > self.previous_prices[symbol]:
                            price_direction = "up"
                        else:
                            price_direction = "down"
                    
                    # Store current price for next comparison
                    self.previous_prices[symbol] = current_price
                    
                    # Update price history in database
                    sql = f"""
                    INSERT INTO price_history (newcoin_id, price, timestamp)
                    SELECT id, {current_price}, '{current_time}'
                    FROM newcoins
                    WHERE symbol = '{symbol}';
                    """
                    sql_statements.append(sql)
                    
                    # Add to row data
                    row_data[symbol] = {
                        'price': current_price,
                        'changed': price_changed,
                        'direction': price_direction
                    }
                    
                    # Update holdings tracking
                    self.update_holding(symbol, current_price)
            
            # Print the table row
            self._print_table_row(row_data)
        
            if sql_statements:
                all_sql = ' '.join(sql_statements)
                mysql_cmd = f"mysql -u root -p1304 -e \"{all_sql}\" {DB_NAME}"
                subprocess.run(mysql_cmd, shell=True, check=True)
                
        except requests.exceptions.RequestException as e:
            print(f"{self._get_timestamp()}: Price update error: {e}")

    def _print_table_header(self, symbols):
        """Print the table header with coin symbols"""
        # ANSI color codes
        BLUE_BG = "\033[44m"
        YELLOW = "\033[33m"
        RESET = "\033[0m"
        
        # Set terminal to blue background and yellow text
        print(f"{BLUE_BG}{YELLOW}")
        
        # Print header row
        header = f"{'Timestamp':<19} | "
        for symbol in symbols:
            header += f"{symbol:<10} | "
        print(header)
        print("-" * (20 + (12 * len(symbols))))
    
    def _print_table_row(self, row_data):
        """Print a row in the table with color coding"""
        # ANSI color codes
        GREEN = "\033[32m"
        RED = "\033[31m"
        YELLOW = "\033[33m"
        PINK = "\033[95m"
        RESET = "\033[0m"
        
        timestamp = row_data['timestamp']
        row = f"{timestamp:<19} | "
        
        for symbol, data in row_data.items():
            if symbol == 'timestamp':
                continue
                
            if not data['changed']:
                row += f"{YELLOW}{'unchanged':<10}{YELLOW} | "
            else:
                price_str = f"${data['price']:.6f}"
                if data['direction'] == "up":
                    row += f"{GREEN}{price_str:<10}{YELLOW} | "
                elif data['direction'] == "down":
                    row += f"{RED}{price_str:<10}{YELLOW} | "
                else:
                    row += f"{YELLOW}{price_str:<10}{YELLOW} | "
        
        print(row)
    
    def update_holding(self, symbol: str, current_price: float):
        """Update holding information and check sell conditions"""
        holding = self.current_holdings[symbol]
        
        # Update peak price
        if current_price > holding['peak_price']:
            holding['peak_price'] = current_price
            holding['last_decline_time'] = None
        # Check if price is declining
        elif current_price < holding['peak_price']:
            if holding['last_decline_time'] is None:
                holding['last_decline_time'] = datetime.now()
            else:
                decline_duration = (datetime.now() - holding['last_decline_time']).total_seconds()
                if decline_duration >= DECLINE_DURATION_TO_SELL:
                    self.sell_coin(symbol, current_price)

    def sell_coin(self, symbol: str, sell_price: float):
        """Sell a coin and calculate profit"""
        if symbol not in self.current_holdings:
            return
            
        holding = self.current_holdings.pop(symbol)
        buy_price = holding['buy_price']
        quantity = holding['quantity']
        profit = (sell_price - buy_price) * quantity
        
        self.wallet_balance += sell_price * quantity
        
        # Update trade record
        sell_time = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
        update_sql = f"""
        UPDATE trades 
        SET sell_price = {sell_price}, 
            sell_time = '{sell_time}',
            profit = {profit},
            status = 'closed'
        WHERE symbol = '{symbol}' AND status = 'open';
        """
        mysql_cmd = f"mysql -u root -p1304 -e \"{update_sql}\" {DB_NAME}"
        subprocess.run(mysql_cmd, shell=True, check=True)
        
        print(f"{self._get_timestamp()}: Sold {symbol} at ${sell_price:.6f} (Buy: ${buy_price:.6f}, Profit: ${profit:.2f})")

    def run(self):
        """Main trading loop"""
        try:
            # Setup tables if not already done
            if not self.setup_complete:
                self.setup_tables()
                self.setup_complete = True
                print("Database tables setup complete")
            
            # Get recent coins
            coins = self.get_recent_cryptos()
            
            # Purge and add new coins
            self.purge_and_add_coins(coins)
            
            # Buy coins
            self.buy_coins(coins)
            
            # Set terminal colors
            print("\033[44m\033[33m")  # Blue background, yellow text
            
            # Monitor prices and sell when appropriate
            while self.current_holdings:
                self.update_price_history()
                time.sleep(PRICE_UPDATE_INTERVAL)
                
        except KeyboardInterrupt:
            print("\033[0m")  # Reset terminal colors
            print("\nStopping monitoring...")
            
        finally:
            # Reset terminal colors
            print("\033[0m")
            
            # Print final results
            print(f"\nFinal wallet balance: ${self.wallet_balance:.2f}")
            profit_loss = self.wallet_balance - WALLET_BALANCE
            print(f"Total profit/loss: ${profit_loss:.2f}")

if __name__ == "__main__":
    bot = TradingBot()
    bot.run()