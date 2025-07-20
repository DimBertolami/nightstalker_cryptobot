# This code fetches the current price of a cryptocurrency from the Bitvavo API.
import json
import time
import mysql.connector # type: ignore
from python_bitvavo_api.bitvavo import Bitvavo # type: ignore

BITVAVO_API_KEY='ce59283de845c416deef1dd91f10c3879f0554e18c938dc9170550cebfcfbe37';
BITVAVO_API_SECRET='28de1f1699a1bc9845a132e91dfa888801d7437d297e419521f6b9bbce670c88ea3a937b6f5c09421573340b5cc75f98edb05cd3ca19a79ddcc820e43b20c29b'
# If they are positive, place an order: For example:
# self.bitvavo_socket.placeOrder("ZRX-EUR", 'buy','limit', { 'amount': '1', 'price': '00001' }, self.order_placed_callback)

#mysql -u dimi -p1304 -e "SELECT c.symbol FROM portfolio p JOIN coins c ON p.coin_id = c.id;" NS


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
        # Temporarily simplified query for debugging: Fetch symbols directly from coins table.
        # Original query: "SELECT c.symbol FROM portfolio p JOIN coins c ON p.coin_id = c.id;"
        #cursor.execute("SELECT symbol FROM coins LIMIT 5;")
        cursor.execute("SELECT coin_id FROM portfolio; NS")
        symbols = cursor.fetchall()
#        print(f"Raw symbols fetched from coins table: {symbols}")
        cursor.close()
        connection.close()
        # Return as uppercase symbols with -EUR added for the Bitvavo API.
        return [f"{symbol[0].upper()}-EUR" for symbol in symbols]
    except mysql.connector.Error as err:
        print(f"Database error in get_portfolio_symbols: {err}")
        print(f"MySQL Error Code: {err.errno}")
        print(f"MySQL Error Message: {err.msg}")
        return []
    except Exception as e:
        print(f"An unexpected error occurred in get_portfolio_symbols: {e}")
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

    def display_and_save_portfolio_prices(self):
        symbols_to_track = get_portfolio_symbols()
        if not symbols_to_track:
            print("No symbols to track from portfolio. Exiting.")
            return

        response = self.bitvavo_engine.ticker24h({})
        
        # The ticker24h endpoint uses 'bid' for the bid price, not 'bestBid'.
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
                    
                    print(f"{symbol_eur}:{price}")

                    insert_query = "INSERT INTO price_history (coin_id, price) VALUES (%s, %s)"
                    cursor.execute(insert_query, (symbol, price))
                    connection.commit()
                    print(f"price insert: success")

            cursor.close()
            connection.close()

        except mysql.connector.Error as err:
            print(f"Database error during price insertion: {err}")
        except Exception as e:
            print(f"An error occurred during database operation: {e}")

if __name__ == '__main__':
    bvavo = BitvavoImplementation()
    bvavo.display_and_save_portfolio_prices()