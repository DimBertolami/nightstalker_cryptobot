import logging
import os
import sys
import time
import traceback
from datetime import datetime
from pathlib import Path
import signal
import threading
from typing import Optional

from trading_bot import CryptoTradingBot
from database import get_db, init_db
import pandas as pd

# Setup logging
log_dir = Path('logs')
log_dir.mkdir(exist_ok=True)

logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler(log_dir / 'trading_bot_run.log'),
        logging.StreamHandler()
    ]
)

logger = logging.getLogger(__name__)

class GracefulKiller:
    """Handle graceful shutdown of the bot"""
    def __init__(self):
        self.kill_now = False
        signal.signal(signal.SIGINT, self.exit_gracefully)
        signal.signal(signal.SIGTERM, self.exit_gracefully)

    def exit_gracefully(self, signum, frame):
        logger.info(f"Received signal {signum}, shutting down gracefully...")
        self.kill_now = True

class TradingBotRunner:
    def __init__(self, config_path: str = 'config/trading_config.json'):
        self.config_path = config_path
        self.bot: Optional[CryptoTradingBot] = None
        self.killer = GracefulKiller()
        self.health_check_thread: Optional[threading.Thread] = None
        self.last_heartbeat = datetime.now()
        self.max_heartbeat_interval = 300  # 5 minutes

    def load_config(self) -> dict:
        """Load and validate configuration"""
        try:
            import json
            
            if not os.path.exists(self.config_path):
                logger.error(f"Config file not found: {self.config_path}")
                return self.get_default_config()
            
            with open(self.config_path, 'r') as f:
                config = json.load(f)
                
            # Validate required fields
            required_fields = [
                'symbol', 'interval', 'lookback_days', 'initial_balance',
                'initial_trade_amount', 'min_trade_amount', 'threshold',
                'evaluation_interval', 'sleep_interval', 'max_position_size',
                'stop_loss_pct', 'take_profit_pct', 'max_drawdown_pct',
                'position_adjustment_factor', 'risk_tolerance',
                'volatility_window', 'market_regime_threshold',
                'correlation_threshold', 'diversification_factor'
            ]
            
            missing_fields = [f for f in required_fields if f not in config]
            if missing_fields:
                logger.warning(f"Missing required config fields: {missing_fields}")
                # Use default values for missing fields
                default_config = self.get_default_config()
                for field in missing_fields:
                    config[field] = default_config[field]
                
            return config
            
        except Exception as e:
            logger.error(f"Error loading config: {e}")
            return self.get_default_config()

    def get_default_config(self) -> dict:
        """Return default configuration"""
        return {
            'symbol': 'BTC-USD',
            'interval': '1h',
            'lookback_days': 60,
            'initial_balance': 1000.0,
            'initial_trade_amount': 50.0,
            'min_trade_amount': 10.0,
            'threshold': 0.001,
            'evaluation_interval': 10,
            'sleep_interval': 3600,
            'max_position_size': 0.1,
            'stop_loss_pct': 0.02,
            'take_profit_pct': 0.03,
            'max_drawdown_pct': 0.05,
            'position_adjustment_factor': 1.5,
            'risk_tolerance': 0.01,
            'volatility_window': 20,
            'market_regime_threshold': 0.1,
            'correlation_threshold': 0.8,
            'diversification_factor': 1.5
        }

    def health_check(self):
        """Monitor bot health and trigger recovery if needed"""
        while not self.killer.kill_now:
            try:
                current_time = datetime.now()
                time_since_heartbeat = (current_time - self.last_heartbeat).total_seconds()
                
                if time_since_heartbeat > self.max_heartbeat_interval:
                    logger.warning("Heartbeat not detected - attempting recovery")
                    self.recover_bot()
                    
                time.sleep(60)  # Check every minute
                
            except Exception as e:
                logger.error(f"Error in health check: {e}")
                time.sleep(60)

    def recover_bot(self):
        """Recover from failure state"""
        try:
            logger.info("Attempting to recover bot...")
            
            # Try to restart the bot
            if self.bot:
                try:
                    self.bot.stop()
                except:
                    pass
            
            self.bot = None
            time.sleep(10)  # Wait before restarting
            self.start_bot()
            
        except Exception as e:
            logger.error(f"Error during recovery: {e}")
            logger.error(traceback.format_exc())

    def check_database_health(self) -> bool:
        """Check database connection and health"""
        try:
            db = next(get_db())
            db.execute("SELECT 1")
            return True
        except Exception as e:
            logger.error(f"Database connection error: {e}")
            return False

    def check_data_feed(self) -> bool:
        """Check if data feed is working"""
        try:
            import yfinance as yf
            ticker = yf.Ticker(self.config['symbol'])
            df = ticker.history(period='1d')
            return not df.empty
        except Exception as e:
            logger.error(f"Data feed error: {e}")
            return False

    def check_model_health(self) -> bool:
        """Check if ML models are functioning"""
        try:
            if not self.bot:
                return False
                
            # Test prediction
            df = self.bot.pipeline.fetch_data()
            if df is None or df.empty:
                return False
                
            latest_data = df.iloc[-1]
            prediction, _ = self.bot.pipeline.make_ensemble_prediction(latest_data)
            return prediction is not None
            
        except Exception as e:
            logger.error(f"Model prediction error: {e}")
            return False

    def start_bot(self):
        """Start the trading bot"""
        try:
            logger.info("Starting trading bot...")
            
            # Initialize database
            if not self.check_database_health():
                logger.error("Database connection failed - exiting")
                sys.exit(1)
                
            # Load configuration
            self.config = self.load_config()
            
            # Create bot instance
            self.bot = CryptoTradingBot(self.config)
            
            # Start health check thread
            self.health_check_thread = threading.Thread(target=self.health_check)
            self.health_check_thread.daemon = True
            self.health_check_thread.start()
            
            # Start the bot
            self.bot.run()
            
        except Exception as e:
            logger.error(f"Error starting bot: {e}")
            logger.error(traceback.format_exc())
            if self.bot:
                self.bot.stop()
            sys.exit(1)

    def run(self):
        """Main entry point"""
        try:
            # Initialize database
            init_db()
            
            # Start the bot
            self.start_bot()
            
            # Monitor for graceful shutdown
            while not self.killer.kill_now:
                time.sleep(1)
                
        except Exception as e:
            logger.error(f"Critical error in run loop: {e}")
            logger.error(traceback.format_exc())
            sys.exit(1)

if __name__ == "__main__":
    runner = TradingBotRunner()
    runner.run()
