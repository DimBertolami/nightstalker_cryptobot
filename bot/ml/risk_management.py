import numpy as np
import pandas as pd
from typing import Dict, List, Optional, Tuple
from datetime import datetime
import threading
from dataclasses import dataclass
from .utils import logger, ValidationError

@dataclass
class RiskLimits:
    max_position_size: float
    max_drawdown: float
    max_daily_trades: int
    max_leverage: float
    volatility_threshold: float
    exposure_limit: float

class RiskManager:
    """Real-time risk monitoring and management system"""
    
    def __init__(self, initial_capital: float):
        self._validate_initial_capital(initial_capital)
        
        self.initial_capital = initial_capital
        self.current_capital = initial_capital
        self.positions: Dict[str, Dict] = {}
        self.risk_metrics: Dict[str, float] = {}
        self.trade_history: List[Dict] = []
        
        # Risk limits with default values
        self.risk_limits = RiskLimits(
            max_position_size=0.1,  # 10% of capital
            max_drawdown=0.2,       # 20% max drawdown
            max_daily_trades=10,
            max_leverage=3.0,
            volatility_threshold=0.03,
            exposure_limit=0.5      # 50% max total exposure
        )
        
        # Thread safety
        self._lock = threading.Lock()
        
        # Initialize metrics
        self._initialize_metrics()
    
    def _validate_initial_capital(self, capital: float) -> None:
        """Validate initial capital amount"""
        if not isinstance(capital, (int, float)) or capital <= 0:
            raise ValidationError("Initial capital must be a positive number")
    
    def _initialize_metrics(self) -> None:
        """Initialize risk metrics tracking"""
        self.risk_metrics = {
            'current_drawdown': 0.0,
            'peak_capital': self.initial_capital,
            'total_exposure': 0.0,
            'daily_trades': 0,
            'current_volatility': 0.0,
            'var_95': 0.0,
            'sharpe_ratio': 0.0,
            'current_leverage': 0.0
        }
    
    def update_position(self, symbol: str, quantity: float, price: float) -> Dict[str, any]:
        """Update position with real-time risk checks"""
        with self._lock:
            try:
                position_value = quantity * price
                
                # Check position size limit
                if abs(position_value) > self.current_capital * self.risk_limits.max_position_size:
                    raise ValidationError("Position size exceeds limit")
                
                # Check total exposure
                new_exposure = self._calculate_total_exposure() + abs(position_value)
                if new_exposure > self.current_capital * self.risk_limits.exposure_limit:
                    raise ValidationError("Total exposure exceeds limit")
                
                # Update position
                self.positions[symbol] = {
                    'quantity': quantity,
                    'price': price,
                    'value': position_value,
                    'timestamp': datetime.now()
                }
                
                # Update metrics
                self._update_risk_metrics()
                
                return {
                    'success': True,
                    'current_risk': self.get_current_risk_metrics(),
                    'position': self.positions[symbol]
                }
                
            except Exception as e:
                logger.error(f"Error updating position: {str(e)}")
                return {
                    'success': False,
                    'error': str(e)
                }
    
    def _calculate_total_exposure(self) -> float:
        """Calculate total market exposure"""
        return sum(abs(pos['value']) for pos in self.positions.values())
    
    def _update_risk_metrics(self) -> None:
        """Update all risk metrics"""
        try:
            # Update peak capital
            self.risk_metrics['peak_capital'] = max(
                self.risk_metrics['peak_capital'],
                self.current_capital
            )
            
            # Calculate drawdown
            self.risk_metrics['current_drawdown'] = (
                self.risk_metrics['peak_capital'] - self.current_capital
            ) / self.risk_metrics['peak_capital']
            
            # Update exposure
            self.risk_metrics['total_exposure'] = self._calculate_total_exposure()
            
            # Update leverage
            self.risk_metrics['current_leverage'] = (
                self.risk_metrics['total_exposure'] / self.current_capital
            )
            
            # Check risk limits
            self._check_risk_limits()
            
        except Exception as e:
            logger.error(f"Error updating risk metrics: {str(e)}")
    
    def _check_risk_limits(self) -> None:
        """Check if any risk limits are breached"""
        violations = []
        
        if self.risk_metrics['current_drawdown'] > self.risk_limits.max_drawdown:
            violations.append("Maximum drawdown exceeded")
        
        if self.risk_metrics['current_leverage'] > self.risk_limits.max_leverage:
            violations.append("Maximum leverage exceeded")
        
        if self.risk_metrics['current_volatility'] > self.risk_limits.volatility_threshold:
            violations.append("Volatility threshold exceeded")
        
        if violations:
            logger.warning("Risk limit violations: " + ", ".join(violations))
    
    def adjust_position_size(self, intended_size: float, 
                           volatility: float) -> float:
        """Adjust position size based on volatility"""
        try:
            # Base volatility adjustment
            vol_ratio = self.risk_limits.volatility_threshold / max(volatility, 1e-6)
            adjusted_size = intended_size * min(vol_ratio, 1.0)
            
            # Additional drawdown adjustment
            if self.risk_metrics['current_drawdown'] > 0:
                drawdown_factor = 1 - (self.risk_metrics['current_drawdown'] / 
                                     self.risk_limits.max_drawdown)
                adjusted_size *= max(drawdown_factor, 0.2)
            
            # Ensure within limits
            max_size = self.current_capital * self.risk_limits.max_position_size
            return min(adjusted_size, max_size)
            
        except Exception as e:
            logger.error(f"Error adjusting position size: {str(e)}")
            return 0.0
    
    def update_market_volatility(self, returns: np.ndarray) -> None:
        """Update market volatility estimate"""
        try:
            # Calculate rolling volatility
            self.risk_metrics['current_volatility'] = float(np.std(returns) * np.sqrt(252))
            
            # Update Value at Risk (VaR)
            self.risk_metrics['var_95'] = float(
                np.percentile(returns, 5) * np.sqrt(252) * self.current_capital
            )
            
        except Exception as e:
            logger.error(f"Error updating volatility: {str(e)}")
    
    def get_current_risk_metrics(self) -> Dict[str, float]:
        """Get current risk metrics"""
        with self._lock:
            return {
                'drawdown': self.risk_metrics['current_drawdown'],
                'exposure': self.risk_metrics['total_exposure'],
                'leverage': self.risk_metrics['current_leverage'],
                'volatility': self.risk_metrics['current_volatility'],
                'var_95': self.risk_metrics['var_95'],
                'capital_at_risk': self.risk_metrics['total_exposure'] * 
                                 self.risk_metrics['current_volatility'],
                'free_capital': self.current_capital - self.risk_metrics['total_exposure']
            }
    
    def should_reduce_exposure(self) -> Tuple[bool, str]:
        """Check if exposure should be reduced"""
        with self._lock:
            if self.risk_metrics['current_drawdown'] > self.risk_limits.max_drawdown * 0.8:
                return True, "Approaching maximum drawdown"
            
            if self.risk_metrics['current_leverage'] > self.risk_limits.max_leverage * 0.9:
                return True, "Approaching maximum leverage"
            
            if self.risk_metrics['current_volatility'] > self.risk_limits.volatility_threshold * 1.2:
                return True, "High market volatility"
            
            return False, ""
    
    def update_risk_limits(self, new_limits: RiskLimits) -> None:
        """Update risk limits with validation"""
        try:
            if new_limits.max_position_size <= 0 or new_limits.max_position_size > 1:
                raise ValidationError("Invalid position size limit")
            
            if new_limits.max_drawdown <= 0 or new_limits.max_drawdown > 1:
                raise ValidationError("Invalid drawdown limit")
            
            if new_limits.max_leverage < 1:
                raise ValidationError("Invalid leverage limit")
            
            with self._lock:
                self.risk_limits = new_limits
                self._check_risk_limits()
                
        except Exception as e:
            logger.error(f"Error updating risk limits: {str(e)}")
            raise