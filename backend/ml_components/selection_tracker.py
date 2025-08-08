"""
Selection tracker for monitoring and analyzing crypto selection performance.

Tracks decisions, performance metrics, and provides detailed analytics.
"""

import numpy as np
import pandas as pd
from sqlalchemy.orm import Session
from datetime import datetime, timedelta
from typing import Dict, List, Tuple
import logging
import os
from backend.models.unified_models import Trade, ModelPerformance, ModelPrediction

class SelectionTracker:
    def __init__(self, config: dict, db_session: Session):
        self.config = config
        self.db = db_session
        self.logger = logging.getLogger(__name__)
        self.decision_history = []
        self.performance_metrics = {}

    def record_decision(self, decision: str, price: float, prediction: float, 
                       confidence: float, risk_score: float, coin_id: str = None):
        """Record a trading decision."""
        decision_record = {
            'timestamp': datetime.now(),
            'decision': decision,
            'price': price,
            'prediction': prediction,
            'confidence': confidence,
            'risk_score': risk_score,
            'coin_id': coin_id
        }
        
        self.decision_history.append(decision_record)
        
        # Store in database
        trade = Trade(
            symbol=coin_id or 'UNKNOWN',
            decision=decision,
            price=price,
            amount=0,  # Will be calculated based on risk management
            balance=self.get_current_balance(),
            position=0,
            risk_score=risk_score,
            model_confidence=confidence,
            strategy_version='crypto_selector_v1.0',
            notes=f"Prediction: {prediction:.4f}, Confidence: {confidence:.4f}"
        )
        
        self.db.add(trade)
        self.db.commit()
        
        self.logger.info(f"Recorded decision: {decision} at {price:.4f}")

    def get_current_balance(self) -> float:
        """Get current portfolio balance."""
        latest_trade = self.db.query(Trade).order_by(Trade.id.desc()).first()
        return latest_trade.balance if latest_trade else 1000.0  # Default starting balance

    def get_correlation_matrix(self) -> pd.DataFrame:
        """Get correlation matrix for risk management."""
        # This would typically fetch historical price data
        # For now, return empty DataFrame
        return pd.DataFrame()

    def calculate_performance_metrics(self, days: int = 30) -> dict:
        """Calculate performance metrics for the last N days."""
        cutoff_date = datetime.now() - timedelta(days=days)
        
        trades = self.db.query(Trade).filter(
            Trade.timestamp >= cutoff_date
        ).order_by(Trade.timestamp).all()
        
        if not trades:
            return {}
        
        # Convert to DataFrame
        df = pd.DataFrame([{
            'timestamp': t.timestamp,
            'decision': t.decision,
            'price': t.price,
            'confidence': t.model_confidence,
            'risk_score': t.risk_score
        } for t in trades])
        
        # Calculate metrics
        total_trades = len(df)
        buy_trades = len(df[df['decision'] == 'BUY'])
        sell_trades = len(df[df['decision'] == 'SELL'])
        hold_trades = len(df[df['decision'] == 'HOLD'])
        
        # Win rate (simplified - assuming SELL is a win)
        win_rate = sell_trades / total_trades if total_trades > 0 else 0
        
        # Average confidence
        avg_confidence = df['confidence'].mean()
        avg_risk_score = df['risk_score'].mean()
        
        # Sharpe ratio (simplified)
        returns = df['price'].pct_change().dropna()
        sharpe_ratio = returns.mean() / returns.std() if returns.std() > 0 else 0
        
        # Maximum drawdown
        cumulative_returns = (1 + returns).cumprod()
        running_max = cumulative_returns.expanding().max()
        drawdown = (cumulative_returns - running_max) / running_max
        max_drawdown = drawdown.min()
        
        metrics = {
            'total_trades': total_trades,
            'buy_trades': buy_trades,
            'sell_trades': sell_trades,
            'hold_trades': hold_trades,
            'win_rate': win_rate,
            'avg_confidence': avg_confidence,
            'avg_risk_score': avg_risk_score,
            'sharpe_ratio': sharpe_ratio,
            'max_drawdown': max_drawdown,
            'period_days': days
        }
        
        self.performance_metrics = metrics
        return metrics

    def get_model_performance(self, model_name: str, days: int = 30) -> dict:
        """Get performance metrics for a specific model."""
        cutoff_date = datetime.now() - timedelta(days=days)
        
        predictions = self.db.query(ModelPrediction).filter(
            ModelPrediction.created_at >= cutoff_date
        ).all()
        
        if not predictions:
            return {}
        
        actual_prices = [p.actual_price for p in predictions]
        predicted_prices = [p.predicted_price for p in predictions]
        
        # Calculate metrics
        mse = np.mean((np.array(actual_prices) - np.array(predicted_prices)) ** 2)
        rmse = np.sqrt(mse)
        mae = np.mean(np.abs(np.array(actual_prices) - np.array(predicted_prices)))
        r2 = 1 - (mse / np.var(actual_prices))
        
        return {
            'model_name': model_name,
            'rmse': rmse,
            'mae': mae,
            'r2': r2,
            'predictions_count': len(predictions),
            'period_days': days
        }

    def generate_performance_report(self, days: int = 30) -> dict:
        """Generate comprehensive performance report."""
        overall_metrics = self.calculate_performance_metrics(days)
        model_metrics = {}
        
        # Get model-specific metrics
        models = self.db.query(ModelPerformance).all()
        for model in models:
            model_metrics[model.model_name] = self.get_model_performance(
                model.model_name, days
            )
        
        # Decision analysis
        decision_analysis = self._analyze_decisions(days)
        
        # Risk analysis
        risk_analysis = self._analyze_risk(days)
        
        report = {
            'overall_metrics': overall_metrics,
            'model_metrics': model_metrics,
            'decision_analysis': decision_analysis,
            'risk_analysis': risk_analysis,
            'generated_at': datetime.now()
        }
        
        return report

    def _analyze_decisions(self, days: int) -> dict:
        """Analyze decision patterns."""
        cutoff_date = datetime.now() - timedelta(days=days)
        
        trades = self.db.query(Trade).filter(
            Trade.timestamp >= cutoff_date
        ).all()
        
        if not trades:
            return {}
        
        df = pd.DataFrame([{
            'timestamp': t.timestamp,
            'decision': t.decision,
            'confidence': t.model_confidence,
            'risk_score': t.risk_score
        } for t in trades])
        
        # Decision distribution
        decision_counts = df['decision'].value_counts().to_dict()
        
        # Confidence by decision
        confidence_by_decision = df.groupby('decision')['confidence'].agg(['mean', 'std']).to_dict()
        
        # Risk score by decision
        risk_by_decision = df.groupby('decision')['risk_score'].agg(['mean', 'std']).to_dict()
        
        # Time-based patterns
        df['hour'] = df['timestamp'].dt.hour
        df['day_of_week'] = df['timestamp'].dt.dayofweek
        
        hourly_distribution = df.groupby('hour')['decision'].value_counts().to_dict()
        daily_distribution = df.groupby('day_of_week')['decision'].value_counts().to_dict()
        
        return {
            'decision_counts': decision_counts,
            'confidence_by_decision': confidence_by_decision,
            'risk_by_decision': risk_by_decision,
            'hourly_distribution': hourly_distribution,
            'daily_distribution': daily_distribution
        }

    def _analyze_risk(self, days: int) -> dict:
        """Analyze risk patterns."""
        cutoff_date = datetime.now() - timedelta(days=days)
        
        trades = self.db.query(Trade).filter(
            Trade.timestamp >= cutoff_date
        ).all()
        
        if not trades:
            return {}
        
        df = pd.DataFrame([{
            'timestamp': t.timestamp,
            'risk_score': t.risk_score,
            'confidence': t.model_confidence,
            'decision': t.decision
        } for t in trades])
        
        # Risk distribution
        risk_distribution = {
            'mean': df['risk_score'].mean(),
            'std': df['risk_score'].std(),
            'min': df['risk_score'].min(),
            'max': df['risk_score'].max(),
            'percentiles': {
                '25th': df['risk_score'].quantile(0.25),
                '50th': df['risk_score'].quantile(0.5),
                '75th': df['risk_score'].quantile(0.75)
            }
        }
        
        # Risk by decision
        risk_by_decision = df.groupby('decision')['risk_score'].agg(['mean', 'std']).to_dict()
        
        # Risk vs confidence correlation
        risk_confidence_corr = df['risk_score'].corr(df['confidence'])
        
        return {
            'risk_distribution': risk_distribution,
            'risk_by_decision': risk_by_decision,
            'risk_confidence_correlation': risk_confidence_corr
        }

    def get_recent_decisions(self, limit: int = 10) -> List[dict]:
        """Get recent decisions."""
        trades = self.db.query(Trade).order_by(Trade.timestamp.desc()).limit(limit).all()
        
        return [{
            'timestamp': t.timestamp,
            'decision': t.decision,
            'price': t.price,
            'confidence': t.model_confidence,
            'risk_score': t.risk_score,
            'coin_id': t.symbol
        } for t in trades]

    def export_decisions(self, filename: str = None) -> str:
        """Export decision history to CSV."""
        if filename is None:
            filename = f"decisions_{datetime.now().strftime('%Y%m%d_%H%M%S')}.csv"
        
        trades = self.db.query(Trade).all()
        
        if not trades:
            return None
        
        df = pd.DataFrame([{
            'timestamp': t.timestamp,
            'symbol': t.symbol,
            'decision': t.decision,
            'price': t.price,
            'confidence': t.model_confidence,
            'risk_score': t.risk_score,
            'strategy_version': t.strategy_version
        } for t in trades])
        
        filepath = os.path.join('reports', filename)
        os.makedirs('reports', exist_ok=True)
        df.to_csv(filepath, index=False)
        
        return filepath
