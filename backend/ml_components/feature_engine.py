"""
Feature engineering module for crypto selection system.

Creates intelligent features from age, marketcap, and volume data
optimized for cryptocurrency price prediction.
"""

import numpy as np
import pandas as pd
from typing import Dict, List, Tuple
import logging

class FeatureEngineer:
    def __init__(self, config: Dict):
        self.config = config
        self.logger = logging.getLogger(__name__)
        self.window_sizes = config.get('window_sizes', [7, 14, 30, 90])
        self.feature_names = []

    def create_features(self, df: pd.DataFrame) -> pd.DataFrame:
        """
        Create comprehensive features from age, marketcap, and volume data.
        """
        features = df.copy()
        
        # Basic features
        features = self._add_basic_features(features)
        
        # Age-based features
        features = self._add_age_features(features)
        
        # MarketCap features
        features = self._add_marketcap_features(features)
        
        # Volume features
        features = self._add_volume_features(features)
        
        # Technical indicators
        features = self._add_technical_indicators(features)
        
        # Advanced features
        features = self._add_advanced_features(features)
        
        # Remove NaN values
        features = features.dropna()
        
        return features

    def _add_basic_features(self, df: pd.DataFrame) -> pd.DataFrame:
        """Add basic derived features."""
        features = df.copy()
        
        # Log transformations for better scaling
        if 'marketcap' in features.columns:
            features['log_marketcap'] = np.log1p(features['marketcap'])
        if 'volume' in features.columns:
            features['log_volume'] = np.log1p(features['volume'])
        
        # Ratios
        if all(col in features.columns for col in ['marketcap', 'volume']):
            features['volume_to_marketcap_ratio'] = features['volume'] / (features['marketcap'] + 1e-8)
        
        return features

    def _add_age_features(self, df: pd.DataFrame) -> pd.DataFrame:
        """Create age-based features."""
        features = df.copy()
        
        if 'age' not in features.columns:
            return features
        
        # Age momentum
        for window in self.window_sizes:
            features[f'age_momentum_{window}d'] = features['age'].pct_change(window)
        
        # Age volatility
        for window in self.window_sizes:
            features[f'age_volatility_{window}d'] = features['age'].rolling(window).std()
        
        # Age acceleration
        features['age_acceleration'] = features['age'].diff().diff()
        
        # Lifecycle stage (based on age percentiles)
        age_percentiles = features['age'].quantile([0.25, 0.5, 0.75])
        features['lifecycle_stage'] = pd.cut(
            features['age'],
            bins=[0, age_percentiles[0.25], age_percentiles[0.5], age_percentiles[0.75], np.inf],
            labels=['new', 'young', 'mature', 'old']
        )
        
        return features

    def _add_marketcap_features(self, df: pd.DataFrame) -> pd.DataFrame:
        """Create marketcap-based features."""
        features = df.copy()
        
        if 'marketcap' not in features.columns:
            return features
        
        # MarketCap momentum
        for window in self.window_sizes:
            features[f'marketcap_momentum_{window}d'] = features['marketcap'].pct_change(window)
        
        # MarketCap velocity
        for window in self.window_sizes:
            features[f'marketcap_velocity_{window}d'] = features['marketcap'].diff(window)
        
        # MarketCap acceleration
        features['marketcap_acceleration'] = features['marketcap'].diff().diff()
        
        # MarketCap rank (relative position)
        features['marketcap_rank'] = features['marketcap'].rank(pct=True)
        
        # MarketCap volatility
        for window in self.window_sizes:
            features[f'marketcap_volatility_{window}d'] = (
                features['marketcap'].rolling(window).std() / 
                features['marketcap'].rolling(window).mean()
            )
        
        return features

    def _add_volume_features(self, df: pd.DataFrame) -> pd.DataFrame:
        """Create volume-based features."""
        features = df.copy()
        
        if 'volume' not in features.columns:
            return features
        
        # Volume momentum
        for window in self.window_sizes:
            features[f'volume_momentum_{window}d'] = features['volume'].pct_change(window)
        
        # Volume velocity
        for window in self.window_sizes:
            features[f'volume_velocity_{window}d'] = features['volume'].diff(window)
        
        # Volume acceleration
        features['volume_acceleration'] = features['volume'].diff().diff()
        
        # Volume rank
        features['volume_rank'] = features['volume'].rank(pct=True)
        
        # Volume volatility
        for window in self.window_sizes:
            features[f'volume_volatility_{window}d'] = (
                features['volume'].rolling(window).std() / 
                features['volume'].rolling(window).mean()
            )
        
        return features

    def _add_technical_indicators(self, df: pd.DataFrame) -> pd.DataFrame:
        """Add technical indicators based on the features."""
        features = df.copy()
        
        # RSI-like indicator for marketcap
        if 'marketcap' in features.columns:
            features = self._add_rsi_indicator(features, 'marketcap', 'marketcap_rsi')
        
        # MACD-like indicator for volume
        if 'volume' in features.columns:
            features = self._add_macd_indicator(features, 'volume', 'volume_macd')
        
        # Bollinger Bands for marketcap
        if 'marketcap' in features.columns:
            features = self._add_bollinger_bands(features, 'marketcap', 'marketcap_bb')
        
        return features

    def _add_rsi_indicator(self, df: pd.DataFrame, column: str, name: str) -> pd.DataFrame:
        """Add RSI-like indicator."""
        features = df.copy()
        
        delta = features[column].diff()
        gain = delta.where(delta > 0, 0)
        loss = -delta.where(delta < 0, 0)
        
        for window in self.window_sizes:
            avg_gain = gain.rolling(window=window).mean()
            avg_loss = loss.rolling(window=window).mean()
            rs = avg_gain / (avg_loss + 1e-8)
            features[f'{name}_{window}d'] = 100 - (100 / (1 + rs))
        
        return features

    def _add_macd_indicator(self, df: pd.DataFrame, column: str, name: str) -> pd.DataFrame:
        """Add MACD-like indicator."""
        features = df.copy()
        
        exp1 = features[column].ewm(span=12).mean()
        exp2 = features[column].ewm(span=26).mean()
        features[f'{name}_macd'] = exp1 - exp2
        features[f'{name}_signal'] = features[f'{name}_macd'].ewm(span=9).mean()
        features[f'{name}_histogram'] = features[f'{name}_macd'] - features[f'{name}_signal']
        
        return features

    def _add_bollinger_bands(self, df: pd.DataFrame, column: str, name: str) -> pd.DataFrame:
        """Add Bollinger Bands-like indicator."""
        features = df.copy()
        
        for window in self.window_sizes:
            sma = features[column].rolling(window=window).mean()
            std = features[column].rolling(window=window).std()
            features[f'{name}_upper_{window}d'] = sma + (std * 2)
            features[f'{name}_lower_{window}d'] = sma - (std * 2)
            features[f'{name}_width_{window}d'] = features[f'{name}_upper_{window}d'] - features[f'{name}_lower_{window}d']
        
        return features

    def _add_advanced_features(self, df: pd.DataFrame) -> pd.DataFrame:
        """Add advanced features combining multiple inputs."""
        features = df.copy()
        
        # Combined features
        if all(col in features.columns for col in ['age', 'marketcap', 'volume']):
            # Age-adjusted marketcap
            features['age_adjusted_marketcap'] = features['marketcap'] / (features['age'] + 1)
            
            # Volume efficiency
            features['volume_efficiency'] = features['volume'] / (features['marketcap'] * features['age'] + 1e-8)
            
            # Market activity ratio
            features['market_activity_ratio'] = features['volume'] / (features['marketcap'] + 1e-8)
            
            # Age-volume correlation
            features['age_volume_correlation'] = features['age'].rolling(window=30).corr(features['volume'])
        
        # Momentum indicators
        required_cols = ['age', 'marketcap', 'volume']
        if all(col in features.columns for col in required_cols):
            features['combined_momentum'] = (
                features['age'].pct_change(7) * 0.2 +
                features['marketcap'].pct_change(7) * 0.5 +
                features['volume'].pct_change(7) * 0.3
            )
        
        return features

    def get_feature_names(self) -> List[str]:
        """Return list of feature names."""
        return self.feature_names

    def validate_features(self, features: pd.DataFrame) -> bool:
        """Validate that features are properly formatted."""
        if features.empty:
            self.logger.error("Features DataFrame is empty")
            return False
        
        if features.isnull().values.any():
            self.logger.error("Features contain NaN values")
            return False
        
        if np.isinf(features.values).any():
            self.logger.error("Features contain infinite values")
            return False
        
        return True
