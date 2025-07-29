import json
import time
import mysql.connector # type: ignore
from python_bitvavo_api.bitvavo import Bitvavo # type: ignore
import logging
import signal
import sys
import requests # Import the requests library
from datetime import datetime, timedelta
import subprocess # Import the subprocess module

# For clean exit
running = True

# PHP Sell API Endpoint
SELL_API_URL = "http://localhost/NS/api/execute-sell.php"

def signal_handler(sig, frame):
    global running
    script_logger.info("Shutting down gracefully...")
    script_logger.info("Backend price update script stopped.")
    running = False
    sys.exit(0)

signal.signal(signal.SIGINT, signal_handler)

# --- Logger Setup ---
# General logger for script operations
script_logger = logging.getLogger('bitvavo_script')
script_logger.setLevel(logging.INFO)
script_handler = logging.FileHandler('/opt/lampp/htdocs/NS/logs/bitvavo_script.log')
script_formatter = logging.Formatter('%(asctime)s - %(levelname)s - %(message)s')
script_handler.setFormatter(script_formatter)
script_logger.addHandler(script_handler)

# Dedicated logger for price updates
price_logger = logging.getLogger('price_updates')
price_logger.setLevel(logging.INFO)
price_handler = logging.FileHandler('/opt/lampp/htdocs/NS/logs/price_updates.log')
price_formatter = logging.Formatter('%(asctime)s - %(message)s')
price_handler.setFormatter(price_formatter)
price_logger.addHandler(price_handler)


BITVAVO_API_KEY='ce59283de845c416deef1dd91f10c3879f0554e18c938dc9170550cebfcfbe37';
BITVAVO_API_SECRET='28de1f1699a1bc9845a132e91dfa888801d7437d297e419521f6b9bbce670c88ea3a937d297e419521f6b9bbce670c88ea3a937b6f5c09421573340b5cc75f98edb05cd3ca19a79ddcc820e43b20c29b'

def get_db_connection():
    """Establishes and returns a database connection."""
    return mysql.connector.connect(
        unix_socket="/opt/lampp/var/mysql/mysql.sock",
        host="127.0.0.1",
        user="root",
        password="1304",
        database="NS",
        port=3307
    )

def get_portfolio_symbols():
    """Fetches coin symbols from the portfolio in the database."""
    try:
        connection = get_db_connection()
        cursor = connection.cursor()
        # Only select coins that have an amount > 0 in the portfolio
        cursor.execute("SELECT coin_id FROM portfolio WHERE amount > 0")
        symbols = cursor.fetchall()
        cursor.close()
        connection.close()
        return [symbol[0].upper() for symbol in symbols]
    except mysql.connector.Error as err:
        script_logger.error(f"Database error in get_portfolio_symbols: {err}")
        return []
    except Exception as e:
        script_logger.error(f"An unexpected error occurred in get_portfolio_symbols: {e}")
        return []

def get_coin_amount_in_portfolio(coin_id):
    """Fetches the amount of a specific coin in the portfolio."""
    try:
        connection = get_db_connection()
        cursor = connection.cursor()
        cursor.execute("SELECT amount FROM portfolio WHERE coin_id = %s", (coin_id,))
        result = cursor.fetchone()
        cursor.close()
        connection.close()
        return float(result[0]) if result else 0.0
    except mysql.connector.Error as err:
        script_logger.error(f"Database error in get_coin_amount_in_portfolio for {coin_id}: {err}")
        return 0.0
    except Exception as e:
        script_logger.error(f"An unexpected error occurred in get_coin_amount_in_portfolio for {coin_id}: {e}")
        return 0.0

def get_apex_data(coin_id):
    """Retrieves apex data for a given coin from the coin_apex_prices table."""
    try:
        connection = get_db_connection()
        cursor = connection.cursor(dictionary=True)
        cursor.execute("SELECT * FROM coin_apex_prices WHERE coin_id = %s", (coin_id,))
        data = cursor.fetchone()
        cursor.close()
        connection.close()
        return data
    except mysql.connector.Error as err:
        script_logger.error(f"Database error in get_apex_data for {coin_id}: {err}")
        return None
    except Exception as e:
        script_logger.error(f"An unexpected error occurred in get_apex_data for {coin_id}: {e}")
        return None

def update_apex_data(coin_id, apex_price, apex_timestamp, drop_start_timestamp=None, status='monitoring'):
    """Inserts or updates apex data for a coin in the coin_apex_prices table."""
    try:
        connection = get_db_connection()
        cursor = connection.cursor()
        query = """
            INSERT INTO coin_apex_prices (coin_id, apex_price, apex_timestamp, drop_start_timestamp, status, last_checked)
            VALUES (%s, %s, %s, %s, %s, NOW())
            ON DUPLICATE KEY UPDATE
            apex_price = VALUES(apex_price),
            apex_timestamp = VALUES(apex_timestamp),
            drop_start_timestamp = VALUES(drop_start_timestamp),
            status = VALUES(status),
            last_checked = NOW()
        """
        cursor.execute(query, (coin_id, apex_price, apex_timestamp, drop_start_timestamp, status))
        connection.commit()
        cursor.close()
        connection.close()
        return True
    except mysql.connector.Error as err:
        script_logger.error(f"Database error in update_apex_data for {coin_id}: {err}")
        return False
    except Exception as e:
        script_logger.error(f"An unexpected error occurred in update_apex_data for {coin_id}: {e}")
        return False

def execute_sell_api(coin_id, amount, price):
    """Calls the PHP API endpoint to execute a sell order."""
    payload = {
        'coin_id': coin_id,
        'amount': str(amount), # Ensure amount is sent as string for PHP float parsing
        'price': str(price)   # Ensure price is sent as string for PHP float parsing
    }
    try:
        script_logger.info(f"Attempting to sell {amount} of {coin_id} at {price} via API...")
        response = requests.post(SELL_API_URL, json=payload)
        response.raise_for_status() # Raise an exception for HTTP errors (4xx or 5xx)
        result = response.json()
        if result.get('success'):
            script_logger.info(f"Sell API Success for {coin_id}: {result.get('message')}")
            return True
        else:
            script_logger.error(f"Sell API Failed for {coin_id}: {result.get('message', 'Unknown error')}")
            return False
    except requests.exceptions.RequestException as e:
        script_logger.error(f"Error calling sell API for {coin_id}: {e}")
        return False
    except json.JSONDecodeError:
        script_logger.error(f"Failed to decode JSON response from sell API for {coin_id}. Response: {response.text}")
        return False
    except Exception as e:
        script_logger.error(f"An unexpected error occurred during sell API call for {coin_id}: {e}")
        return False

class BitvavoImplementation:
    api_key = BITVAVO_API_KEY
    api_secret = BITVAVO_API_SECRET
    bitvavo_engine = None

    def __init__(self):
        self.api_key = BITVAVO_API_KEY
        self.api_secret = BITVAVO_API_SECRET
        self.bitvavo_engine = Bitvavo({
            'APIKEY': self.api_key,
            'APISECRET': self.api_secret
        })

    def log_and_save_portfolio_prices(self):
        symbols_to_track = get_portfolio_symbols()
        if not symbols_to_track:
            script_logger.warning("No symbols to track from portfolio or all coins sold.")
            return False

        response = self.bitvavo_engine.ticker24h({})
        prices = {item['market']: float(item['bid']) for item in response if 'bid' in item}

        current_time = datetime.now()

        try:
            connection = get_db_connection()
            cursor = connection.cursor()

            for symbol_eur in symbols_to_track:
                symbol = symbol_eur.replace('-EUR', '')
                current_price = prices.get(symbol_eur)

                if current_price is None:
                    script_logger.warning(f"Price not found for {symbol_eur}. Skipping.")
                    continue
                
                price_logger.info(f"{symbol_eur}:{current_price}")

                # Insert into price_history
                insert_query = "INSERT INTO price_history (coin_id, price, recorded_at) VALUES (%s, %s, %s)"
                cursor.execute(insert_query, (symbol, current_price, current_time))
                connection.commit()
                script_logger.info(f"Successfully inserted price for {symbol}: {current_price}")

                # --- Apex Tracking Logic ---
                apex_data = get_apex_data(symbol)

                if apex_data:
                    apex_price = float(apex_data['apex_price'])
                    drop_start_timestamp = apex_data['drop_start_timestamp']
                    status = apex_data['status']

                    if status == 'sold':
                        # If already sold, skip tracking for this coin
                        continue

                    if current_price > apex_price:
                        # New apex found
                        script_logger.info(f"New apex for {symbol}: {current_price} (old: {apex_price})")
                        update_apex_data(symbol, current_price, current_time, None, 'monitoring')
                    elif current_price < apex_price:
                        # Price is below apex
                        if status == 'monitoring':
                            # First time price dropped below apex
                            script_logger.info(f"Price for {symbol} dropped below apex. Starting drop timer.")
                            update_apex_data(symbol, apex_price, apex_data['apex_timestamp'], current_time, 'dropping')
                        elif status == 'dropping':
                            # Still dropping, check duration
                            if drop_start_timestamp and (current_time - drop_start_timestamp).total_seconds() >= 30:
                                script_logger.info(f"Price for {symbol} has been below apex for >= 30 seconds. Initiating sell.")
                                # Trigger sell
                                amount_to_sell = get_coin_amount_in_portfolio(symbol) # Get current amount from portfolio
                                if amount_to_sell > 0:
                                    if execute_sell_api(symbol, amount_to_sell, current_price):
                                        script_logger.info(f"Successfully sold {amount_to_sell} of {symbol}.")
                                        update_apex_data(symbol, apex_price, apex_data['apex_timestamp'], drop_start_timestamp, 'sold')
                                    else:
                                        script_logger.error(f"Failed to sell {symbol}. Will continue monitoring.")
                                else:
                                    script_logger.warning(f"No {symbol} found in portfolio to sell. Marking as sold to stop monitoring.")
                                    update_apex_data(symbol, apex_price, apex_data['apex_timestamp'], drop_start_timestamp, 'sold')
                    # If price recovers to apex or above, reset status
                    elif current_price >= apex_price and status == 'dropping':
                        script_logger.info(f"Price for {symbol} recovered to or above apex. Resetting drop timer.")
                        update_apex_data(symbol, apex_price, apex_data['apex_timestamp'], None, 'monitoring')
                else:
                    # No apex data, insert current price as initial apex
                    script_logger.info(f"Initializing apex for {symbol} with current price: {current_price}")
                    update_apex_data(symbol, current_price, current_time, None, 'monitoring')

            cursor.close()
            connection.close()

        except mysql.connector.Error as err:
            script_logger.error(f"Database error during price insertion or apex tracking: {err}")
        except Exception as e:
            script_logger.error(f"An error occurred during database operation or apex tracking: {e}")
        
        return True

if __name__ == '__main__':
    script_logger.info("Backend price update script started.")
    script_logger.handlers[0].flush() # Force flush the log immediately
    script_logger.info("--- Script starting ---")
    bvavo = BitvavoImplementation()
    while running:
        if not bvavo.log_and_save_portfolio_prices():
            script_logger.info("No portfolio coins found or all active coins sold. Shutting down.")
            break
        time.sleep(3)
    script_logger.info("--- Script finished ---")