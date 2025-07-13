import requests
from datetime import datetime, timedelta, timezone
import time
import subprocess
import sys
from typing import List, Dict, Optional, Tuple
from dateutil.parser import parse as dateparse  # More explicit import
from binance.client import Client
from binance.exceptions import BinanceAPIException

from config import (
    CMC_API_KEY,
    BINANCE_TEST_API_KEY,
    BINANCE_TEST_API_SECRET,
    TEST_MODE
)

# Configuration
DB_NAME = "Crypto_Stalker_py"
WALLET_BALANCE = 1000.00  # Starting balance in USD
MIN_VOLUME = 1_500_000  # Minimum 24h volume in USD
PRICE_UPDATE_INTERVAL = 3  # Seconds
DECLINE_DURATION_TO_SELL = 30  # Seconds of declining prices before selling

headers = {'X-CMC_PRO_API_KEY': CMC_API_KEY}

class TradingBot:
    def __init__(self, days: int = 1):
        self.current_holdings: Dict[str, Dict] = {}  # symbol: {buy_price, quantity, peak_price}
        self.wallet_balance = WALLET_BALANCE
        self.setup_complete = False
        self.days = days
        self.sells_since_header = 0  # Counter for sells since last header
    
    def _get_timestamp(self):
        """Helper method to get current timestamp in YYYY-MM-DD HH:MM:SS format"""
        return datetime.now().strftime('%Y-%m-%d %H:%M:%S')

    def setup_tables(self):
        """Create required database tables if they don't exist"""
        print(f"[{self._get_timestamp()}] [INFO] Starting database setup...")
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
            self.setup_complete = True
            print(f"[{self._get_timestamp()}] [SUCCESS] Database setup complete.")
            return True
        except subprocess.CalledProcessError as e:
            print(f"[{self._get_timestamp()}] [ERROR] Error setting up tables: {e}")
            raise

    def get_recent_cryptos(self) -> List[Dict]:
        """Fetch recent high-volume cryptocurrencies"""
        print(f"[{self._get_timestamp()}] [INFO] Fetching recent high-volume cryptocurrencies from API...")
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
            
            cutoff_time = datetime.now(timezone.utc) - timedelta(days=self.days)
            print(f"[DEBUG] cutoff_time (UTC, {self.days} days ago): {cutoff_time}")
            recent_coins = []
            
            for coin in data['data']:
                raw_date_added = coin['date_added']
                try:
                    date_added = dateparse(raw_date_added)
                    if date_added.tzinfo is None:
                        date_added = date_added.replace(tzinfo=timezone.utc)
                except Exception as e:
                    print(f"[WARNING] Could not parse date_added for {coin.get('symbol', '?')}: {raw_date_added} ({e})")
                    continue
                volume = coin['quote']['USD']['volume_24h']
                print(f"[DEBUG] {coin['symbol']} date_added: {date_added} (raw: {raw_date_added}), volume: {volume}")
                if date_added > cutoff_time and volume > MIN_VOLUME:
                    recent_coins.append({
                        'symbol': coin['symbol'],
                        'name': coin['name'],
                        'price': coin['quote']['USD']['price'],
                        'volume_24h': volume,
                        'percent_change_1h': coin['quote']['USD']['percent_change_1h'],
                        'market_cap': coin['quote']['USD']['market_cap'],
                        'date_added': coin['date_added']
                    })
            print(f"[{self._get_timestamp()}] [INFO] Fetched {len(recent_coins)} recent coins.")
            if not recent_coins:
                print(f"[{self._get_timestamp()}] [WARNING] No recent coins found matching criteria.")
            return recent_coins
            
        except requests.exceptions.RequestException as e:
            print(f"[{self._get_timestamp()}] [ERROR] API Error: {e}")
            return []

    def purge_and_add_coins(self, coins: List[Dict]):
        """Purge newcoins table and add new coins"""
        if not coins:
            return False
            
        try:
            # Drop foreign key constraint first
            drop_fk_cmd = f"""
            mysql -u root -p1304 -e "
            ALTER TABLE price_history DROP FOREIGN KEY price_history_ibfk_1;
            " {DB_NAME}
            """
            subprocess.run(drop_fk_cmd, shell=True, check=True)
            
            # Now truncate tables
            truncate_cmd = f"""
            mysql -u root -p1304 -e "
            TRUNCATE TABLE price_history;
            TRUNCATE TABLE newcoins;
            " {DB_NAME}
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
            mysql -u root -p1304 -e "
            ALTER TABLE price_history 
            ADD CONSTRAINT price_history_ibfk_1 
            FOREIGN KEY (newcoin_id) REFERENCES newcoins(id) ON DELETE CASCADE;
            " {DB_NAME}
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
            print(f"[{self._get_timestamp()}] [INFO] Recorded trade for {symbol}.")
        except subprocess.CalledProcessError as e:
            print(f"[{self._get_timestamp()}] [ERROR] Error recording trade: {e}")

    def buy_coins(self, coins: List[Dict]):
        """Simulate buying coins with available wallet balance"""
        print(f"[{self._get_timestamp()}] [INFO] Attempting to buy coins...")
        if not coins or not self.wallet_balance:
            print(f"[{self._get_timestamp()}] [WARNING] No coins to buy or wallet balance is zero.")
            return
        
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
            print(f"[{self._get_timestamp()}] [TRADE] Bought {symbol} at ${buy_price:.6f}")
        print(f"[{self._get_timestamp()}] [INFO] Finished buying coins. Holdings: {list(self.current_holdings.keys())}")
        self.wallet_balance = 0

    def update_price_history(self):
        """Update price history for all held coins"""
        if not self.current_holdings:
            print(f"[{self._get_timestamp()}] [WARNING] No coins to update price history for.")
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
        NAVY_BG = "\033[48;5;18m"  # Navy blue background
        BRIGHT_YELLOW = "\033[38;5;226;1m"  # Bright yellow bold text
        BLUE = "\033[34m"  # Blue for dotted lines
        RESET = "\033[0m"
        
        # Set terminal to navy blue background and bright yellow bold text
        print(f"{NAVY_BG}{BRIGHT_YELLOW}")
        
        # Get terminal width
        try:
            import os
            terminal_width = os.get_terminal_size().columns
        except (ImportError, OSError):
            terminal_width = 100  # Default if can't determine
            
        # Calculate column widths
        num_columns = len(symbols) + 1  # +1 for timestamp
        separators_space = 3 * num_columns  # Each '| ' takes 2 chars, plus one at end
        available_width = terminal_width - separators_space
        timestamp_width = 19
        remaining_width = available_width - timestamp_width
        
        if len(symbols) > 0:
            symbol_width = max(10, remaining_width // len(symbols))
        else:
            symbol_width = 10
            
        self.column_width = symbol_width
        self.timestamp_width = timestamp_width
        
        # Print header row
        header = f"{'Timestamp':<{timestamp_width}} | "
        for symbol in symbols:
            header += f"{symbol:^{symbol_width}} | "
        
        # Create dotted separator line
        separator = "." * terminal_width
        
        print(f"{BLUE}{separator}{BRIGHT_YELLOW}")
        print(header)
        print(f"{BLUE}{separator}{BRIGHT_YELLOW}")
        
    def _print_table_row(self, row_data):
        """Print a row in the table with color coding"""
        # ANSI color codes
        GREEN = "\033[38;5;46m"  # Bright green
        BRIGHT_RED = "\033[38;5;196m"  # Bright red
        BRIGHT_YELLOW = "\033[38;5;226;1m"  # Bright yellow bold
        BRIGHT_FUCHSIA = "\033[38;5;201m"  # Bright fuchsia
        RESET = "\033[0m"
    
        timestamp = row_data['timestamp']
        row = f"{timestamp:<19} | "
        sql_statements = []  # Initialize sql_statements list
    
        for symbol, data in row_data.items():
            if symbol == 'timestamp':
                continue
            
            if not data['changed']:
                # Empty spaces instead of 'unchanged'
                row += f"{BRIGHT_YELLOW}{' ':<{self.column_width}}{BRIGHT_YELLOW} | "
            else:
                price_str = f"${data['price']:.6f}"
                if data['direction'] == "up":
                    row += f"{GREEN}{price_str:<{self.column_width}}{BRIGHT_YELLOW} | "
                elif data['direction'] == "down":
                    row += f"{BRIGHT_RED}{price_str:<{self.column_width}}{BRIGHT_YELLOW} | "
                else:
                    row += f"{BRIGHT_YELLOW}{price_str:<{self.column_width}}{BRIGHT_YELLOW} | "
    
        print(row)
    
        if sql_statements:
            all_sql = ' '.join(sql_statements)
            mysql_cmd = f"mysql -u root -p1304 -e \"{all_sql}\" {DB_NAME}"
            subprocess.run(mysql_cmd, shell=True, check=True)

    def run(self):
        """Main bot logic"""
        if not self.setup_complete:
            print(f"[{self._get_timestamp()}] [ERROR] Setup not complete. Exiting.")
            return
        
        print(f"[{self._get_timestamp()}] [INFO] Starting bot. Monitoring new cryptocurrencies...")
        
        try:
            # Initial fetch and purge/add
            recent_coins = self.get_recent_cryptos()
            self.purge_and_add_coins(recent_coins)
            
            # Buy new coins
            self.buy_coins(recent_coins)
            
            # Monitor prices and sell when appropriate
            while self.current_holdings:
                self.update_price_history()
                time.sleep(PRICE_UPDATE_INTERVAL)
            print(f"[{self._get_timestamp()}] [INFO] Monitoring loop exited (no more holdings).")
        except KeyboardInterrupt:
            print("\033[0m")  # Reset terminal colors
            print(f"[{self._get_timestamp()}] [INFO] Stopping monitoring due to KeyboardInterrupt.")
        except Exception as e:
            import traceback
            print(f"[{self._get_timestamp()}] [CRITICAL] Unexpected error: {e}")
            traceback.print_exc()
        finally:
            # Reset terminal colors
            print("\033[0m")
            
            # Print final results
            print(f"\nFinal wallet balance: ${self.wallet_balance:.2f}")
            profit_loss = self.wallet_balance - WALLET_BALANCE
            print(f"Total profit/loss: ${profit_loss:.2f}")

    def update_holding(self, symbol: str, current_price: float):
        """Update holding information and check sell conditions"""
        holding = self.current_holdings[symbol]
        buy_price = holding['buy_price']
        
        # Update peak price
        if current_price > holding['peak_price']:
            holding['peak_price'] = current_price
            # Only reset decline time if price is also above buy price
            if current_price >= buy_price:
                holding['last_decline_time'] = None
        
        # Check if price is below buy price or declining from peak
        if current_price < buy_price or current_price < holding['peak_price']:
            if holding['last_decline_time'] is None:
                holding['last_decline_time'] = datetime.now()
            else:
                decline_duration = (datetime.now() - holding['last_decline_time']).total_seconds()
                if decline_duration >= DECLINE_DURATION_TO_SELL:
                    self.sell_coin(symbol, current_price)

    def sell_coin(self, symbol: str, current_price: float):
        """Sell a coin and update the wallet balance"""
        if symbol not in self.current_holdings:
            return
        
        holding = self.current_holdings[symbol]
        quantity = holding['quantity']
        buy_price = holding['buy_price']
        
        # Calculate proceeds and profit/loss
        proceeds = quantity * current_price
        profit_loss = proceeds - (quantity * buy_price)
        
        # Update wallet balance
        self.wallet_balance += proceeds
        
        # Update trade record in database
        try:
            sql = f"""
            UPDATE trades 
            SET sell_price = {current_price},
                sell_time = '{datetime.now().strftime('%Y-%m-%d %H:%M:%S')}',
                profit = {profit_loss},
                status = 'closed'
            WHERE symbol = '{symbol}' AND status = 'open';
            """
            mysql_cmd = f"mysql -u root -p1304 -e \"{sql}\" {DB_NAME}"
            subprocess.run(mysql_cmd, shell=True, check=True)
        except subprocess.CalledProcessError as e:
            print(f"[{self._get_timestamp()}] [ERROR] Error updating trade record: {e}")
        
        # Print trade information
        print(f"[{self._get_timestamp()}] [TRADE] Sold {symbol}")
        print(f"  Buy price : ${buy_price:.6f}")
        print(f"  Sell price: ${current_price:.6f}")
        print(f"  Profit/Loss: ${profit_loss:.2f}")
        print(f"  Current wallet balance: ${self.wallet_balance:.2f}")
        
        # Remove from holdings
        del self.current_holdings[symbol]
        
        # Force table header reprint with remaining coins
        if self.current_holdings:
            print("\n")  # Add some space
            self._print_table_header(list(self.current_holdings.keys()))
        else:
            self.table_initialized = False
            print("\n" + "="*50)
            print("All positions closed. Waiting for new opportunities...")
            print("="*50 + "\n")

class RiskManager:
    def __init__(self):
        self.max_position_size = 0.1  # Max 10% of portfolio per trade
        self.stop_loss_percentage = 0.02  # 2% stop loss
        self.take_profit_percentage = 0.05  # 5% take profit
        
    def calculate_position_size(self, total_capital: float):
        return total_capital * self.max_position_size

class WalletError(Exception):
    """Base exception class for Wallet errors"""
    pass

class InsufficientFundsError(WalletError):
    """Raised when wallet has insufficient funds"""
    pass

class ExchangeConnectionError(WalletError):
    """Raised when exchange connection fails"""
    pass

class ValidationError(WalletError):
    """Raised when trade validation fails"""
    pass

class Wallet:
    def __init__(self, exchange_client):
        self.exchange = exchange_client
        self.balances: Dict[str, float] = {}
        self.reserved_funds: Dict[str, float] = {}
        self._last_update = None
        self.update_interval = 60  # Update balance cache every 60 seconds
        
    def get_balance(self, currency: str = 'USD') -> float:
        """
        Get current balance for specified currency with error handling
        """
        try:
            # Check if we need to update cached balance
            if self._should_update_balance():
                self.update_balance()
                
            available = self.balances.get(currency, 0.0)
            reserved = self.reserved_funds.get(currency, 0.0)
            return available - reserved
            
        except BinanceAPIException as e:
            raise ExchangeConnectionError(f"Failed to get balance: {e}")
        except Exception as e:
            raise WalletError(f"Unexpected error getting balance: {e}")
    
    def can_execute_trade(self, symbol: str, amount: float) -> Tuple[bool, Optional[str]]:
        """
        Validate if a trade can be executed with detailed error reporting
        Returns: (can_execute: bool, error_message: Optional[str])
        """
        try:
            # Get current balance with error handling
            balance = self.get_balance()
            
            # Get trading fees
            try:
                fee_percentage = self.exchange.get_trading_fee(symbol)
            except BinanceAPIException as e:
                return False, f"Failed to get trading fees: {e}"
                
            total_with_fees = amount * (1 + fee_percentage)
            
            # Check sufficient funds
            if total_with_fees > balance:
                raise InsufficientFundsError(
                    f"Insufficient funds: {balance:.2f} available, {total_with_fees:.2f} required"
                )
            
            # Validate symbol and get trading rules
            try:
                symbol_info = self.exchange.get_symbol_info(symbol)
                if not symbol_info:
                    return False, f"Trading pair {symbol} not available"
                    
                # Check minimum trade amount
                if amount < symbol_info['min_amount']:
                    return False, f"Amount {amount} below minimum {symbol_info['min_amount']}"
                    
                # Check maximum trade amount
                if amount > symbol_info['max_amount']:
                    return False, f"Amount {amount} above maximum {symbol_info['max_amount']}"
            except BinanceAPIException as e:
                return False, f"Failed to validate trading rules: {e}"
        
            # All validations passed
            return True, None
            
        except InsufficientFundsError as e:
            return False, str(e)
        except BinanceAPIException as e:
            return False, f"Exchange error: {e}"
        except Exception as e:
            return False, f"Validation error: {e}"

# Add this at the bottom of the file:
if __name__ == "__main__":
    try:
        days_input = input("How old (in days) should the coins be? [default: 1]: ").strip()
        days = int(days_input) if days_input else 1
        
        bot = TradingBot(days=days)
        bot.setup_tables()  # This line is critical!
        bot.run()
    except KeyboardInterrupt:
        print("\nExiting due to user interrupt...")
    except Exception as e:
        print(f"Error: {e}")
    finally:
        print("\033[0m")  # Reset terminal colors
