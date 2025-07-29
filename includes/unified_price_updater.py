import json
import time
import mysql.connector # type: ignore
import requests
import logging
import signal
import sys
from datetime import datetime, timedelta
BITVAVO_API_KEY='ce59283de845c416deef1dd91f10c3879f0554e18c938dc9170550cebfcfbe37'
BITVAVO_API_SECRET='28de1f1699a1bc9845a132e91dfa888801d7437d297e419521f6b9bbce670c88ea3a937b6f5c09421573340b5cc75f98edb05cd3ca19a79ddcc820e43b20c29b'
BINANCE_API_KEY='X8HpKiRKv6fNCulGEV2ReFpgyeS4wT0SWgokopvObB6ICUADi5nOEUZNFbcWUP9I'
BINANCE_API_SECRET='qeJ3x3SByFxFepLXrBqkWkSYijPt2DjvNA1MVA7fykgOqgUw6Jrb0Cmmvm7DWqWs'
BINANCE_API_URL='https://api.binance.com'
BITVAVO_API_URL='https://api.bitvavo.com/v2/order'


# For clean exit
running = True

# PHP API Endpoints
SELL_API_URL = "http://localhost/NS/api/execute-sell.php"
BUY_API_URL = "http://localhost/NS/api/execute-buy.php"

def signal_handler(sig, frame):
    global running
    script_logger.info("Shutting down gracefully...")
    script_logger.info("Unified price update script stopped.")
    running = False
    sys.exit(0)

signal.signal(signal.SIGINT, signal_handler)

# --- Logger Setup ---
script_logger = logging.getLogger('unified_price_updater')
script_logger.setLevel(logging.INFO)
script_handler = logging.FileHandler('/opt/lampp/htdocs/NS/logs/unified_price_updater.log')
script_formatter = logging.Formatter('%(asctime)s - %(levelname)s - %(message)s')
script_handler.setFormatter(script_formatter)
script_logger.addHandler(script_handler)

price_logger = logging.getLogger('price_updates')
price_logger.setLevel(logging.INFO)
price_handler = logging.FileHandler('/opt/lampp/htdocs/NS/logs/price_updates_terminal.log')
price_formatter = logging.Formatter('%(asctime)s - %(message)s')
price_handler.setFormatter(price_formatter)
price_logger.addHandler(price_handler)

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

def execute_sell_api(coin_id, amount, price):
    """Calls the PHP API endpoint to execute a sell order."""
    payload = {
        'coin_id': coin_id,
        'amount': str(amount),
        'price': str(price)
    }
    try:
        script_logger.info(f"Attempting to sell {amount} of {coin_id} at {price} via API...")
        response = requests.post(SELL_API_URL, json=payload)
        response.raise_for_status()
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

def execute_buy_api(coin_id, amount, price):
    """Calls the PHP API endpoint to execute a buy order."""
    payload = {
        'coin_id': coin_id,
        'amount': str(amount),
        'price': str(price)
    }
    try:
        script_logger.info(f"Attempting to buy {amount} of {coin_id} at {price} via API...")
        response = requests.post(BUY_API_URL, json=payload)
        response.raise_for_status()
        result = response.json()
        if result.get('success'):
            script_logger.info(f"Buy API Success for {coin_id}: {result.get('message')}")
            return True
        else:
            script_logger.error(f"Buy API Failed for {coin_id}: {result.get('message', 'Unknown error')}")
            return False
    except requests.exceptions.RequestException as e:
        script_logger.error(f"Error calling buy API for {coin_id}: {e}")
        return False
    except json.JSONDecodeError:
        script_logger.error(f"Failed to decode JSON response from buy API for {coin_id}. Response: {response.text}")
        return False
    except Exception as e:
        script_logger.error(f"An unexpected error occurred during buy API call for {coin_id}: {e}")
        return False

# Placeholder for Bitvavo API key/secret - will need to be loaded from config
BITVAVO_API_KEY='ce59283de845c416deef1dd91f10c3879f0554e18c938dc9170550cebfcfbe37'
BITVAVO_API_SECRET='28de1f1699a1bc9845a132e91dfa888801d7437d297e419521f6b9bbce670c88ea3a937b6f5c09421573340b5cc75f98edb05cd3ca19a79ddcc820e43b20c29b'

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

# --- Exchange-specific price fetching functions ---
def fetch_binance_prices(symbols):
    """Fetches current prices for given symbols from Binance API."""
    prices = {}
    try:
        url = "https://api.binance.com/api/v3/ticker/price"
        response = requests.get(url)
        response.raise_for_status()
        data = response.json()
        price_map = {item['symbol']: item['price'] for item in data}
        for symbol in symbols:
            if symbol in price_map:
                prices[symbol] = float(price_map[symbol])
    except Exception as e:
        script_logger.error(f"Error fetching prices from Binance API: {e}")
    return prices

def fetch_bitvavo_prices(symbols_to_fetch):
    """Fetches current prices for given symbols from Bitvavo API."""
    from python_bitvavo_api.bitvavo import Bitvavo
    prices = {}
    try:
        bitvavo_engine = Bitvavo({
            'APIKEY': BITVAVO_API_KEY,
            'APISECRET': BITVAVO_API_SECRET
        })
        response = bitvavo_engine.ticker24h({})
        for item in response:
            market = item.get('market')
            bid_price = item.get('bid')
            if market and bid_price is not None:
                # Extract base coin symbol from market (e.g., BTC-EUR -> BTC)
                base_coin_symbol = market.split('-')[0]
                if base_coin_symbol in [s.split('-')[0] for s in symbols_to_fetch]:
                    prices[base_coin_symbol] = float(bid_price)
    except Exception as e:
        script_logger.error(f"Error fetching prices from Bitvavo API: {e}")
    return prices

# --- Main price update logic ---
def unified_price_update_loop():
    connection = None
    try:
        connection = get_db_connection()
        cursor = connection.cursor()

        # Fetch coins from portfolio along with their associated exchange_id from the coins table
        # Assuming portfolio.coin_id matches coins.symbol or coins.id
        cursor.execute("""
            SELECT p.coin_id, c.exchange_id 
            FROM portfolio p
            JOIN coins c ON p.coin_id = c.symbol OR p.coin_id = c.id
            WHERE p.amount > 0
        """)
        portfolio_coins = cursor.fetchall()

        if not portfolio_coins:
            script_logger.info("No active portfolio coins found. Clearing price_history table.")
            try:
                connection2 = get_db_connection()
                cursor2 = connection2.cursor()
                cursor2.execute("DELETE FROM price_history")
                connection2.commit()
                cursor2.close()
                connection2.close()
                script_logger.info("price_history table cleared successfully.")
            except Exception as e:
                script_logger.error(f"Failed to clear price_history table: {e}")
            return False # Return False to indicate no coins were processed

        binance_symbols_to_track = []
        bitvavo_symbols_to_track = []
        coin_exchange_map = {}

        for coin_id, exchange_id in portfolio_coins:
            # Assuming coin_id from portfolio is the base symbol (e.g., BTC, SAHARA)
            if exchange_id == 2: # Binance
                binance_symbols_to_track.append(f"{coin_id.upper()}USDT")
                coin_exchange_map[f"{coin_id.upper()}USDT"] = coin_id # Map full symbol back to base coin_id
            elif exchange_id == 1: # Bitvavo
                bitvavo_symbols_to_track.append(f"{coin_id.upper()}-EUR")
                coin_exchange_map[f"{coin_id.upper()}-EUR"] = coin_id # Map full symbol back to base coin_id
            else:
                script_logger.warning(f"Unknown exchange_id {exchange_id} for coin {coin_id}. Skipping.")

        all_fetched_prices = {}

        # Fetch Binance prices
        if binance_symbols_to_track:
            script_logger.info(f"Fetching Binance prices for: {binance_symbols_to_track}")
            binance_prices = fetch_binance_prices(binance_symbols_to_track)
            all_fetched_prices.update(binance_prices)

        # Fetch Bitvavo prices
        if bitvavo_symbols_to_track:
            script_logger.info(f"Fetching Bitvavo prices for: {bitvavo_symbols_to_track}")
            bitvavo_prices = fetch_bitvavo_prices(bitvavo_symbols_to_track)
            all_fetched_prices.update(bitvavo_prices)

        current_time = datetime.now()

        for full_symbol, base_coin_id in coin_exchange_map.items():
            price = None
            if full_symbol in all_fetched_prices: # For Binance symbols (e.g., BTCUSDT)
                price = all_fetched_prices[full_symbol]
            elif base_coin_id in all_fetched_prices: # For Bitvavo symbols (e.g., USDC)
                price = all_fetched_prices[base_coin_id]

            if price is not None:
                price_logger.info(f"{full_symbol}:{price}")
                print(f"{full_symbol}:{price}") # Terminal output

                insert_query = "INSERT INTO price_history (coin_id, price, recorded_at) VALUES (%s, %s, %s)"
                cursor.execute(insert_query, (base_coin_id, price, current_time))
                connection.commit()
                script_logger.info(f"Successfully inserted price for {base_coin_id}: {price}")

                # --- Apex Tracking Logic ---
                apex_data = get_apex_data(base_coin_id)

                apex_data = get_apex_data(base_coin_id)
                script_logger.info(f"Retrieved apex data for {base_coin_id}: {apex_data}")

                if apex_data:
                    apex_price = float(apex_data['apex_price'])
                    drop_start_timestamp = apex_data['drop_start_timestamp']
                    status = apex_data['status']

                    

                    if price > apex_price:
                        script_logger.info(f"New apex for {base_coin_id}: {price} (old: {apex_price})")
                        update_apex_data(base_coin_id, price, current_time, None, 'monitoring')
                    elif price < apex_price:
                        if status == 'monitoring':
                            script_logger.info(f"Price for {base_coin_id} dropped below apex. Starting drop timer.")
                            update_apex_data(base_coin_id, apex_price, apex_data['apex_timestamp'], current_time, 'dropping')
                        elif status == 'dropping':
                            if drop_start_timestamp and (current_time - drop_start_timestamp).total_seconds() >= 30:
                                script_logger.info(f"Price for {base_coin_id} has been below apex for >= 30 seconds. Initiating sell.")
                                amount_to_sell = get_coin_amount_in_portfolio(base_coin_id)
                                if amount_to_sell > 0:
                                    if execute_sell_api(base_coin_id, amount_to_sell, price):
                                        script_logger.info(f"Successfully sold {amount_to_sell} of {base_coin_id}.")
                                        update_apex_data(base_coin_id, apex_price, apex_data['apex_timestamp'], drop_start_timestamp, 'sold')
                                    else:
                                        script_logger.error(f"Failed to sell {base_coin_id}. Will continue monitoring.")
                                else:
                                    script_logger.warning(f"No {base_coin_id} found in portfolio to sell. Marking as sold to stop monitoring.")
                                    update_apex_data(base_coin_id, apex_price, apex_data['apex_timestamp'], drop_start_timestamp, 'sold')
                    elif price >= apex_price and status == 'dropping':
                        script_logger.info(f"Price for {base_coin_id} recovered to or above apex. Resetting drop timer.")
                        update_apex_data(base_coin_id, apex_price, apex_data['apex_timestamp'], None, 'monitoring')
                else:
                    script_logger.info(f"Initializing apex for {base_coin_id} with current price: {price}")
                    update_apex_data(base_coin_id, price, current_time, None, 'monitoring')
            else:
                script_logger.warning(f"Price not found for {full_symbol}. Skipping insertion and apex tracking.")

        return True # Return True to indicate coins were processed
    except mysql.connector.Error as err:
        script_logger.error(f"Database error in unified_price_update_loop: {err}")
    except Exception as e:
        script_logger.error(f"An unexpected error occurred in unified_price_update_loop: {e}")
        return False
    finally:
        if connection:
            connection.close()

if __name__ == '__main__':
    script_logger.info("Bitvavo - Binance price update script started.")
    script_logger.handlers[0].flush()
    script_logger.info("--- Script starting ---")
    while running:
       if not unified_price_update_loop():
            timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            script_logger.info(f"No active portfolio items found. Script stopped at {timestamp}.")
            break
       time.sleep(3)
    script_logger.info("--- Script finished ---")
    