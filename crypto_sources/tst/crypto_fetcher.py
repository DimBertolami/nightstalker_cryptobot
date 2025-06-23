import aiohttp
import asyncio
import requests
from datetime import datetime, timedelta
import time

# ---------------- CONFIG ----------------
API_KEY = '1758e18b-1744-4ad6-a2a9-908af2f33c8a'
HEADERS = {'X-CMC_PRO_API_KEY': API_KEY}
CACHE_DURATION = 900
RATE_LIMIT_COOLDOWN = 300

# ---------------- STATE ----------------
last_fetch_time = 0
cached_data = None
last_rate_limit_time = 0


async def fetch_json(session, url, params=None):
    async with session.get(url, params=params) as response:
        return await response.json()


async def get_bitvavo_trending(session):
    markets_url = "https://api.bitvavo.com/v2/markets"
    ticker_url = "https://api.bitvavo.com/v2/ticker/24h"
    markets = await fetch_json(session, markets_url)
    stats = await fetch_json(session, ticker_url)
    stats_dict = {item['market']: item for item in stats}

    trending = []
    for market in markets:
        symbol = market['market']
        if symbol not in stats_dict:
            continue
        data = stats_dict[symbol]

        if not any(x in symbol for x in ["EUR", "USDT"]):
            continue

        trending.append({
            "Exchange": "Bitvavo",
            "Name": symbol.split("-")[0],
            "Symbol": symbol,
            "Price USD": float(data['last']) if "USDT" in symbol else None,
            "Price EUR": float(data['last']) if "EUR" in symbol else None,
            "24h Change %": float(data['priceChangePercentage']),
            "Volume": float(data['volume']),
            "Market Cap": None
        })

    trending = sorted(trending, key=lambda x: x['Volume'], reverse=True)[:10]
    return trending


async def get_jupiter_trending(session):
    tokens_url = "https://token.jup.ag/all"
    tokens = await fetch_json(session, tokens_url)

    top_tokens = [t for t in tokens if t.get("extensions", {}).get("coingeckoId")][:10]
    results = []

    for token in top_tokens:
        token_id = token.get("symbol", "")
        name = token.get("name", "")
        coingecko_id = token["extensions"]["coingeckoId"]

        price_data = await fetch_json(session, f"https://price.jup.ag/v4/price", params={"ids": token_id})
        price_info = price_data.get(token_id, {})

        results.append({
            "Exchange": "Jupiter",
            "Name": name,
            "Symbol": token_id,
            "Price USD": price_info.get("price"),
            "Price EUR": None,
            "24h Change %": price_info.get("priceChangePct24h", 0),
            "Volume": None,
            "Market Cap": None
        })

    return results


def get_recent_cmc_cryptos(min_volume=1_500_000, min_market_cap=1_000_000, max_age_days=1):
    global last_fetch_time, cached_data, last_rate_limit_time

    current_time = time.time()
    if current_time - last_rate_limit_time < RATE_LIMIT_COOLDOWN:
        print("Rate limit cooling down, using cached data")
        return cached_data or []

    if cached_data and (current_time - last_fetch_time) < CACHE_DURATION:
        print("Using cached data (cache still valid)")
        return cached_data

    url = 'https://pro-api.coinmarketcap.com/v1/cryptocurrency/listings/latest'
    params = {
        'start': '1',
        'limit': '50',
        'sort': 'date_added',
        'sort_dir': 'desc',
        'convert': 'USD'
    }

    try:
        print(f"Fetching new data from CoinMarketCap with filters:")
        print(f"- Minimum volume: ${min_volume:,}")
        print(f"- Minimum market cap: ${min_market_cap:,}")
        print(f"- Maximum age: {max_age_days} days")
        print("-" * 50)
        
        response = requests.get(url, headers=HEADERS, params=params)
        if response.status_code == 429:
            last_rate_limit_time = current_time
            print("Rate limit exceeded, try again later")
            return cached_data or []

        response.raise_for_status()
        data = response.json()

        now = datetime.utcnow()
        age_threshold = now - timedelta(days=max_age_days)
        recent_high_volume = []
        
        print(f"Total coins retrieved before filtering: {len(data['data'])}")
        
        # Track filtered out coins 
        filtered_by_age = 0
        filtered_by_volume = 0
        filtered_by_market_cap = 0

        for coin in data['data']:
            date_added = datetime.strptime(coin['date_added'], "%Y-%m-%dT%H:%M:%S.%fZ")
            volume_24h = coin['quote']['USD']['volume_24h']
            market_cap = coin['quote']['USD']['market_cap']
            
            # Apply filters one by one to track statistics
            if date_added <= age_threshold:
                filtered_by_age += 1
                continue
                
            if volume_24h < min_volume:
                filtered_by_volume += 1
                continue
                
            if market_cap < min_market_cap:
                filtered_by_market_cap += 1
                continue
            
            # All filters passed
            recent_high_volume.append({
                "Exchange": "CoinMarketCap",
                "Name": coin['name'],
                "Symbol": coin['symbol'],
                "Price USD": coin['quote']['USD']['price'],
                "Price EUR": None,
                "24h Change %": coin['quote']['USD']['percent_change_24h'],
                "Volume": volume_24h,
                "Market Cap": market_cap,
                "Date Added": date_added.strftime("%Y-%m-%d %H:%M:%S")
            })

        # Print filter statistics
        print(f"Coins filtered out by age: {filtered_by_age}")
        print(f"Coins filtered out by volume: {filtered_by_volume}")
        print(f"Coins filtered out by market cap: {filtered_by_market_cap}")
        print(f"Coins remaining after filtering: {len(recent_high_volume)}")
        print("-" * 50)
        
        # Print details of remaining coins
        if recent_high_volume:
            print("New coins that passed all filters:")
            for i, coin in enumerate(recent_high_volume, 1):
                print(f"{i}. {coin['Name']} ({coin['Symbol']})")
                print(f"   - Listed on: {coin['Date Added']}")
                print(f"   - Price: ${coin['Price USD']:.6f}")
                print(f"   - Market Cap: ${coin['Market Cap']:,.2f}")
                print(f"   - 24h Volume: ${coin['Volume']:,.2f}")
                if coin['24h Change %'] is not None:
                    print(f"   - 24h Change: {coin['24h Change %']:.2f}%")
                print()
        else:
            print("No coins passed all the filters")
        
        cached_data = recent_high_volume
        last_fetch_time = current_time
        return recent_high_volume

    except requests.exceptions.RequestException as e:
        print(f"Error fetching data from CoinMarketCap: {e}")
        return cached_data or []


async def get_all_data():
    async with aiohttp.ClientSession() as session:
        bitvavo_task = asyncio.create_task(get_bitvavo_trending(session))
        jupiter_task = asyncio.create_task(get_jupiter_trending(session))

        bitvavo, jupiter = await asyncio.gather(bitvavo_task, jupiter_task)
        cmc = get_recent_cmc_cryptos()

        return bitvavo + jupiter + cmc


# Execute the script
if __name__ == "__main__":
    print("Crypto Fetcher - New Coins Discovery Tool")
    print("========================================")
    
    # Get CoinMarketCap data with custom filters
    cmc_data = get_recent_cmc_cryptos(
        min_volume=1_500_000,       # $1.5M minimum 24h volume
        min_market_cap=1_000_000,   # $1M minimum market cap
        max_age_days=1              # Listed within the last day
    )
    
    # Run additional async data sources if needed
    # loop = asyncio.get_event_loop()
    # all_data = loop.run_until_complete(get_all_data())
    # print(f"\nTotal coins across all sources: {len(all_data)}")
