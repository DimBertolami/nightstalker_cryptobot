import requests

def fetch_birdeye_new_coins(min_volume=1_500_000):
    url = "https://public-api.birdeye.so/public/token/list?sort_by=created&sort_type=asc"
    headers = {"accept": "application/json"}
    
    try:
        response = requests.get(url, headers=headers)
        response.raise_for_status()
        tokens = response.json().get("data", [])
        
        return [{
            'symbol': token['symbol'],
            'name': token['name'],
            'volume': token['volume_usd_24h'],
            'created_at': token['created_at']
        } for token in tokens if token.get("volume_usd_24h", 0) >= min_volume]
    
    except Exception as e:
        print(f"Error fetching from Birdeye: {e}")
        return []

# Usage
coins = fetch_birdeye_new_coins()
print("\nBirdeye New Coins:")
for coin in coins[:5]:
    print(f"{coin['symbol']} ({coin['name']}): ${coin['volume']:,}, created: {coin['created_at']}")
