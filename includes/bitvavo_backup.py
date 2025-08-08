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
      
    connection = mysql.connector.connect(unix_socket="/opt/lampp/var/mysql/mysql.sock", host="127.0.0.1", user="root", password="1304", database="NS", port=3307)
    cursor = connection.cursor()
    cursor.execute("SELECT c.symbol FROM portfolio p JOIN coins c ON p.coin_id = c.id;")
    symbols = cursor.fetchall()
    connection.close()
    # Return as uppercase symbols with -EUR added
    return [f"{symbol[0].upper()}-EUR" for symbol in symbols]


class BitvavoImplementation:
    api_key = BITVAVO_API_KEY
    api_secret = BITVAVO_API_SECRET
    bitvavo_engine = None
    bitvavo_socket = None

    # Connect securely to Bitvavo, create the WebSocket and error callbacks.
    def __init__(self, tracked_symbols):
        self.api_key = BITVAVO_API_KEY
        self.api_secret = BITVAVO_API_SECRET
        self.tracked_symbols = set(tracked_symbols)  # use set for fast lookup
        self.first_data_received = False

        self.bitvavo_engine = Bitvavo({
            'APIKEY': self.api_key,
            'APISECRET': self.api_secret
        })
        self.bitvavo_socket = self.bitvavo_engine.newWebsocket()
        self.bitvavo_socket.setErrorCallback(self.error_callback)

        self.socket_closed = False

        # Attempt to patch the underlying websocket-client WebSocketApp on_close callback
        try:
            ws_app = getattr(self.bitvavo_socket, 'ws', None)
            if ws_app:
                original_on_close = getattr(ws_app, 'on_close', None)
                def patched_on_close(ws, close_status_code, close_msg, *args):
                    #print("Patched underlying websocket on_close called")
                    self.socket_closed = True
                    if original_on_close:
                        try:
                            original_on_close(ws, close_status_code, close_msg)
                        except Exception:
                            pass
                ws_app.on_close = patched_on_close
        except Exception as e:
            print("Exception patching underlying websocket on_close:", e)

    def wait_and_close(self):
        # Bitvavo uses a weight based rate limiting system. Your app is limited to 1000 weight points per IP or
        # API key per minute. The rate weighting for each endpoint is supplied in Bitvavo API documentation.
        # This call returns the amount of points left. If you make more requests than permitted by the weight limit,
        # your IP or API key is banned.
        limit = self.bitvavo_engine.getRemainingLimit()
        try:
            max_wait_seconds = 10
            waited = 0
            while (limit > 0 and not self.socket_closed and waited < max_wait_seconds):
                time.sleep(0.5)
                waited += 0.5
                limit = self.bitvavo_engine.getRemainingLimit()
        except KeyboardInterrupt:
            print("KeyboardInterrupt received, closing socket...")
            try:
                self.bitvavo_socket.closeSocket()
            except Exception as e:
                print("Exception during closeSocket:", e)
            # Attempt to join the receive thread with timeout to avoid hanging
            try:
                if hasattr(self.bitvavo_socket, 'receiveThread') and self.bitvavo_socket.receiveThread.is_alive():
                    self.bitvavo_socket.receiveThread.join(timeout=2)
            except Exception as e:
                print("Exception during receiveThread join:", e)

    # Handle errors.
    def error_callback(self, error):
        # Print error as string to avoid JSON serialization issues
        print("Error:", str(error))

    # Retrieve the data you need from Bitvavo in order to implement your
    # trading logic. Use multiple workflows to return data to your
    # callbacks.
    def a_trading_strategy(self):
        self.bitvavo_socket.ticker24h({}, self.a_trading_strategy_callback)

    # In your app you analyse data returned by the trading strategy, then make
    # calls to Bitvavo to respond to market conditions.
    def a_trading_strategy_callback(self, response):
      if not self.first_data_received:
          for market in response:
              if market["market"] in self.tracked_symbols:
                  print(f"{market['market']}: {market['bid']}")
          self.first_data_received = True
          # Close the socket after first data batch is processed
          # Use a timer to close socket outside of callback thread to avoid join current thread error
          import threading
          threading.Timer(0, self.bitvavo_socket.closeSocket).start()

    def order_placed_callback(self, response):
        # The order return parameters explain the quote and the fees for this trade.
        print("Order placed:", json.dumps(response, indent=2))
        # Add your business logic.

    # Sockets are fast, but asynchronous. Keep the socket open while you are
    # trading.
    def wait_and_close(self):
        # Bitvavo uses a weight based rate limiting system. Your app is limited to 1000 weight points per IP or
        # API key per minute. The rate weighting for each endpoint is supplied in Bitvavo API documentation.
        # This call returns the amount of points left. If you make more requests than permitted by the weight limit,
        # your IP or API key is banned.
        limit = self.bitvavo_engine.getRemainingLimit()
        try:
            while (limit > 0 and not self.socket_closed):
                time.sleep(0.5)
                limit = self.bitvavo_engine.getRemainingLimit()
        except KeyboardInterrupt:
            print("KeyboardInterrupt received, closing socket...")
            self.bitvavo_socket.closeSocket()
            # Attempt to join the receive thread with timeout to avoid hanging
            if hasattr(self.bitvavo_socket, 'receiveThread') and self.bitvavo_socket.receiveThread.is_alive():
                self.bitvavo_socket.receiveThread.join(timeout=2)


if __name__ == '__main__':
    symbols_to_track = get_portfolio_symbols()
#    print("Tracking:", symbols_to_track)
    bvavo = BitvavoImplementation(tracked_symbols=symbols_to_track)
    bvavo.a_trading_strategy()
    bvavo.wait_and_close()
