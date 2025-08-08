import pandas as pd
import numpy as np
import talib

from typing import Dict, Any
import logging

logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')

class FeatureEngine:
    def __init__(self, config: Dict = None):
        self.config = config if config is not None else {}

    def _get_safe_window(self, df_length: int, default_window: int) -> int:
        """
        Calculates a safe window size for TA indicators based on DataFrame length.
        Ensures window is at least 1 and not greater than df_length.
        """
        return max(1, min(df_length, default_window))

    def engineer_features(self, data: pd.DataFrame) -> pd.DataFrame:
        """
        Generates intelligent features from raw crypto data.
        Expected input data columns: 'close', 'volume', 'market_cap', 'date_added' (or similar for age).
        """
        df = data.copy()

        if df.empty:
            # logging.warning("Input DataFrame is empty. Returning empty DataFrame.")
            return pd.DataFrame() # Return empty DataFrame if input is empty

        # Ensure necessary columns exist
        required_cols = ['close', 'volume', 'market_cap']
        for col in required_cols:
            if col not in df.columns:
                raise ValueError(f"Missing required column: {col}")

        # Convert timestamp to datetime if not already
        if 'timestamp' in df.columns and not pd.api.types.is_datetime64_any_dtype(df['timestamp']):
            df['timestamp'] = pd.to_datetime(df['timestamp']).dt.tz_localize(None)

        # Ensure numerical columns are clean before TA calculations
        numeric_cols = ['open', 'high', 'low', 'close', 'volume', 'market_cap']
        for col in numeric_cols:
            if col in df.columns:
                df[col] = pd.to_numeric(df[col], errors='coerce')
                df[col] = df[col].ffill().bfill()
                df[col] = df[col].fillna(0) # Fill any remaining NaNs with 0

        df_length = len(df)

        # --- Age-based Features ---
        if 'date_added' in df.columns:
            df['date_added'] = pd.to_datetime(df['date_added'])
            df['coin_age_days'] = (df['timestamp'] - df['date_added']).dt.days
            df['coin_age_momentum'] = df['coin_age_days'].diff() # Simple momentum
            
            # Age-adjusted volatility (example: rolling std dev of price, adjusted by age)
            window_bollinger = self._get_safe_window(df_length, 20)
            upper_band, middle_band, lower_band = talib.BBANDS(df['close'], timeperiod=window_bollinger)
            # Calculate Bollinger Bandwidth and adjust by age
            df['age_adjusted_volatility'] = ((upper_band - lower_band) / middle_band) / (df['coin_age_days'] + 1)
            
            # Lifecycle stage indicators (simple bins)
            df['lifecycle_stage'] = pd.cut(df['coin_age_days'], bins=[-1, 7, 30, 90, 365, np.inf], 
                                            labels=['new', 'week_old', 'month_old', 'quarter_old', 'year_plus']).astype(str)
        else:
            df['coin_age_days'] = 0 # Default if no date_added
            df['coin_age_momentum'] = 0
            # window_bollinger = self._get_safe_window(df_length, 20)
            # df['age_adjusted_volatility'] = ta.volatility.bollinger_wband(df['close'], window=window_bollinger)
            window_bollinger = self._get_safe_window(df_length, 20)
            upper_band, middle_band, lower_band = talib.BBANDS(df['close'], timeperiod=window_bollinger)
            df['age_adjusted_volatility'] = ((upper_band - lower_band) / middle_band) / (df['coin_age_days'] + 1)
            df['lifecycle_stage'] = 'unknown'

        # --- MarketCap Features ---
        df['market_cap_log'] = np.log1p(df['market_cap'])
        df['market_cap_velocity'] = df['market_cap'].diff()
        df['market_cap_acceleration'] = df['market_cap_velocity'].diff()
        # Relative market position (requires broader market data, placeholder for now)
        df['relative_market_cap'] = df['market_cap'] / df['market_cap'].max() # Relative to max in this dataset

        # --- Volume Features ---
        df['volume_log'] = np.log1p(df['volume'])
        df['volume_change'] = df['volume'].pct_change()
        
        window_volume_sma = self._get_safe_window(df_length, 14)
        df['volume_moving_average'] = df['volume'].rolling(window=window_volume_sma).mean()
        
        # On-Balance Volume (OBV)
        # OBV = OBV_prev + volume (if close > close_prev)
        # OBV = OBV_prev - volume (if close < close_prev)
        # OBV = OBV_prev (if close == close_prev)
        
        # Calculate price change direction
        price_change = df['close'].diff()
        
        # Vectorized OBV calculation
        obv_direction = np.sign(price_change).fillna(0) # 1 for up, -1 for down, 0 for no change
        df['on_balance_volume'] = (df['volume'] * obv_direction).cumsum()
        
        # Chaikin Money Flow (CMF)
        # CMF = sum(MFV) / sum(Volume)
        # MFV = ((Close - Low) - (High - Close)) / (High - Low) * Volume
        
        # Money Flow Multiplier
        # high_low_diff = df['high'] - df['low']
        # high_low_diff = high_low_diff.replace(0, np.nan)
        # money_flow_multiplier = ((df['close'] - df['low']) - (df['high'] - df['close'])) / high_low_diff
        # money_flow_volume = money_flow_multiplier * df['volume']
        # window_cmf = self._get_safe_window(df_length, 20) # CMF typically uses a 20-period window
        # df['chaikin_money_flow'] = money_flow_volume.rolling(window=window_cmf).sum() / df['volume'].rolling(window=window_cmf).sum()
        # Manual Chaikin Money Flow (CMF) calculation
        # MFV = ((Close - Low) - (High - Close)) / (High - Low) * Volume
        # CMF = sum(MFV) / sum(Volume)
        
        high_low_diff = df['high'] - df['low']
        # Avoid division by zero, replace 0 with NaN then fill with a small number or 1 to prevent errors
        high_low_diff = high_low_diff.replace(0, np.nan).fillna(1e-9) 
        
        money_flow_multiplier = ((df['close'] - df['low']) - (df['high'] - df['close'])) / high_low_diff
        money_flow_volume = money_flow_multiplier * df['volume']
        
        window_cmf = self._get_safe_window(df_length, 20) # CMF typically uses a 20-period window
        
        # Calculate sum of Money Flow Volume and sum of Volume over the window
        sum_mfv = money_flow_volume.rolling(window=window_cmf).sum()
        sum_volume = df['volume'].rolling(window=window_cmf).sum()
        
        # Avoid division by zero for CMF
        df['chaikin_money_flow'] = sum_mfv / sum_volume.replace(0, np.nan).fillna(1e-9)
        df['chaikin_money_flow'] = df['chaikin_money_flow'].fillna(0) # Fill any NaNs from initial periods
        
        # Volume-weighted price action (VWAP)
        # VWAP = Cumulative(Typical Price * Volume) / Cumulative(Volume)
        # Typical Price = (High + Low + Close) / 3
        if all(col in df.columns for col in ['high', 'low', 'open']):
            df['typical_price'] = (df['high'] + df['low'] + df['close']) / 3
            df['typical_price_volume'] = df['typical_price'] * df['volume']
            df['cumulative_typical_price_volume'] = df['typical_price_volume'].cumsum()
            df['cumulative_volume'] = df['volume'].cumsum()
            df['vwap'] = df['cumulative_typical_price_volume'] / df['cumulative_volume']
        else:
            df['vwap'] = df['close'] # Fallback if not enough data

        # --- Technical Indicators ---
        # Momentum Indicators
        # Relative Strength Index (RSI)
        # delta = df['close'].diff()
        # gain = delta.where(delta > 0, 0)
        # loss = -delta.where(delta < 0, 0)
        # 
        # window_rsi = self._get_safe_window(df_length, 14)
        # 
        # avg_gain = gain.rolling(window=window_rsi).mean()
        # avg_loss = loss.rolling(window=window_rsi).mean()
        # 
        # rs = avg_gain / avg_loss
        # df['rsi'] = 100 - (100 / (1 + rs))
        window_rsi = self._get_safe_window(df_length, 14)
        df['rsi'] = talib.RSI(df['close'], timeperiod=window_rsi)
        
        # Stochastic Oscillator
        # %K = (Current Close - Lowest Low) / (Highest High - Lowest Low) * 100
        # %D = SMA(%K, 3)
        
        # window_stoch = self._get_safe_window(df_length, 14)
        # 
        # lowest_low = df['low'].rolling(window=window_stoch).min()
        # highest_high = df['high'].rolling(window=window_stoch).max()
        # 
        # # Avoid division by zero
        # high_low_diff = highest_high - lowest_low
        # high_low_diff = high_low_diff.replace(0, np.nan)
        # 
        # df['stoch_oscillator'] = ((df['close'] - lowest_low) / high_low_diff) * 100
        # df['stoch_oscillator_d'] = df['stoch_oscillator'].rolling(window=3).mean() # %D is typically a 3-period SMA of %K
        window_stoch = self._get_safe_window(df_length, 14)
        df['stoch_oscillator'], df['stoch_oscillator_d'] = talib.STOCH(df['high'], df['low'], df['close'], 
                                                                    fastk_period=window_stoch, slowk_period=3, 
                                                                    slowk_matype=0, slowd_period=3, slowd_matype=0)
        
        # Williams %R
        # %R = (Highest High - Close) / (Highest High - Lowest Low) * -100
        
        # window_williams = self._get_safe_window(df_length, 14)
        # 
        # highest_high_w = df['high'].rolling(window=window_williams).max()
        # lowest_low_w = df['low'].rolling(window=window_williams).min()
        # 
        # # Avoid division by zero
        # high_low_diff_w = highest_high_w - lowest_low_w
        # high_low_diff_w = high_low_diff_w.replace(0, np.nan)
        # 
        # df['williams_r'] = ((highest_high_w - df['close']) / high_low_diff_w) * -100
        window_williams = self._get_safe_window(df_length, 14)
        df['williams_r'] = talib.WILLR(df['high'], df['low'], df['close'], timeperiod=window_williams)

        # Volatility Indicators
        # Average True Range (ATR)
        # TR = max[(High - Low), abs(High - Close_prev), abs(Low - Close_prev)]
        # ATR = SMA(TR, window)
        
        # window_atr = self._get_safe_window(df_length, 14)
        # 
        # # Calculate True Range (TR)
        # high_low = df['high'] - df['low']
        # high_close_prev = abs(df['high'] - df['close'].shift(1))
        # low_close_prev = abs(df['low'] - df['close'].shift(1))
        # 
        # true_range = pd.DataFrame({'hl': high_low, 'hcp': high_close_prev, 'lcp': low_close_prev}).max(axis=1)
        # 
        # df['atr'] = true_range.rolling(window=window_atr).mean()
        window_atr = self._get_safe_window(df_length, 14)
        df['atr'] = talib.ATR(df['high'], df['low'], df['close'], timeperiod=window_atr)
        
        # Bollinger Bands
        # window_bollinger_bands = self._get_safe_window(df_length, 20)
        # 
        # rolling_mean_bb = df['close'].rolling(window=window_bollinger_bands).mean()
        # rolling_std_bb = df['close'].rolling(window=window_bollinger_bands).std()
        # 
        # df['bollinger_hband'] = rolling_mean_bb + (2 * rolling_std_bb)
        # df['bollinger_lband'] = rolling_mean_bb - (2 * rolling_std_bb)
        window_bollinger_bands = self._get_safe_window(df_length, 20)
        window_bollinger_bands = self._get_safe_window(df_length, 20)
        df['bollinger_hband'], df['bollinger_mband'], df['bollinger_lband'] = talib.BBANDS(df['close'], timeperiod=window_bollinger_bands)

        # Trend Indicators
        # Moving Average Convergence Divergence (MACD)
        # MACD Line: (12-day EMA - 26-day EMA)
        # Signal Line: (9-day EMA of MACD Line)
        
        # Calculate EMAs for MACD
        # ema_12 = df['close'].ewm(span=12, adjust=False).mean()
        # ema_26 = df['close'].ewm(span=26, adjust=False).mean()
        # 
        # df['macd'] = ema_12 - ema_26
        # df['macd_signal'] = df['macd'].ewm(span=9, adjust=False).mean()
        df['macd'], df['macd_signal'], df['macd_hist'] = talib.MACD(df['close'], fastperiod=12, slowperiod=26, signalperiod=9)

        # window_ema_20 = self._get_safe_window(df_length, 20)
        # df['ema_20'] = df['close'].ewm(span=window_ema_20, adjust=False).mean()
        window_ema_20 = self._get_safe_window(df_length, 20)
        df['ema_20'] = talib.EMA(df['close'], timeperiod=window_ema_20)
        
        # window_ema_50 = self._get_safe_window(df_length, 50)
        # df['ema_50'] = df['close'].ewm(span=window_ema_50, adjust=False).mean()
        window_ema_50 = self._get_safe_window(df_length, 50)
        df['ema_50'] = talib.EMA(df['close'], timeperiod=window_ema_50)
        
        # window_ema_200 = self._get_safe_window(df_length, 200)
        # df['ema_200'] = df['close'].ewm(span=window_ema_200, adjust=False).mean()
        window_ema_200 = self._get_safe_window(df_length, 200)
        df['ema_200'] = talib.EMA(df['close'], timeperiod=window_ema_200)

        # Fill any remaining NaN values that might result from TA calculations
        df = df.ffill().bfill()
        df = df.replace([np.inf, -np.inf], np.nan).fillna(0) # Replace inf with 0 after ffill/bfill

        return df

if __name__ == "__main__":
    # Example Usage (dummy data for demonstration)
    
    # Create dummy data resembling historical crypto data
    data = {
        'timestamp': pd.to_datetime(pd.date_range(start='2023-01-01', periods=200, freq='D')),
        'open': np.random.rand(200) * 100 + 1000,
        'high': np.random.rand(200) * 100 + 1050,
        'low': np.random.rand(200) * 100 + 950,
        'close': np.random.rand(200) * 100 + 1000,
        'volume': np.random.rand(200) * 1000000 + 100000,
        'market_cap': np.random.rand(200) * 1000000000 + 100000000,
        'date_added': pd.to_datetime(['2022-12-15'] * 200) # Assume all coins added on this date for simplicity
    }
    dummy_df = pd.DataFrame(data)

    feature_engine = FeatureEngine()
    features_df = feature_engine.engineer_features(dummy_df)

    # Test with missing date_added
    data_no_date_added = {
        'timestamp': pd.to_datetime(pd.date_range(start='2023-01-01', periods=10, freq='D')),
        'open': np.random.rand(10) * 100 + 1000,
        'high': np.random.rand(10) * 100 + 1050,
        'low': np.random.rand(10) * 100 + 950,
        'close': np.random.rand(10) * 100 + 1000,
        'volume': np.random.rand(10) * 1000000 + 100000,
        'market_cap': np.random.rand(10) * 1000000000 + 100000000,
    }
    dummy_df_no_date_added = pd.DataFrame(data_no_date_added)
    features_no_date_added_df = feature_engine.engineer_features(dummy_df_no_date_added)
