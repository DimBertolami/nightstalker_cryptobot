import requests
import time

def get_valid_markets():
    url = "https://api.bitvavo.com/v2/markets"
    try:
        response = requests.get(url, timeout=5)
        response.raise_for_status()
        return response.json()
    except Exception as e:
        print(f"Error fetching markets: {e}")
        return []

def get_top_markets_by_volume(limit=5):
    url = "https://api.bitvavo.com/v2/ticker/24h"
    try:
        response = requests.get(url, timeout=5)
        response.raise_for_status()
        tickers = response.json()
        eur_tickers = [t for t in tickers if t['market'].endswith('-EUR') and float(t['volume']) > 0]
        sorted_by_volume = sorted(eur_tickers, key=lambda x: float(x['baseVolume']), reverse=True)
        return [t['market'] for t in sorted_by_volume[:limit]]
    except Exception as e:
        print(f"Error fetching volume data: {e}")
        return []

def fetch_best_bid_ask(market):
    url = f"https://api.bitvavo.com/v2/{market}/book?depth=1"
    try:
        response = requests.get(url, timeout=5)
        response.raise_for_status()
        data = response.json()
        best_bid = float(data['bids'][0][0]) if data['bids'] else None
        best_ask = float(data['asks'][0][0]) if data['asks'] else None
        return best_bid, best_ask
    except Exception as e:
        print(f"Error fetching order book for {market}: {e}")
        return None, None

def colorize(text, color):
    colors = {
        "green": "\033[92m",
        "yellow": "\033[93m",
        "red": "\033[91m",
        "reset": "\033[0m"
    }
    return f"{colors[color]}{text}{colors['reset']}"

def format_market_data(bid, ask):
    spread_pct = (ask - bid) / bid * 100
    spread_text = f"{spread_pct:.2f}%"
    if spread_pct <= 0.5:
        spread_colored = colorize(spread_text, "green")
    elif spread_pct <= 1.0:
        spread_colored = colorize(spread_text, "yellow")
    else:
        spread_colored = colorize(spread_text, "red")
    return f"B:€{bid:.4f} A:€{ask:.4f} S:{spread_colored}"

def main():
    print("📈 Bitvavo Real-Time Price Monitor (Table Format)\n")

    markets_to_monitor = get_top_markets_by_volume()
    if not markets_to_monitor:
        print("⚠️ Failed to get top volume markets. Falling back to static list.")
        all_markets = get_valid_markets()
        markets_to_monitor = [m['market'] for m in all_markets if m['quote'] == 'EUR' and m['status'] == 'trading'][:5]

    if not markets_to_monitor:
        print("❌ No markets to monitor. Exiting.")
        return

    print("Monitoring markets:")
    print(" | ".join(f"{m:^25}" for m in markets_to_monitor))
    print("-" * (29 * len(markets_to_monitor)))

    try:
        while True:
            row = []
            for market in markets_to_monitor:
                bid, ask = fetch_best_bid_ask(market)
                if bid is not None and ask is not None:
                    row.append(f"{format_market_data(bid, ask):<25}")
                else:
                    row.append("N/A".ljust(25))
            print(" | ".join(row))
            time.sleep(3)
    except KeyboardInterrupt:
        print("\n🛑 Monitoring stopped")

if __name__ == "__main__":
    main()

