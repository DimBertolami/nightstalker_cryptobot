import requests

def fetch_binance_price(symbol="BTCUSDT"):
    url = f"https://api.binance.com/api/v3/ticker/bookTicker?symbol={symbol}"

    try:
        response = requests.get(url)
        response.raise_for_status()
        data = response.json()

        bid = float(data['bidPrice'])
        ask = float(data['askPrice'])
        spread = ask - bid

        print(f"{symbol} ➤ Bid: ${bid:.2f} | Ask: ${ask:.2f} | Spread: ${spread:.4f}")
        return data
    except requests.RequestException as e:
        print(f"Error fetching price from Binance: {e}")
        return None

# Example usage
fetch_binance_price("ETHUSDT")
