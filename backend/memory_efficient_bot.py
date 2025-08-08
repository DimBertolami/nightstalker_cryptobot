import os
import sys
import time
import logging
import psutil
import threading
import numpy as np
import pandas as pd
from datetime import datetime, timedelta
import yfinance as yf
from typing import Optional, Dict, Any
import requests
import json

# Memory monitoring
MEMORY_THRESHOLD = 0.8
SWAP_THRESHOLD = 0.5

class MemoryMonitor:
    def __init__(self, config: Dict):
        self.config = config['memory_settings']
        self.last_cleanup = time.time()
        self.cleanup_interval = self.config['cleanup_interval_seconds']
        self.consecutive_errors = 0
        
    def check_memory(self) -> Dict:
        """Comprehensive memory check with multiple thresholds"""
        mem = psutil.virtual_memory()
        swap = psutil.swap_memory()
        
        result = {
            'status': 'ok',
            'memory_usage': mem.percent,
            'swap_usage': swap.percent,
            'recommendations': []
        }
        
        # Check memory thresholds
        if mem.percent > self.config['max_memory_usage'] * 100:
            result['status'] = 'warning'
            result['recommendations'].append('reduce_load')
            
        if mem.percent > self.config['max_memory_usage'] * 100 * 1.1:  # 10% above threshold
            result['status'] = 'critical'
            result['recommendations'].append('emergency_cleanup')
            
        # Check swap usage
        if swap.percent > self.config['max_swap_usage'] * 100:
            result['status'] = 'warning'
            result['recommendations'].append('reduce_swap')
            
        return result
        
    def cleanup_memory(self):
        """Enhanced cleanup with multiple strategies"""
        if time.time() - self.last_cleanup > self.cleanup_interval:
            self.last_cleanup = time.time()
            
            # Implement cleanup strategies
            if 'reduce_load' in self.config['low_memory_actions']:
                self.reduce_memory_load()
                
            if 'emergency_cleanup' in self.config['low_memory_actions']:
                self.emergency_cleanup()
                
            logging.info(f"Memory cleanup completed. Status: {self.check_memory()['status']}")
            
    def reduce_memory_load(self):
        """Reduce memory usage by limiting data"""
        try:
            # Implement memory reduction strategies
            logging.info("Reducing memory load...")
            # Add more memory reduction strategies here
        except Exception as e:
            logging.error(f"Error during memory reduction: {e}")
            
    def emergency_cleanup(self):
        """Emergency cleanup when memory is critically low"""
        try:
            # Implement emergency cleanup strategies
            logging.info("Performing emergency memory cleanup...")
            # Add emergency cleanup strategies here
        except Exception as e:
            logging.error(f"Error during emergency cleanup: {e}")

class RateLimiter:
    def __init__(self, calls_per_minute: int, error_config: Dict):
        self.calls_per_minute = calls_per_minute
        self.calls = 0
        self.last_reset = time.time()
        self.error_config = error_config
        self.consecutive_errors = 0
        self.last_error_time = 0
        
    def can_make_call(self) -> bool:
        """Rate limiting with error handling"""
        current_time = time.time()
        
        # Handle rate limits
        if current_time - self.last_reset > 60:
            self.calls = 0
            self.last_reset = current_time
            return True
            
        if self.calls < self.calls_per_minute:
            self.calls += 1
            return True
            
        # Handle error conditions
        if self.consecutive_errors >= self.error_config['max_consecutive_errors']:
            cooldown_time = self.error_config['error_cooldown_seconds']
            if current_time - self.last_error_time < cooldown_time:
                return False
                
        return False
        
    def register_error(self):
        """Register an API error"""
        self.consecutive_errors += 1
        self.last_error_time = time.time()
        
    def reset_error_count(self):
        """Reset error count after successful call"""
        self.consecutive_errors = 0

class NetworkErrorTracker:
    def __init__(self, config: Dict):
        self.config = config
        self.last_error_time = 0
        self.consecutive_errors = 0
        self.error_cooldown = self.config['error_handling']['network']['error_cooldown_seconds']
        
    def handle_network_error(self, error: Exception) -> bool:
        """Handle network errors with retry logic"""
        current_time = time.time()
        
        # Track consecutive errors
        self.consecutive_errors += 1
        self.last_error_time = current_time
        
        # Check if we should give up
        if self.consecutive_errors > self.config['error_handling']['network']['max_retries']:
            logging.error(f"Too many consecutive network errors: {self.consecutive_errors}")
            return False
            
        # Calculate backoff time
        backoff = min(
            self.config['error_handling']['network']['backoff_factor'] ** self.consecutive_errors,
            self.config['error_handling']['network']['timeout_seconds']
        )
        
        logging.warning(f"Network error: {error}. Retrying in {backoff} seconds...")
        time.sleep(backoff)
        return True

class MemoryEfficientTradingBot:
    def __init__(self, config_path: str):
        self.config = self.load_config(config_path)
        self.memory_monitor = MemoryMonitor(self.config)
        self.rate_limiters = {
            'yfinance': RateLimiter(
                self.config['api_settings']['rate_limits']['yfinance']['calls_per_minute'],
                self.config['api_settings']['error_config']
            ),
            'binance': RateLimiter(
                self.config['api_settings']['rate_limits']['binance']['calls_per_minute'],
                self.config['api_settings']['error_config']
            ),
            'coingecko': RateLimiter(
                self.config['api_settings']['rate_limits']['coingecko']['calls_per_minute'],
                self.config['api_settings']['error_config']
            )
        }
        self.network_error_tracker = NetworkErrorTracker(self.config)
        self.last_data_cleanup = time.time()
        self.data_cache = {}
        self.error_handlers = {
            'memory': self.handle_memory_error,
            'network': self.handle_network_error,
            'api': self.handle_api_error
        }

    def load_config(self, config_path: str) -> Dict:
        """Load and validate configuration with error handling"""
        try:
            # Get absolute path to config file
            config_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', config_path)
            
            # Load config
            with open(config_path, 'r') as f:
                config = json.load(f)
            
            # Validate required fields
            required_fields = [
                'memory_settings',
                'api_settings',
                'trading_settings',
                'error_handling'
            ]
            
            for field in required_fields:
                if field not in config:
                    raise ValueError(f"Missing required field: {field}")
            
            return config
            
        except FileNotFoundError:
            raise ValueError(f"Config file not found: {config_path}")
        except json.JSONDecodeError:
            raise ValueError(f"Invalid JSON in config file: {config_path}")
        except Exception as e:
            raise ValueError(f"Failed to load config: {str(e)}")
            
    def validate_config(self, config: Dict):
        """Validate configuration values"""
        if not isinstance(config, dict):
            raise ValueError("Config must be a dictionary")
            
        required_sections = ['memory_settings', 'api_settings', 'trading_settings', 'error_handling']
        for section in required_sections:
            if section not in config:
                raise ValueError(f"Missing required config section: {section}")
                
    def handle_config_error(self):
        """Handle configuration errors"""
        logging.error("Critical configuration error. Shutting down...")
        sys.exit(1)
        
    def fetch_data(self, source: str, symbol: str, interval: str, period: str = "15d") -> Optional[pd.DataFrame]:
        """Enhanced data fetching with comprehensive error handling"""
        if not self.memory_monitor.check_memory()['status'] == 'ok':
            logging.warning("Memory usage too high, skipping data fetch")
            return None
            
        if not self.rate_limiters[source].can_make_call():
            logging.warning(f"Rate limit exceeded for {source}")
            return None
            
        try:
            if source == 'yfinance':
                df = yf.download(tickers=symbol, interval=interval, period=period)
                if df.empty:
                    logging.warning(f"Empty data received from {source}")
                    return None
                    
                df = df.tail(self.config['memory_settings']['max_data_points'])
                return df
                
            # Add other data sources here
            return None
            
        except requests.exceptions.RequestException as e:
            if not self.network_error_tracker.handle_network_error(e):
                return None
            
        except Exception as e:
            logging.error(f"Error fetching data from {source}: {e}")
            self.rate_limiters[source].register_error()
            return None
            
    def handle_memory_error(self, error: Exception):
        """Handle memory-related errors"""
        memory_status = self.memory_monitor.check_memory()
        
        if memory_status['memory_usage'] > self.config['error_handling']['memory']['threshold'] * 100:
            logging.warning("High memory usage. Reducing load...")
            if self.config['error_handling']['memory']['actions']['reduce_load']:
                self.memory_monitor.reduce_memory_load()
            
        if memory_status['swap_usage'] > self.config['error_handling']['memory']['swap_threshold'] * 100:
            logging.warning("High swap usage. Performing emergency cleanup...")
            if self.config['error_handling']['memory']['actions']['emergency_cleanup']:
                self.memory_monitor.emergency_cleanup()
                
        return memory_status['memory_usage'] < self.config['error_handling']['memory']['threshold'] * 100
        
    def handle_network_error(self, error: Exception):
        """Handle network-related errors"""
        max_retries = self.config['error_handling']['network']['max_retries']
        backoff_factor = self.config['error_handling']['network']['backoff_factor']
        timeout = self.config['error_handling']['network']['timeout_seconds']
        
        if not self.network_error_tracker.handle_network_error(error):
            logging.error(f"Too many network errors after {max_retries} attempts")
            return False
            
        return True
        
    def handle_api_error(self, error: Exception):
        """Handle API-related errors"""
        if isinstance(error, requests.exceptions.Timeout):
            logging.warning("API timeout. Retrying...")
            return True
            
        if isinstance(error, requests.exceptions.RateLimitExceeded):
            logging.warning("API rate limit exceeded. Waiting...")
            time.sleep(self.config['api_settings']['error_config']['rate_limit_wait'])
            return True
            
        logging.error(f"API error: {error}")
        self.rate_limiters['api'].register_error()
        return False
        
    def process_data(self, df: pd.DataFrame) -> pd.DataFrame:
        """Process data with memory-efficient operations"""
        if df is None or df.empty:
            return df
            
        # Use in-place operations to save memory
        df = df.copy()
        df['close'] = df['close'].astype(np.float32)
        df['volume'] = df['volume'].astype(np.float32)
        
        # Calculate indicators in a memory-efficient way
        df['sma_20'] = df['close'].rolling(window=20).mean()
        df['sma_50'] = df['close'].rolling(window=50).mean()
        
        return df.tail(self.config['memory_settings']['max_data_points'])
        
    def make_prediction(self, df: pd.DataFrame) -> Dict:
        """Make prediction using lightweight model"""
        if df is None or df.empty:
            return {'signal': 'hold'}
            
        # Use simple moving averages for prediction
        last_sma_20 = df['sma_20'].iloc[-1]
        last_sma_50 = df['sma_50'].iloc[-1]
        
        if last_sma_20 > last_sma_50:
            return {'signal': 'buy'}
        elif last_sma_20 < last_sma_50:
            return {'signal': 'sell'}
        return {'signal': 'hold'}
        
    def execute_trade(self, prediction: Dict, current_price: float) -> bool:
        """Execute trade with memory monitoring"""
        if not self.memory_monitor.check_memory()['status'] == 'ok':
            logging.warning("Memory usage too high, skipping trade")
            return False
            
        # Implement lightweight trading logic here
        logging.info(f"Executing trade: {prediction['signal']} at price: {current_price}")
        return True
        
    def run(self):
        """Main bot loop with enhanced error handling"""
        error_count = 0
        max_errors = self.config['error_handling']['system']['max_restarts']
        
        while error_count < max_errors:
            try:
                # Check memory usage
                memory_status = self.memory_monitor.check_memory()
                if memory_status['memory_usage'] > self.config['error_handling']['memory']['threshold'] * 100:
                    if not self.handle_memory_error(Exception(memory_status)):
                        error_count += 1
                        time.sleep(self.config['error_handling']['system']['restart_delay_seconds'])
                        continue
                
                # Fetch data
                df = self.fetch_data(
                    source='yfinance',
                    symbol='BTC-USD',
                    interval='1h',
                    period='15d'
                )
                
                if df is None:
                    logging.warning("Failed to fetch data")
                    time.sleep(60)
                    continue
                
                # Process data
                df = self.process_data(df)
                
                # Make prediction
                prediction = self.make_prediction(df)
                
                # Execute trade
                if prediction['signal'] != 'hold':
                    if not self.execute_trade(prediction, df['close'].iloc[-1]):
                        error_count += 1
                        time.sleep(self.config['error_handling']['system']['restart_delay_seconds'])
                        continue
                
                # Cleanup memory
                self.memory_monitor.cleanup_memory()
                
                # Wait for next interval
                time.sleep(self.config['trading_settings']['update_interval_seconds'])
                
            except KeyboardInterrupt:
                logging.info("Bot stopped by user")
                self.stop()
                break
                
            except Exception as e:
                logging.error(f"Error in trading loop: {e}")
                error_count += 1
                
                # Check if we should restart
                if error_count >= max_errors:
                    logging.error("Maximum error count reached. Shutting down...")
                    break
                    
                # Wait before retry
                time.sleep(self.config['error_handling']['system']['restart_delay_seconds'])
                
    def stop(self):
        """Clean up resources with error handling"""
        try:
            logging.info("Shutting down trading bot")
            self.memory_monitor.cleanup_memory()
            # Add more cleanup operations here
        except Exception as e:
            logging.error(f"Error during shutdown: {e}")
