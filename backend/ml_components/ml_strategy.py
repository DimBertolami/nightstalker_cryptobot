from typing import Dict, List, Optional, Tuple
import numpy as np
import pandas as pd
from datetime import datetime

from ..ml.sentiment_analyzer import MarketSentimentAnalyzer
from ..ml.pattern_recognition import PatternRecognition
from ..ml.anomaly_detection import MarketAnomalyDetector
from ..ml.reinforcement_learning import StrategyOptimizer
from ..ml.risk_management import RiskManager, RiskLimits
from ..ml.utils import logger
from .base_strategy import BaseStrategy

class MLTradingStrategy(BaseStrategy):
    """Integrated ML trading strategy with risk management"""
    
    def __init__(self, initial_capital: float = 100000.0):
        super().__init__()
        
        # Initialize ML components
        self.sentiment_analyzer = MarketSentimentAnalyzer()
        self.pattern_recognition = PatternRecognition()
        self.anomaly_detector = MarketAnomalyDetector()
        self.strategy_optimizer = StrategyOptimizer(
            input_size=7,
            hidden_size=128,
            output_size=3
        )
        
        # Initialize risk management
        self.risk_manager = RiskManager(initial_capital)
        
        # Strategy state
        self.current_positions: Dict[str, Dict] = {}
        self.pending_orders: List[Dict] = []
        self.strategy_state = {
            'last_trade_time': None,
            'daily_trades': 0,
            'current_drawdown': 0.0,
            'strategy_confidence': 0.0
        }
        
        # Performance tracking
        self.performance_metrics = {
            'total_trades': 0,
            'winning_trades': 0,
            'total_pnl': 0.0,
            'max_drawdown': 0.0
        }
    
    def analyze_market(self, market_data: pd.DataFrame,
                      news_data: Optional[List[str]] = None) -> Dict[str, float]:
        """Comprehensive market analysis using all ML components"""
        try:
            # Market sentiment analysis
            sentiment_score = 0.0
            if news_data:
                sentiment_score = self.sentiment_analyzer.analyze_text_sentiment(news_data)
            market_sentiment = self.sentiment_analyzer.analyze_market_data(market_data)
            
            # Pattern recognition
            patterns = self.pattern_recognition.detect_candlestick_patterns(market_data)
            technical_patterns = self.pattern_recognition.detect_technical_patterns(market_data)
            pattern_signals = self.pattern_recognition.get_trading_signals(market_data)
            
            # Anomaly detection
            anomaly_result = self.anomaly_detector.detect_anomalies(market_data)
            
            # Update market volatility
            returns = market_data['close'].pct_change().dropna().values
            self.risk_manager.update_market_volatility(returns)
            
            # Combine signals
            analysis = {
                'sentiment_score': sentiment_score,
                'market_sentiment': market_sentiment['overall_sentiment'],
                'pattern_strength': pattern_signals['signal_strength'],
                'anomaly_score': anomaly_result['anomaly_score'],
                'volatility': self.risk_manager.risk_metrics['current_volatility']
            }
            
            return analysis
            
        except Exception as e:
            logger.error(f"Error in market analysis: {str(e)}")
            return {}
    
    def generate_trading_decision(self, market_data: pd.DataFrame,
                                analysis: Dict[str, float]) -> Tuple[int, float]:
        """Generate trading decision with risk considerations"""
        try:
            # Get current state for RL
            state = self._prepare_state(market_data, analysis)
            
            # Get action from RL model
            action, confidence = self.strategy_optimizer.select_action(
                state, training=False)
            
            # Check risk limits
            should_reduce, reason = self.risk_manager.should_reduce_exposure()
            if should_reduce:
                logger.warning(f"Risk limit warning: {reason}")
                if action == 1:  # If trying to increase position
                    action = 0  # Hold instead
                    confidence *= 0.5
            
            # Update strategy confidence
            self.strategy_state['strategy_confidence'] = confidence
            
            return action, confidence
            
        except Exception as e:
            logger.error(f"Error generating trading decision: {str(e)}")
            return 0, 0.0
    
    def calculate_position_size(self, action: int, price: float,
                              confidence: float) -> float:
        """Calculate position size with risk adjustments"""
        try:
            # Base position size based on confidence
            base_size = self.risk_manager.current_capital * 0.1 * confidence
            
            # Adjust for volatility
            adjusted_size = self.risk_manager.adjust_position_size(
                base_size,
                self.risk_manager.risk_metrics['current_volatility']
            )
            
            # Ensure within daily limits
            if self.strategy_state['daily_trades'] >= \
               self.risk_manager.risk_limits.max_daily_trades:
                logger.warning("Daily trade limit reached")
                return 0.0
            
            return adjusted_size
            
        except Exception as e:
            logger.error(f"Error calculating position size: {str(e)}")
            return 0.0
    
    def execute_trade(self, symbol: str, action: int, 
                     confidence: float, price: float) -> Dict[str, any]:
        """Execute trade with risk management"""
        try:
            # Calculate position size
            position_size = self.calculate_position_size(action, price, confidence)
            if position_size == 0:
                return {'success': False, 'error': 'Zero position size'}
            
            # Apply position direction
            if action == 0:  # Hold
                return {'success': True, 'action': 'hold'}
            elif action == 1:  # Buy
                quantity = position_size / price
            else:  # Sell
                quantity = -position_size / price
            
            # Update position with risk checks
            result = self.risk_manager.update_position(symbol, quantity, price)
            
            if result['success']:
                # Update strategy state
                self.strategy_state['last_trade_time'] = datetime.now()
                self.strategy_state['daily_trades'] += 1
                self.performance_metrics['total_trades'] += 1
                
                # Log trade
                logger.info(f"Trade executed: {symbol} {action} {quantity:.4f} @ {price:.2f}")
                
                return {
                    'success': True,
                    'action': 'buy' if action == 1 else 'sell',
                    'quantity': quantity,
                    'price': price,
                    'confidence': confidence,
                    'risk_metrics': self.risk_manager.get_current_risk_metrics()
                }
            else:
                return {'success': False, 'error': result.get('error', 'Unknown error')}
            
        except Exception as e:
            logger.error(f"Error executing trade: {str(e)}")
            return {'success': False, 'error': str(e)}
    
    def _prepare_state(self, market_data: pd.DataFrame,
                      analysis: Dict[str, float]) -> np.ndarray:
        """Prepare state for RL model"""
        try:
            # Get latest market data
            latest = market_data.iloc[-1]
            
            # Combine market data with analysis
            state = np.array([
                latest['close'],
                latest['volume'],
                analysis.get('sentiment_score', 0),
                analysis.get('pattern_strength', 0),
                analysis.get('anomaly_score', 0),
                self.risk_manager.risk_metrics['current_drawdown'],
                self.risk_manager.risk_metrics['current_leverage']
            ])
            
            return state
            
        except Exception as e:
            logger.error(f"Error preparing state: {str(e)}")
            return np.zeros(7)
    
    def update_strategy(self, market_data: pd.DataFrame,
                       pnl: float) -> None:
        """Update strategy state and performance metrics"""
        try:
            # Update performance metrics
            self.performance_metrics['total_pnl'] += pnl
            if pnl > 0:
                self.performance_metrics['winning_trades'] += 1
            
            # Update drawdown
            peak_capital = self.risk_manager.risk_metrics['peak_capital']
            current_capital = self.risk_manager.current_capital
            current_drawdown = (peak_capital - current_capital) / peak_capital
            
            self.strategy_state['current_drawdown'] = current_drawdown
            self.performance_metrics['max_drawdown'] = max(
                self.performance_metrics['max_drawdown'],
                current_drawdown
            )
            
            # Train RL model if enough data
            if len(market_data) >= 100:
                self.strategy_optimizer.train(
                    env=self._create_training_env(market_data),
                    episodes=1,
                    max_steps_per_episode=100
                )
            
        except Exception as e:
            logger.error(f"Error updating strategy: {str(e)}")
    
    def get_strategy_state(self) -> Dict[str, any]:
        """Get current strategy state and metrics"""
        return {
            'strategy_state': self.strategy_state,
            'performance_metrics': self.performance_metrics,
            'risk_metrics': self.risk_manager.get_current_risk_metrics(),
            'positions': self.risk_manager.positions
        }
    
    def _create_training_env(self, market_data: pd.DataFrame):
        """Create training environment for RL"""
        from ..ml.environment import TradingEnvironment
        return TradingEnvironment(market_data)