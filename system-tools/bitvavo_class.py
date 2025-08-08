from python_bitvavo_api.bitvavo import Bitvavo # type: ignore
import json
import time

# Use this class to connect to Bitvavo and make your first calls.
# Add trading strategies to implement your business logic.

# Use this class to connect to Bitvavo and make your first calls.
# Add trading strategies to implement your business logic.
class BitvavoImplementation:
    api_key = 'ce59283de845c416deef1dd91f10c3879f0554e18c938dc9170550cebfcfbe37'
    api_secret = '28de1f1699a1bc9845a132e91dfa888801d7437d297e419521f6b9bbce670c88ea3a937b6f5c09421573340b5cc75f98edb05cd3ca19a79ddcc820e43b20c29b'
    bitvavo_engine = None
    bitvavo_socket = None

    # Connect securely to Bitvavo, create the WebSocket and error callbacks.
    def __init__(self):
        self.bitvavo_engine = Bitvavo({
            'APIKEY': self.api_key,
            'APISECRET': self.api_secret
        })
        self.bitvavo_socket = self.bitvavo_engine.newWebsocket()
        self.bitvavo_socket.setErrorCallback(self.error_callback)
    def get_balance(self):
        return self.bitvavo_engine.balance({})
    # This is the callback for the WebSocket. It is called when an error occurs.
    # You can use this to log errors or handle them in your app.
    # The error callback is called with a dictionary containing the error
    # message and the error code.
    # See the Bitvavo API documentation for more information on error codes.
    # https://docs.bitvavo.com/#tag/Errors


    # Handle errors.
    def error_callback(self, error):
        print("Add your error message.")
        #print("Errors:", json.dumps(error, indent=2))

    # Retrieve the data you need from Bitvavo in order to implement your
    # trading logic. Use multiple workflows to return data to your
    # callbacks.
    def a_trading_strategy(self):
        self.bitvavo_socket.ticker24h({}, self.a_trading_strategy_callback)

    # In your app you analyse data returned by the trading strategy, then make
    # calls to Bitvavo to respond to market conditions.
    def a_trading_strategy_callback(self, response):
        # Iterate through the markets
        for market in response:

            match market["market"]:
                case "ZRC-EUR":
                    # Implement calculations for your trading logic.
                    # If they are positive, place an order: For example:
                    self.bitvavo_socket.placeOrder("ZRC-EUR", 'buy', 'limit', { 'amount': '1', 'price': '00001' }, self.order_placed_callback)
                    print("Order placed for ZRC-EUR")
                case "a different market":
                    print("do something else")
                case _:
                    print("Not this one: ", market["market"])

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
            while (limit > 0):
                time.sleep(0.5)
                limit = self.bitvavo_engine.getRemainingLimit()
        except KeyboardInterrupt:
            self.bitvavo_socket.closeSocket()


# Shall I re-explain main? Naaaaaaaaaa.
if __name__ == '__main__':
    bvavo = BitvavoImplementation()
    wallet_ballance = bvavo.get_balance()
    print(wallet_ballance)
    bvavo.get_markets = bvavo.bitvavo_engine.markets({})
#    print(bvavo.get_markets)
#    print("Starting trading strategy...")
    bvavo.a_trading_strategy()
#    print("Waiting for trades to complete...")
    bvavo.wait_and_close()
