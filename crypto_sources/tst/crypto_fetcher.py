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


def get_recent_cmc_cryptos(min_volume=1_500_000):
    global last_fetch_time, cached_data, last_rate_limit_time

    current_time = time.time()
    if current_time - last_rate_limit_time < RATE_LIMIT_COOLDOWN:
        return cached_data or []

    if cached_data and (current_time - last_fetch_time) < CACHE_DURATION:
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
        response = requests.get(url, headers=HEADERS, params=params)
        if response.status_code == 429:
            last_rate_limit_time = current_time
            return cached_data or []

        response.raise_for_status()
        data = response.json()

        now = datetime.utcnow()
        one_day_ago = now - timedelta(days=1)
        recent_high_volume = []

        for coin in data['data']:
            date_added = datetime.strptime(coin['date_added'], "%Y-%m-%dT%H:%M:%S.%fZ")
            volume_24h = coin['quote']['USD']['volume_24h']

            if date_added > one_day_ago and volume_24h > min_volume:
                recent_high_volume.append({
                    "Exchange": "CoinMarketCap",
                    "Name": coin['name'],
                    "Symbol": coin['symbol'],
                    "Price USD": coin['quote']['USD']['price'],
                    "Price EUR": None,
                    "24h Change %": None,
                    "Volume": volume_24h,
                    "Market Cap": coin['quote']['USD']['market_cap']
                })

        cached_data = recent_high_volume
        last_fetch_time = current_time
        return recent_high_volume

    except requests.exceptions.RequestException:
        return cached_data or []


async def get_all_data():
    async with aiohttp.ClientSession() as session:
        bitvavo_task = asyncio.create_task(get_bitvavo_trending(session))
        jupiter_task = asyncio.create_task(get_jupiter_trending(session))

        bitvavo, jupiter = await asyncio.gather(bitvavo_task, jupiter_task)
        cmc = get_recent_cmc_cryptos()

        return bitvavo + jupiter + cmc
