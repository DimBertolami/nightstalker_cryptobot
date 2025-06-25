import sys
import subprocess
import requests
import logging

# Robust HTTP/JSON fetch helper
from requests.exceptions import JSONDecodeError, RequestException

logging.basicConfig(level=logging.INFO, format='%(levelname)s: %(message)s')

def safe_get_json(url, method="get", **kwargs):
    try:
        response = getattr(requests, method)(url, **kwargs)
        response.raise_for_status()
        return response.json()
    except JSONDecodeError:
        logging.error(f"JSON decode error for URL: {url}")
        logging.debug(f"Response text: {response.text[:200]}")
        return None
    except RequestException as e:
        logging.error(f"HTTP error for URL: {url} -- {e}")
        return None

def import_or_install(package, import_name=None):
    import_name = import_name or package
    try:
        return __import__(import_name)
    except ImportError:
        print(f"Package '{package}' not found. Installing...")
        subprocess.check_call([sys.executable, "-m", "pip", "install", package])
        try:
            return __import__(import_name)
        except ImportError:
            print(f"Failed to import '{import_name}' after installation. Exiting.")
            sys.exit(1)

aiohttp = import_or_install('aiohttp')
asyncio = import_or_install('asyncio')
requests = import_or_install('requests')
datetime_mod = import_or_install('datetime')
timedelta = datetime_mod.timedelta
datetime = datetime_mod.datetime
time = import_or_install('time')
json = import_or_install('json')
abc_mod = import_or_install('abc')
ABC = abc_mod.ABC
abstractmethod = abc_mod.abstractmethod
csv = import_or_install('csv')
colorama = import_or_install('colorama')
Fore = colorama.Fore
Style = colorama.Style
init = colorama.init
# No external dependencies for table formatting

# ---------------- CONFIG ----------------
BIRDEYE_API_KEY = '1758e18b-1744-4ad6-a2a9-908af2f33c8a'
COINGECKO_API_KEY = 'CG-YXnGRuZPgUAyWZs14mHBJVyW'
ALPACA_API_KEY = 'AK3SDJC0RCHYOZMM2M2M'
ALPACA_SECRET_KEY = 'YoObOLuNphmArDfgJ03aSaqBSijOlWIOfe0G8Zfx'
LIVECOINWATCH_KEY = 'bc88596f-30a5-4a64-a7d2-666a6a3b494b'
HEADERS = {'X-CMC_PRO_API_KEY': BIRDEYE_API_KEY}
CACHE_DURATION = 900
RATE_LIMIT_COOLDOWN = 300

# ---------------- STATE ----------------
last_fetch_time = 0
cached_data = None
last_rate_limit_time = 0

init(autoreset=True)


class CryptoSource(ABC):
    @abstractmethod
    async def fetch(self, session):
        pass


class BitvavoSource(CryptoSource):
    async def fetch(self, session):
        markets = await session.get("https://api.bitvavo.com/v2/markets")
        stats = await session.get("https://api.bitvavo.com/v2/ticker/24h")
        markets_json = await markets.json()
        stats_json = await stats.json()
        stats_dict = {item['market']: item for item in stats_json}

        trending = []
        for market in markets_json:
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
                "Price USD": float(data.get('last')) if "USDT" in symbol and data.get('last') is not None else None,
                "Price EUR": float(data.get('last')) if "EUR" in symbol and data.get('last') is not None else None,
                "24h Change %": float(data.get('priceChangePercentage')) if data.get('priceChangePercentage') is not None else 0,
                "Volume": float(data.get('volume')) if data.get('volume') is not None else 0,
                "Market Cap": None
            })

        return sorted(trending, key=lambda x: x['Volume'], reverse=True)[:10]


class JupiterSource(CryptoSource):
    async def fetch(self, session):
        try:
            # Get top tokens by market cap (simulate trending, as the new endpoint doesn't provide trending directly)
            tokens = await (await session.get("https://token.jup.ag/all")).json()
            top_tokens = [t for t in tokens if t.get("symbol")][:10]
            token_ids = ','.join([t['address'] for t in top_tokens])
            results = []
            price_url = "https://lite-api.jup.ag/price/v2"
            try:
                price_resp = await session.get(f"{price_url}?ids={token_ids}")
                price_data = await price_resp.json()
            except Exception as e:
                logging.error(f"Jupiter price API error (price batch): {e}")
                price_data = {}
            for token in top_tokens:
                token_id = token['address']
                name = token.get('name', '')
                symbol = token.get('symbol', '')
                price_info = price_data.get('data', {}).get(token_id, {})
                price = None
                try:
                    price = float(price_info.get('price')) if price_info.get('price') else None
                except Exception:
                    price = None
                results.append({
                    "Exchange": "Jupiter",
                    "Name": name,
                    "Symbol": symbol,
                    "Price USD": price,
                    "Price EUR": None,
                    "24h Change %": None,  # Not available in new API
                    "Volume": None,
                    "Market Cap": None
                })
            return results
        except Exception as e:
            logging.error(f"Jupiter API error: {e}")
            return []


class CoinGeckoSource(CryptoSource):
    async def fetch(self, session):
        try:
            data = await (await session.get("https://api.coingecko.com/api/v3/search/trending")).json()
            return [{
                "Exchange": "CoinGecko",
                "Name": coin['item']['name'],
                "Symbol": coin['item']['symbol'],
                "Price USD": coin['item']['price_btc'],
                "24h Change %": None,
                "Volume": None,
                "Market Cap": None
            } for coin in data['coins']]
        except Exception as e:
            print(f"CoinGecko API error: {e}")
            return []


class AlpacaSource(CryptoSource):
    async def fetch(self, session):
        url = "https://data.alpaca.markets/v1beta3/crypto/us/latest/bars"
        headers = {
            "APCA-API-KEY-ID": ALPACA_API_KEY,
            "APCA-API-SECRET-KEY": ALPACA_SECRET_KEY
        }
        params = {
            "symbols": "BTC/USD,ETH/USD,SOL/USD"
        }
        try:
            resp = await session.get(url, headers=headers, params=params)
            data = await resp.json()
            if 'bars' not in data:
                logging.error(f"Alpaca API error: missing 'bars' in response: {data}")
                return []
            cryptos = []
            for symbol, bar in data['bars'].items():
                if bar:
                    latest = bar
                    try:
                        open_ = latest.get('o')
                        close_ = latest.get('c')
                        change_pct = ((close_ - open_) / open_ * 100) if open_ else None
                        cryptos.append({
                            "Exchange": "Alpaca",
                            "Name": symbol.split('/')[0],
                            "Symbol": symbol,
                            "Price USD": close_,
                            "24h Change %": change_pct,
                            "Volume": latest.get('v'),
                            "Market Cap": None
                        })
                    except Exception as e:
                        logging.error(f"Alpaca parse error for {symbol}: {e}")
            return cryptos
        except Exception as e:
            logging.error(f"Alpaca API error: {e}")
            return []



class SyncCryptoSource(ABC):
    @abstractmethod
    def fetch(self):
        pass


class CMCSource(SyncCryptoSource):
    def fetch(self, min_volume=1_500_000):
        global last_fetch_time, cached_data, last_rate_limit_time
        now = time.time()

        if now - last_rate_limit_time < RATE_LIMIT_COOLDOWN:
            return cached_data or []

        if cached_data and (now - last_fetch_time) < CACHE_DURATION:
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
                logging.error("CoinMarketCap API rate limit hit (HTTP 429). Skipping fetch and using cached data if available.")
                last_rate_limit_time = now
                return cached_data or []

            response.raise_for_status()
            data = response.json()

            # Check for rate limiting in the API response body (sometimes CMC returns 200 with error message)
            status = data.get('status', {})
            if status.get('error_code') == 429 or (
                status.get('error_message') and 'rate limit' in status.get('error_message').lower()
            ):
                logging.error(f"CoinMarketCap API rate limit in response: {status}")
                last_rate_limit_time = now
                return cached_data or []

            from datetime import datetime, timedelta, timezone
            one_day_ago = datetime.now(timezone.utc) - timedelta(days=1)

            coins = [
                {
                    "Exchange": "CoinMarketCap",
                    "Name": coin['name'],
                    "Symbol": coin['symbol'],
                    "Price USD": coin['quote']['USD']['price'],
                    "Price EUR": None,
                    "24h Change %": None,
                    "Volume": coin['quote']['USD']['volume_24h'],
                    "Market Cap": coin['quote']['USD']['market_cap']
                }
                for coin in data['data']
                if datetime.strptime(coin['date_added'], "%Y-%m-%dT%H:%M:%S.%fZ").replace(tzinfo=timezone.utc) > one_day_ago
                and coin['quote']['USD']['volume_24h'] > min_volume
            ]
            cached_data = coins
            last_fetch_time = now
            return coins

        except requests.RequestException:
            return cached_data or []


class LiveCoinWatchSource(SyncCryptoSource):
    def fetch(self, min_volume=1_500_000):
        url = "https://api.livecoinwatch.com/coins/list"
        headers = {
            "content-type": "application/json",
            "x-api-key": LIVECOINWATCH_KEY
        }
        payload = {
            "currency": "USD",
            "sort": "age",
            "order": "ascending",
            "offset": 0,
            "limit": 1000,
            "meta": True
        }
        response = requests.post(url, data=json.dumps(payload), headers=headers)
        data = response.json()
        coins = []
        if isinstance(data, dict) and 'coins' in data:
            coins = data['coins']
        elif isinstance(data, list):
            coins = data
        else:
            print(f"LiveCoinWatch API error: Unexpected response format: {data}")
            return []
        return [
            {
                "Exchange": "LiveCoinWatch",
                "Name": coin.get('name'),
                "Symbol": coin.get('code'),
                "Price USD": coin.get('rate'),
                "24h Change %": coin.get('delta', {}).get('day') if isinstance(coin.get('delta'), dict) else None,
                "Volume": coin.get('volume'),
                "Market Cap": coin.get('cap')
            }
            for coin in coins if coin.get('volume', 0) is not None and coin.get('volume', 0) >= min_volume
        ]


class BirdeyeSource(SyncCryptoSource):
    def fetch(self, min_volume=1_500_000):
        url = "https://public-api.birdeye.so/defi/tokenlist"
        headers = {
            "x-api-key": BIRDEYE_API_KEY,
            "accept": "application/json"
        }
        params = {
            "sort_by": "volume",
            "sort_type": "desc",
            "limit": 100,
            "offset": 0
        }
        try:
            if not BIRDEYE_API_KEY or len(BIRDEYE_API_KEY) != 64:
                logging.error("Invalid Birdeye API key format (must be 64 chars)")
                return []
            
            response = requests.get(url, headers=headers, params=params)
            response.raise_for_status()
            data = response.json()
            
            if not data.get('data') or not isinstance(data['data'], list):
                logging.error(f"Birdeye API returned unexpected format: {data}")
                return []
            
            return [
                {
                    "Exchange": "Birdeye",
                    "Name": token.get("name"),
                    "Symbol": token.get("symbol"),
                    "Price USD": token.get("price_usd"),
                    "24h Change %": token.get("change_24h"),
                    "Volume": token.get("volume_usd_24h"),
                    "Market Cap": token.get("market_cap"),
                }
                for token in data['data'] if token.get("volume_usd_24h", 0) is not None and token.get("volume_usd_24h", 0) >= min_volume
            ]
        except Exception as e:
            logging.error(f"Birdeye API request failed - check API key permissions: {str(e)}")
            return []


class CoinPaprikaSource(SyncCryptoSource):
    def fetch(self, max_days_old=1):
        url = "https://api.coinpaprika.com/v1/coins"
        coins = safe_get_json(url)
        if not coins:
            return []
        new_coins = []
        for coin in coins:
            first_data_at = coin.get("first_data_at")
            if first_data_at:
                created = datetime.fromisoformat(first_data_at.replace("Z", ""))
                # Use timezone-aware UTC datetime for compatibility
                try:
                    now_dt = datetime.now(datetime.UTC)
                except AttributeError:
                    # Fallback for older Python versions
                    from datetime import timezone
                    now_dt = datetime.now(timezone.utc)
                if (now_dt - created).days <= max_days_old:
                    new_coins.append({
                        "Exchange": "CoinPaprika",
                        "Name": coin.get("name"),
                        "Symbol": coin.get("symbol"),
                        "Price USD": None,
                        "24h Change %": None,
                        "Volume": None,
                        "Market Cap": None
                    })
        return new_coins


class DexScreenerSource(SyncCryptoSource):
    def fetch(self, min_volume=1_500_000):
        url = "https://api.dexscreener.com/token-profiles/latest/v1"
        data = safe_get_json(url)
        if not data or not isinstance(data, list):
            logging.error(f"DexScreener API error: Unexpected response {data}")
            return []
        results = []
        for token in data:
            try:
                # DexScreener token profiles do not provide price/volume directly; skip tokens missing this info
                price = token.get('priceUsd')
                volume = token.get('volume24h')
                if volume is not None and volume >= min_volume:
                    results.append({
                        "Exchange": "DexScreener",
                        "Name": token.get('name'),
                        "Symbol": token.get('symbol'),
                        "Price USD": price,
                        "24h Change %": None,
                        "Volume": volume,
                        "Market Cap": None
                    })
            except Exception as e:
                logging.error(f"DexScreener parse error: {e}")
        return results


async def get_all_data():
    # List of source classes to skip due to persistent endpoint errors
    skip_sources = []  # All sources now working with updated endpoints

    failed_sources = []
    async_sources = [
        BitvavoSource(), JupiterSource(), CoinGeckoSource(), AlpacaSource()
    ]
    sync_sources = [
        CMCSource(), LiveCoinWatchSource(), BirdeyeSource(), CoinPaprikaSource(), DexScreenerSource()
    ]

    async with aiohttp.ClientSession() as session:
        async_results = []
        for source in async_sources:
            if source.__class__.__name__ in skip_sources:
                failed_sources.append(source.__class__.__name__)
                continue
            try:
                result = await source.fetch(session)
                async_results.append(result)
            except Exception as e:
                logging.error(f"Async source {source.__class__.__name__} failed: {e}")
                failed_sources.append(source.__class__.__name__)
        
    sync_results = []
    for source in sync_sources:
        if source.__class__.__name__ in skip_sources:
            failed_sources.append(source.__class__.__name__)
            continue
        try:
            result = source.fetch()
            sync_results.append(result)
        except Exception as e:
            logging.error(f"Sync source {source.__class__.__name__} failed: {e}")
            failed_sources.append(source.__class__.__name__)

    all_data = sum(async_results, []) + sum(sync_results, [])
    return all_data, list(set(failed_sources))


def write_to_csv(data, filename="trending_crypto.csv"):
    if not data:
        print("No data to write.")
        return

    keys = data[0].keys()
    with open(filename, 'w', newline='', encoding='utf-8') as f:
        writer = csv.DictWriter(f, fieldnames=keys)
        writer.writeheader()
        writer.writerows(data)
    print(f"{Fore.GREEN} Data written to {filename}")


def export_to_csv(data, filename="crypto_trending_data.csv", save_backup=True):
    """Export the crypto data to a CSV file for use in LibreOffice Calc"""
    import os
    from datetime import datetime, timezone
    
    # Current time for age calculation
    current_time = datetime.now(timezone.utc)
    
    # Filter entries with price or volume
    filtered_data = []
    for item in data:
        price = item.get('Price USD')
        volume = item.get('Volume')
        if price is None and (volume is None or volume == 0):
            continue
            
        # Format the data for CSV (without color formatting)
        change = item.get("24h Change %")
        change_str = f"{change:+.2f}%" if change is not None else "N/A"
        price_str = f"${price:,.2f}" if price is not None else "$0.00"
        volume_str = f"{volume:,}" if volume is not None else "N/A"
        market_cap = item.get('Market Cap')
        market_cap_str = f"${market_cap:,.2f}" if market_cap is not None else "N/A"
        
        # Add age in hours if available or set default for fallback mechanism
        age_hours = item.get('age_hours')
        first_seen = item.get('first_seen')
        
        # For fallback mechanism, we'll set all coins to be "new" (12 hours old)
        # if they don't have age information, so they pass the filter in the fallback
        if age_hours is None:
            age_hours = 12.0
            
        if first_seen is None:
            # Create a timestamp from 12 hours ago for fallback data
            from datetime import timedelta
            first_seen = (current_time - timedelta(hours=12)).isoformat()
        
        filtered_data.append({
            "Name": item['Name'],
            "Symbol": item['Symbol'],
            "Exchange": item['Exchange'],
            "Price USD": price_str,
            "24h Change %": change_str,
            "Volume": volume_str,
            "Market Cap": market_cap_str,
            "Age (hours)": age_hours,
            "First Seen": first_seen
        })
    
    # Write to CSV file
    if filtered_data:
        # Make sure the original file is written
        with open(filename, 'w', newline='') as csvfile:
            fieldnames = ["Name", "Symbol", "Exchange", "Price USD", "24h Change %", "Volume", "Market Cap", "Age (hours)", "First Seen"]
            writer = csv.DictWriter(csvfile, fieldnames=fieldnames)
            writer.writeheader()
            for item in filtered_data:
                writer.writerow(item)
        print(f"\n{Fore.GREEN}Data exported to {filename}{Style.RESET_ALL}")
        
        # Save a timestamped backup copy for fallback mechanism
        if save_backup:
            # Create backup directory if it doesn't exist
            backup_dir = "/opt/lampp/htdocs/NS/data/csv"
            os.makedirs(backup_dir, exist_ok=True)
            
            # Create timestamped filename
            timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
            backup_filename = os.path.join(backup_dir, f"crypto_data_{timestamp}.csv")
            
            # Save the backup copy
            with open(backup_filename, 'w', newline='') as csvfile:
                writer = csv.DictWriter(csvfile, fieldnames=fieldnames)
                writer.writeheader()
                for item in filtered_data:
                    writer.writerow(item)
            print(f"{Fore.GREEN}Backup saved to {backup_filename}{Style.RESET_ALL}")
    else:
        print(f"\n{Fore.RED}No data to export{Style.RESET_ALL}")

def export_to_html(data, filename="crypto_trending_data.html"):
    """Export the crypto data to an HTML file with AutoFilter enabled"""
    # Filter entries with price or volume
    filtered_data = []
    for item in data:
        price = item.get('Price USD')
        volume = item.get('Volume')
        if price is None and (volume is None or volume == 0):
            continue
            
        # Format the data for HTML
        change = item.get("24h Change %")
        change_str = f"{change:+.2f}%" if change is not None else "N/A"
        price_str = f"${price:,.2f}" if price is not None else "$0.00"
        volume_str = f"{volume:,}" if volume is not None else "N/A"
        market_cap = item.get('Market Cap')
        market_cap_str = f"${market_cap:,.2f}" if market_cap is not None else "N/A"
        
        # Add color to change values
        if change and change >= 0:
            change_str = f'<span style="color:green">{change_str}</span>'
        elif change and change < 0:
            change_str = f'<span style="color:red">{change_str}</span>'
        
        filtered_data.append({
            "Name": item['Name'],
            "Symbol": item['Symbol'],
            "Exchange": item['Exchange'],
            "Price USD": price_str,
            "24h Change %": change_str,
            "Volume": volume_str,
            "Market Cap": market_cap_str
        })
    
    # Create HTML file with data-table attributes for auto-filtering
    if filtered_data:
        fieldnames = ["Name", "Symbol", "Exchange", "Price USD", "24h Change %", "Volume", "Market Cap"]
        
        html_content = f"""
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Crypto Trending Data</title>
            <style>
                table {{ border-collapse: collapse; width: 100%; }}
                th, td {{ border: 1px solid #ddd; padding: 8px; text-align: left; }}
                th {{ background-color: #4CAF50; color: white; }}
                tr:nth-child(even) {{ background-color: #f2f2f2; }}
                tr:hover {{ background-color: #ddd; }}
            </style>
        </head>
        <body>
            <h2>Crypto Trending Data</h2>
            <p>This file can be opened directly in LibreOffice Calc or Excel. The table has AutoFilter enabled.</p>
            <p><b>Instructions:</b> When opening in LibreOffice Calc or Excel, select the entire table and enable AutoFilter (Data > AutoFilter).</p>
            <table id="cryptoTable" class="display" data-order='[[0, "asc"]]'>
                <thead>
                    <tr>
        """
        
        # Add table headers
        for field in fieldnames:
            html_content += f"<th>{field}</th>"
        html_content += "</tr></thead><tbody>"
        
        # Add table rows
        for item in filtered_data:
            html_content += "<tr>"
            for field in fieldnames:
                html_content += f"<td>{item[field]}</td>"
            html_content += "</tr>"
        
        html_content += """</tbody>
            </table>
        </body>
        </html>
        """
        
        with open(filename, 'w') as htmlfile:
            htmlfile.write(html_content)
        print(f"\n{Fore.GREEN}Data exported to {filename} (HTML with table formatting){Style.RESET_ALL}")
        print(f"{Fore.CYAN}Open this file in LibreOffice Calc or Excel for auto-filtering{Style.RESET_ALL}")
    else:
        print(f"\n{Fore.RED}No data to export{Style.RESET_ALL}")


def print_with_colors(data, failed_sources=None):
    # Filter entries with price or volume
    filtered_data = []
    for item in data:
        price = item.get('Price USD')
        volume = item.get('Volume')
        if price is None and (volume is None or volume == 0):
            continue
            
        # Format the data
        change = item.get("24h Change %")
        change_str = f"{change:+.2f}%" if change is not None else "N/A"
        price_str = f"${price:,.2f}" if price is not None else "$0.00"
        volume_str = f"{volume:,}" if volume is not None else "N/A"
        market_cap = item.get('Market Cap')
        market_cap_str = f"${market_cap:,.2f}" if market_cap is not None else "N/A"
        
        # Add colored versions for display
        change_color = Fore.GREEN if change and change >= 0 else Fore.RED
        
        filtered_data.append({
            "name": item['Name'],
            "symbol": item['Symbol'],
            "exchange": item['Exchange'],
            "price": price_str,
            "change": change_str,
            "change_positive": change and change >= 0,
            "volume": volume_str,
            "market_cap": market_cap_str
        })
    
    # Display the table
    if filtered_data:
        # Define column headers and widths
        headers = {
            "name": "Name", 
            "symbol": "Symbol", 
            "exchange": "Exchange", 
            "price": "Price USD", 
            "change": "24h Change %", 
            "volume": "Volume", 
            "market_cap": "Market Cap"
        }
        
        # Calculate column widths
        col_widths = {}
        for key in headers:
            # Start with header width
            col_widths[key] = len(headers[key])
            # Check data width
            for item in filtered_data:
                col_widths[key] = max(col_widths[key], len(str(item[key])))
        
        # Print top border
        top_border = "┏"
        for i, key in enumerate(headers):
            top_border += "━" * (col_widths[key] + 2)
            if i < len(headers) - 1:
                top_border += "┳"
            else:
                top_border += "┓"
        print(top_border)
        
        # Print header
        header_row = "┃"
        for key in headers:
            header_row += f" {Fore.GREEN}{headers[key]:{col_widths[key]}}{Style.RESET_ALL} ┃"
        print(header_row)
        
        # Print separator line
        separator = "┣"
        for i, key in enumerate(headers):
            separator += "━" * (col_widths[key] + 2)
            if i < len(headers) - 1:
                separator += "╋"
            else:
                separator += "┫"
        print(separator)
        
        # Print data rows with source separators
        current_source = None
        for item in filtered_data:
            # Check if we're switching to a new source
            if current_source is not None and current_source != item["exchange"]:
                # Print a thin separator line between different sources
                thin_separator = "┠"
                for i, key in enumerate(headers):
                    thin_separator += "┄" * (col_widths[key] + 2)
                    if i < len(headers) - 1:
                        thin_separator += "╂"
                    else:
                        thin_separator += "┨"
                print(thin_separator)
            
            # Update current source
            current_source = item["exchange"]
            
            # Print the data row
            row = "┃"
            for key in headers:
                if key == "name":
                    row += f" {Fore.CYAN}{item[key]:{col_widths[key]}}{Style.RESET_ALL} ┃"
                elif key == "symbol":
                    row += f" {Fore.CYAN}{item[key]:{col_widths[key]}}{Style.RESET_ALL} ┃"
                elif key == "exchange":
                    row += f" {Fore.BLUE}{item[key]:{col_widths[key]}}{Style.RESET_ALL} ┃"
                elif key == "price":
                    row += f" {Fore.YELLOW}{item[key]:{col_widths[key]}}{Style.RESET_ALL} ┃"
                elif key == "change":
                    color = Fore.GREEN if item["change_positive"] else Fore.RED
                    row += f" {color}{item[key]:{col_widths[key]}}{Style.RESET_ALL} ┃"
                elif key == "volume":
                    row += f" {Fore.MAGENTA}{item[key]:{col_widths[key]}}{Style.RESET_ALL} ┃"
                else:
                    row += f" {item[key]:{col_widths[key]}} ┃"
            print(row)
        
        # Print bottom border
        bottom_border = "┗"
        for i, key in enumerate(headers):
            bottom_border += "━" * (col_widths[key] + 2)
            if i < len(headers) - 1:
                bottom_border += "┻"
            else:
                bottom_border += "┛"
        print(bottom_border)
        
        print(f"\n{Fore.CYAN}Displayed {len(filtered_data)} entries with price or volume data.{Style.RESET_ALL}")
    else:
        print(f"{Fore.YELLOW}No data with price or volume to display.{Style.RESET_ALL}")
        
    if failed_sources:
        print(f"{Fore.RED}Skipped sources due to errors or endpoint issues: {', '.join(failed_sources)}{Style.RESET_ALL}")


async def main():
    data, failed_sources = await get_all_data()
    write_to_csv(data)
    print_with_colors(data, failed_sources=failed_sources)
    return data


if __name__ == "__main__":
    # Run the main function
    data = asyncio.run(main())
    
    # Export data to CSV for LibreOffice Calc
    export_to_csv(data)
    
    # Export data to HTML with table formatting (better for auto-filtering)
    export_to_html(data)