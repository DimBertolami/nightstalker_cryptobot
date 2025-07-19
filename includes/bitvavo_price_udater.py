import json
import time
import mysql.connector # type: ignore
from python_bitvavo_api.bitvavo import Bitvavo # type: ignore
import logging
import signal
import sys

# For clean exit
running = True

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
BITVAVO_API_SECRET='28de1f1699a1bc9845a132e91dfa888801d7437d297e419521f6b9bbce670c88ea3a937b6f5c09421573340b5cc75f98edb05cd3ca19a79ddcc820e43b20c29b'

def get_portfolio_symbols():
    """Fetches coin symbols from the portfolio in the database."""
    try:
        connection = mysql.connector.connect(
            unix_socket="/opt/lampp/var/mysql/mysql.sock",
            host="127.0.0.1",
            user="root",
            password="1304",
            database="NS",
            port=3307
        )
        cursor = connection.cursor()
        cursor.execute("SELECT coin_id FROM portfolio; NS")
        symbols = cursor.fetchall()
        cursor.close()
        connection.close()
        return [f"{symbol[0].upper()}-EUR" for symbol in symbols]
    except mysql.connector.Error as err:
        script_logger.error(f"Database error in get_portfolio_symbols: {err}")
        return []
    except Exception as e:
        script_logger.error(f"An unexpected error occurred in get_portfolio_symbols: {e}")
        return []


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
            script_logger.warning("No symbols to track from portfolio.")
            return False

        response = self.bitvavo_engine.ticker24h({})
        prices = {item['market']: item['bid'] for item in response if 'bid' in item}

        try:
            connection = mysql.connector.connect(
                unix_socket="/opt/lampp/var/mysql/mysql.sock",
                host="127.0.0.1",
                user="root",
                password="1304",
                database="NS",
                port=3307
            )
            cursor = connection.cursor()

            for symbol_eur in symbols_to_track:
                if symbol_eur in prices:
                    price = prices[symbol_eur]
                    symbol = symbol_eur.replace('-EUR', '')
                    
                    price_logger.info(f"{symbol_eur}:{price}")

                    insert_query = "INSERT INTO price_history (coin_id, price) VALUES (%s, %s)"
                    cursor.execute(insert_query, (symbol, price))
                    connection.commit()
                    script_logger.info(f"Successfully inserted price for {symbol}: {price}")

            cursor.close()
            connection.close()

        except mysql.connector.Error as err:
            script_logger.error(f"Database error during price insertion: {err}")
        except Exception as e:
            script_logger.error(f"An error occurred during database operation: {e}")
        
        return True

if __name__ == '__main__':
    script_logger.info("Backend price update script started.")
    script_logger.handlers[0].flush() # Force flush the log immediately
    script_logger.info("--- Script starting ---")
    bvavo = BitvavoImplementation()
    while running:
        if not bvavo.log_and_save_portfolio_prices():
            script_logger.info("No portfolio coins found. Shutting down.")
            break
        time.sleep(3)
    script_logger.info("--- Script finished ---")