import requests
import json

API_KEY = "bc88596f-30a5-4a64-a7d2-666a6a3b494b"

def fetch_livecoinwatch_new_coins(min_volume=1_500_000):
    url = "https://api.livecoinwatch.com/coins/list"
    headers = {
        "content-type": "application/json",
        "x-api-key": API_KEY
    }
    payload = {
        "currency": "USD",
        "sort": "age",  # or 'volume' or 'marketCap'
        "order": "ascending",
        "offset": 0,
        "limit": 1000,
        "meta": True
    }

    response = requests.post(url, data=json.dumps(payload), headers=headers)
    data = response.json()

    # In case the response is wrapped in {'coins': [...]}
    coins = data.get('coins') if isinstance(data, dict) else data

    # Safely filter by volume
    new_coins = [
        coin for coin in coins
        if isinstance(coin.get('volume'), (int, float)) and coin['volume'] >= min_volume
    ]

    return new_coins

# Demo output
if __name__ == "__main__":
    coins = fetch_livecoinwatch_new_coins()
    for coin in coins[:5]:
        print(f"{coin['code']}: ${coin['volume']:,} volume, age: {coin['age']}s")

