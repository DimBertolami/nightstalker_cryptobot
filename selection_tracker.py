import pandas as pd
from datetime import datetime, timedelta
import logging
from typing import Dict, Any, List
from sqlalchemy import create_engine, text
from sqlalchemy.orm import sessionmaker

logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')

class SelectionTracker:
    def __init__(self, db_engine=None):
        self.db_engine = db_engine
        self.Session = sessionmaker(bind=self.db_engine) if self.db_engine else None
        self.selection_history = [] # In-memory for demonstration, primarily for testing without DB

    def record_selection(self, selected_coins: List[Dict], selection_metadata: Dict = None):
        """
        Records a coin selection event.
        In a real system, this would persist to a database.
        """
        timestamp = datetime.now()
        for coin in selected_coins:
            record = {
                'timestamp': timestamp,
                'coin_symbol': coin['symbol'],
                'predicted_action': coin['predicted_action'],
                'composite_score': coin['composite_score'],
                'risk_adjusted_score': coin['risk_adjusted_score'],
                'price_at_selection': coin['close'], # Assuming 'close' is current price
                'metadata': selection_metadata # e.g., model version, market regime
            }
            self.selection_history.append(record)
            logging.info(f"Recorded selection for {coin['symbol']} at {timestamp}")

            # Database insertion
            if self.db_engine and self.Session:
                session = self.Session()
                try:
                    query = text("INSERT INTO coin_selections (timestamp, symbol, action, score, risk_score, price) VALUES (:timestamp, :symbol, :action, :score, :risk_score, :price)")
                    session.execute(query, {
                        "timestamp": record['timestamp'],
                        "symbol": record['coin_symbol'],
                        "action": record['predicted_action'],
                        "score": record['composite_score'],
                        "risk_score": record['risk_adjusted_score'],
                        "price": record['price_at_selection']
                    })
                    session.commit()
                    logging.info(f"Successfully saved selection for {coin['symbol']} to DB.")
                except Exception as e:
                    session.rollback()
                    logging.error(f"Error saving selection for {coin['symbol']} to DB: {e}")
                finally:
                    session.close()

    def get_selection_history(self, start_date: datetime = None, end_date: datetime = None) -> pd.DataFrame:
        """
        Retrieves historical coin selections.
        """
        history_df = pd.DataFrame(self.selection_history)
        if not history_df.empty:
            history_df['timestamp'] = pd.to_datetime(history_df['timestamp'])
            if start_date:
                history_df = history_df[history_df['timestamp'] >= start_date]
            if end_date:
                history_df = history_df[history_df['timestamp'] <= end_date]
        return history_df

    def analyze_performance(self) -> Dict[str, Any]:
        """
        Analyzes the performance of past selections.
        (Placeholder - requires actual trade outcomes to be linked)
        """
        logging.info("Analyzing selection performance (placeholder)...")
        history = self.get_selection_history()
        if history.empty:
            return {"message": "No selection history to analyze."}

        # Dummy analysis metrics
        buy_selections = history[history['predicted_action'] == 'buy']
        sell_selections = history[history['predicted_action'] == 'sell']

        analysis = {
            "total_selections": len(history),
            "buy_selections": len(buy_selections),
            "sell_selections": len(sell_selections),
            "avg_composite_score": history['composite_score'].mean() if not history.empty else 0,
            "avg_risk_adjusted_score": history['risk_adjusted_score'].mean() if not history.empty else 0,
            "win_rate_placeholder": "N/A (requires actual trade outcomes)"
        }
        logging.info("Selection performance analysis complete.")
        return analysis

if __name__ == "__main__":
    print("Running SelectionTracker example...")
    tracker = SelectionTracker()

    # Create some dummy selected coins data (similar to what CryptoSelector might return)
    dummy_selected_coins = pd.DataFrame({
        'symbol': ['BTC', 'ETH', 'ADA'],
        'predicted_action': ['buy', 'buy', 'hold'],
        'composite_score': [0.95, 0.88, 0.70],
        'risk_adjusted_score': [0.90, 0.85, 0.68],
        'close': [30000, 2000, 0.5],
        'timestamp': [datetime.now(), datetime.now(), datetime.now()]
    })

    # Record selections
    tracker.record_selection(dummy_selected_coins, {'model_version': 'v1.0'})

    # Add more dummy data for different times
    dummy_selected_coins_2 = pd.DataFrame({
        'symbol': ['XRP', 'DOGE'],
        'predicted_action': ['sell', 'buy'],
        'composite_score': [0.80, 0.75],
        'risk_adjusted_score': [0.78, 0.72],
        'close': [0.6, 0.1],
        'timestamp': [datetime.now() - timedelta(days=1), datetime.now() - timedelta(days=1)]
    })
    tracker.record_selection(dummy_selected_coins_2, {'model_version': 'v1.0'})

    # Get selection history
    print("\nSelection History:")
    history_df = tracker.get_selection_history()
    print(history_df.head())

    # Analyze performance
    print("\nPerformance Analysis:")
    analysis_results = tracker.analyze_performance()
    print(analysis_results)

    # Get history for a specific date range
    print("\nSelection History for last 2 days:")
    two_days_ago = datetime.now() - timedelta(days=2)
    recent_history = tracker.get_selection_history(start_date=two_days_ago)
    