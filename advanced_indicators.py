import numpy as np
import pandas as pd
from typing import Dict, List, Tuple
import talib
import math
from scipy.stats import norm
from sklearn.preprocessing import StandardScaler
from sklearn.decomposition import PCA
import logging

logger = logging.getLogger(__name__)

class AdvancedIndicators:
    def __init__(self, config: Dict):
        self.config = config
        self.scaler = StandardScaler()
        self.pca = PCA(n_components=3)
        self.indicators = {
            'trend': [],
            'momentum': [],
            'volatility': [],
            'sentiment': []
        }

    def calculate_indicators(self, df: pd.DataFrame) -> pd.DataFrame:
        """Calculate all advanced indicators"""
        try:
            # Calculate trend indicators
            df = self._calculate_trend_indicators(df)
            
            # Calculate momentum indicators
            df = self._calculate_momentum_indicators(df)
            
            # Calculate volatility indicators
            df = self._calculate_volatility_indicators(df)
            
            # Calculate sentiment indicators
            df = self._calculate_sentiment_indicators(df)
            
            # Normalize and reduce dimensionality
            df = self._normalize_and_reduce(df)
            
            return df
            
        except Exception as e:
            logger.error(f"Error calculating indicators: {e}")
            return df

    def _calculate_trend_indicators(self, df: pd.DataFrame) -> pd.DataFrame:
        """Calculate advanced trend indicators"""
        try:
            # Calculate multiple moving averages
            for period in [10, 20, 50, 100, 200]:
                df[f'SMA_{period}'] = df['Close'].rolling(window=period).mean()
                df[f'EMA_{period}'] = df['Close'].ewm(span=period, adjust=False).mean()
            
            # Calculate MACD with multiple configurations
            for fast, slow, signal in [(12, 26, 9), (8, 21, 5), (10, 30, 7)]:
                macd, signal, hist = talib.MACD(
                    df['Close'],
                    fastperiod=fast,
                    slowperiod=slow,
                    signalperiod=signal
                )
                df[f'MACD_{fast}_{slow}_{signal}'] = macd
                df[f'MACD_Signal_{fast}_{slow}_{signal}'] = signal
            
            # Calculate Ichimoku Cloud
            df['ichimoku_tenkan'] = talib.EMA(df['Close'], timeperiod=9)
            df['ichimoku_kijun'] = talib.EMA(df['Close'], timeperiod=26)
            df['ichimoku_senkou_a'] = (df['ichimoku_tenkan'] + df['ichimoku_kijun']) / 2
            df['ichimoku_senkou_b'] = talib.EMA(df['Close'], timeperiod=52)
            
            return df
            
        except Exception as e:
            logger.error(f"Error calculating trend indicators: {e}")
            return df

    def _calculate_momentum_indicators(self, df: pd.DataFrame) -> pd.DataFrame:
        """Calculate advanced momentum indicators"""
        try:
            # Calculate RSI with multiple periods
            for period in [7, 14, 21]:
                df[f'RSI_{period}'] = talib.RSI(df['Close'], timeperiod=period)
            
            # Calculate Stochastic Oscillator
            k, d = talib.STOCH(
                df['High'],
                df['Low'],
                df['Close'],
                fastk_period=14,
                slowk_period=3,
                slowd_period=3
            )
            df['Stoch_K'] = k
            df['Stoch_D'] = d
            
            # Calculate Williams %R
            df['Williams_R'] = talib.WILLR(
                df['High'],
                df['Low'],
                df['Close'],
                timeperiod=14
            )
            
            # Calculate Ultimate Oscillator
            df['Ultimate_Osc'] = talib.ULTOSC(
                df['High'],
                df['Low'],
                df['Close'],
                timeperiod1=7,
                timeperiod2=14,
                timeperiod3=28
            )
            
            return df
            
        except Exception as e:
            logger.error(f"Error calculating momentum indicators: {e}")
            return df

    def _calculate_volatility_indicators(self, df: pd.DataFrame) -> pd.DataFrame:
        """Calculate advanced volatility indicators"""
        try:
            # Calculate multiple volatility measures
            for period in [10, 20, 50]:
                df[f'Volatility_{period}'] = df['Close'].rolling(window=period).std()
                df[f'ATR_{period}'] = talib.ATR(
                    df['High'],
                    df['Low'],
                    df['Close'],
                    timeperiod=period
                )
            
            # Calculate Bollinger Bands with multiple configurations
            for period in [20, 50]:
                upper, middle, lower = talib.BBANDS(
                    df['Close'],
                    timeperiod=period,
                    nbdevup=2,
                    nbdevdn=2
                )
                df[f'BB_Upper_{period}'] = upper
                df[f'BB_Lower_{period}'] = lower
            
            # Calculate Keltner Channels
            df['Keltner_Middle'] = df['Close'].ewm(span=20).mean()
            df['Keltner_Range'] = (df['High'] - df['Low']).rolling(window=20).mean()
            df['Keltner_Upper'] = df['Keltner_Middle'] + 2 * df['Keltner_Range']
            df['Keltner_Lower'] = df['Keltner_Middle'] - 2 * df['Keltner_Range']
            
            return df
            
        except Exception as e:
            logger.error(f"Error calculating volatility indicators: {e}")
            return df

    def _calculate_sentiment_indicators(self, df: pd.DataFrame) -> pd.DataFrame:
        """Calculate advanced sentiment indicators"""
        try:
            # Calculate Volume Profile
            df['Volume_Profile'] = df['Volume'].rolling(window=20).mean()
            
            # Calculate Money Flow Index
            df['MFI'] = talib.MFI(
                df['High'],
                df['Low'],
                df['Close'],
                df['Volume'],
                timeperiod=14
            )
            
            # Calculate On-Balance Volume
            df['OBV'] = talib.OBV(df['Close'], df['Volume'])
            
            # Calculate Chaikin Money Flow
            df['CMF'] = talib.ADOSC(
                df['High'],
                df['Low'],
                df['Close'],
                df['Volume'],
                fastperiod=3,
                slowperiod=10
            )
            
            return df
            
        except Exception as e:
            logger.error(f"Error calculating sentiment indicators: {e}")
            return df

    def _normalize_and_reduce(self, df: pd.DataFrame) -> pd.DataFrame:
        """Normalize and reduce dimensionality of indicators"""
        try:
            # Normalize all indicator columns
            indicator_cols = [col for col in df.columns if col not in ['Date', 'Open', 'High', 'Low', 'Close', 'Volume']]
            df[indicator_cols] = self.scaler.fit_transform(df[indicator_cols])
            
            # Reduce dimensionality using PCA
            reduced_features = self.pca.fit_transform(df[indicator_cols])
            
            # Add principal components to dataframe
            for i, component in enumerate(reduced_features.T):
                df[f'PC_{i+1}'] = component
            
            return df
            
        except Exception as e:
            logger.error(f"Error normalizing and reducing: {e}")
            return df

class DecisionEngine:
    def __init__(self, config: Dict):
        self.config = config
        self.indicators = AdvancedIndicators(config)
        self.logger = logging.getLogger(__name__)
        self.decision_strategies = {
            'trend': self._trend_strategy,
            'momentum': self._momentum_strategy,
            'volatility': self._volatility_strategy,
            'sentiment': self._sentiment_strategy
        }
        self.strategy_weights = {
            'trend': 0.3,
            'momentum': 0.3,
            'volatility': 0.2,
            'sentiment': 0.2
        }

    def analyze_market(self, df: pd.DataFrame) -> Dict:
        """Analyze market conditions and generate insights"""
        try:
            # Calculate indicators
            df = self.indicators.calculate_indicators(df)
            
            # Get latest data point
            latest = df.iloc[-1]
            
            # Generate insights
            insights = {
                'trend': self._analyze_trend(latest),
                'momentum': self._analyze_momentum(latest),
                'volatility': self._analyze_volatility(latest),
                'sentiment': self._analyze_sentiment(latest),
                'market_regime': self._determine_market_regime(latest)
            }
            
            return insights
            
        except Exception as e:
            self.logger.error(f"Error analyzing market: {e}")
            return {}

    def _analyze_trend(self, data: pd.Series) -> Dict:
        """Analyze trend indicators"""
        insights = {
            'direction': 'Sideways',
            'strength': 0.5,
            'confidence': 0.5
        }
        
        # Check multiple moving averages
        if data['SMA_10'] > data['SMA_50'] > data['SMA_200']:
            insights['direction'] = 'Bullish'
            insights['strength'] = 0.8
        elif data['SMA_10'] < data['SMA_50'] < data['SMA_200']:
            insights['direction'] = 'Bearish'
            insights['strength'] = 0.8
            
        # Check MACD signals
        if data['MACD_12_26_9'] > data['MACD_Signal_12_26_9']:
            insights['confidence'] += 0.2
        
        return insights

    def _analyze_momentum(self, data: pd.Series) -> Dict:
        """Analyze momentum indicators"""
        insights = {
            'overbought': False,
            'oversold': False,
            'strength': 0.5,
            'confidence': 0.5
        }
        
        # Check RSI levels
        if data['RSI_14'] > 70:
            insights['overbought'] = True
            insights['strength'] = 0.8
        elif data['RSI_14'] < 30:
            insights['oversold'] = True
            insights['strength'] = 0.8
            
        # Check Stochastic Oscillator
        if data['Stoch_K'] > 80 and data['Stoch_D'] > 80:
            insights['overbought'] = True
            insights['confidence'] += 0.2
        elif data['Stoch_K'] < 20 and data['Stoch_D'] < 20:
            insights['oversold'] = True
            insights['confidence'] += 0.2
            
        return insights

    def _analyze_volatility(self, data: pd.Series) -> Dict:
        """Analyze volatility indicators"""
        insights = {
            'high_volatility': False,
            'volatility_trend': 'Stable',
            'risk_level': 0.5
        }
        
        # Check volatility levels
        if data['Volatility_20'] > data['Volatility_50']:
            insights['high_volatility'] = True
            insights['risk_level'] = 0.8
            
        # Check Bollinger Bands
        if data['Close'] > data['BB_Upper_20']:
            insights['volatility_trend'] = 'Expanding'
            insights['risk_level'] += 0.2
        elif data['Close'] < data['BB_Lower_20']:
            insights['volatility_trend'] = 'Contracting'
            insights['risk_level'] -= 0.2
            
        return insights

    def _analyze_sentiment(self, data: pd.Series) -> Dict:
        """Analyze sentiment indicators"""
        insights = {
            'positive_sentiment': False,
            'negative_sentiment': False,
            'volume_trend': 'Neutral',
            'confidence': 0.5
        }
        
        # Check volume profile
        if data['Volume'] > data['Volume_Profile']:
            insights['volume_trend'] = 'Increasing'
            insights['confidence'] += 0.2
        
        # Check MFI
        if data['MFI'] > 80:
            insights['negative_sentiment'] = True
            insights['confidence'] += 0.2
        elif data['MFI'] < 20:
            insights['positive_sentiment'] = True
            insights['confidence'] += 0.2
            
        return insights

    def _determine_market_regime(self, data: pd.Series) -> str:
        """Determine current market regime"""
        try:
            # Calculate regime score
            trend_score = self._analyze_trend(data)['strength']
            momentum_score = self._analyze_momentum(data)['strength']
            volatility_score = self._analyze_volatility(data)['risk_level']
            sentiment_score = self._analyze_sentiment(data)['confidence']
            
            regime_score = (
                trend_score * self.strategy_weights['trend'] +
                momentum_score * self.strategy_weights['momentum'] +
                volatility_score * self.strategy_weights['volatility'] +
                sentiment_score * self.strategy_weights['sentiment']
            )
            
            if regime_score > 0.7:
                return 'Bull Market'
            elif regime_score < 0.3:
                return 'Bear Market'
            else:
                return 'Sideways Market'
                
        except Exception as e:
            self.logger.error(f"Error determining market regime: {e}")
            return 'Unknown'

    def generate_decision(self, df: pd.DataFrame) -> Dict:
        """Generate trading decision with reasoning"""
        try:
            # Get market insights
            insights = self.analyze_market(df)
            
            # Generate decision scores
            decision_scores = {
                'buy': 0,
                'sell': 0,
                'hold': 0
            }
            
            # Calculate scores for each strategy
            for strategy, weight in self.strategy_weights.items():
                strategy_insights = insights[strategy]
                
                if strategy == 'trend':
                    if strategy_insights['direction'] == 'Bullish':
                        decision_scores['buy'] += weight * strategy_insights['strength']
                    elif strategy_insights['direction'] == 'Bearish':
                        decision_scores['sell'] += weight * strategy_insights['strength']
                
                if strategy == 'momentum':
                    if strategy_insights['oversold']:
                        decision_scores['buy'] += weight * strategy_insights['strength']
                    elif strategy_insights['overbought']:
                        decision_scores['sell'] += weight * strategy_insights['strength']
                
                if strategy == 'volatility':
                    if strategy_insights['high_volatility']:
                        decision_scores['hold'] += weight * strategy_insights['risk_level']
                
                if strategy == 'sentiment':
                    if strategy_insights['positive_sentiment']:
                        decision_scores['buy'] += weight * strategy_insights['confidence']
                    elif strategy_insights['negative_sentiment']:
                        decision_scores['sell'] += weight * strategy_insights['confidence']
            
            # Determine final decision
            final_decision = max(decision_scores, key=decision_scores.get)
            
            # Generate reasoning
            reasoning = []
            
            if final_decision == 'buy':
                reasoning.append("Bullish trend detected")
                if insights['momentum']['oversold']:
                    reasoning.append("Oversold momentum condition")
                if insights['sentiment']['positive_sentiment']:
                    reasoning.append("Positive market sentiment")
            
            elif final_decision == 'sell':
                reasoning.append("Bearish trend detected")
                if insights['momentum']['overbought']:
                    reasoning.append("Overbought momentum condition")
                if insights['sentiment']['negative_sentiment']:
                    reasoning.append("Negative market sentiment")
            
            else:  # hold
                reasoning.append("High market volatility detected")
                if insights['volatility']['high_volatility']:
                    reasoning.append("Waiting for volatility to subside")
                if insights['market_regime'] == 'Sideways Market':
                    reasoning.append("Market in consolidation phase")
            
            return {
                'decision': final_decision,
                'confidence': max(decision_scores.values()),
                'reasoning': reasoning,
                'insights': insights,
                'market_regime': insights['market_regime']
            }
            
        except Exception as e:
            self.logger.error(f"Error generating decision: {e}")
            return {
                'decision': 'hold',
                'confidence': 0.5,
                'reasoning': ['Error in decision generation'],
                'insights': {},
                'market_regime': 'Unknown'
            }
