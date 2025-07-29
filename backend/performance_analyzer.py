import logging
import numpy as np
import pandas as pd
from typing import Dict, List, Tuple, Optional
import matplotlib.pyplot as plt
import seaborn as sns
from datetime import datetime, timedelta
from sklearn.metrics import (
    mean_squared_error,
    mean_absolute_error,
    r2_score,
    explained_variance_score,
    precision_score,
    recall_score,
    f1_score,
    roc_auc_score
)
from scipy.stats import norm
import json
from pathlib import Path

logger = logging.getLogger(__name__)

class PerformanceAnalyzer:
    def __init__(self, config: Dict):
        self.config = config
        self.metrics_history = []
        self.strategy_history = []
        self.confidence_history = []
        self.trade_history = []
        self.model_performance = {}
        self.strategy_evolution = {}
        self.confidence_evolution = {}
        self.evaluation_metrics = {
            'price_prediction': {
                'mse': [],
                'mae': [],
                'r2': [],
                'explained_variance': []
            },
            'trade_decision': {
                'precision': [],
                'recall': [],
                'f1': [],
                'accuracy': []
            },
            'risk_management': {
                'max_drawdown': [],
                'volatility': [],
                'sharpe_ratio': [],
                'sortino_ratio': []
            },
            'strategy_performance': {
                'total_return': [],
                'annualized_return': [],
                'win_rate': [],
                'profit_factor': []
            }
        }

    def evaluate_model_performance(self, predictions: np.ndarray, actuals: np.ndarray) -> Dict:
        """Evaluate model prediction performance"""
        try:
            metrics = {
                'mse': mean_squared_error(actuals, predictions),
                'mae': mean_absolute_error(actuals, predictions),
                'r2': r2_score(actuals, predictions),
                'explained_variance': explained_variance_score(actuals, predictions),
                'rmse': np.sqrt(mean_squared_error(actuals, predictions)),
                'mape': np.mean(np.abs((actuals - predictions) / actuals)) * 100
            }
            
            self.metrics_history.append({
                'timestamp': datetime.now(),
                'metrics': metrics,
                'type': 'model_performance'
            })
            
            return metrics
            
        except Exception as e:
            logger.error(f"Error evaluating model performance: {e}")
            return {}

    def evaluate_trade_performance(self, trades: List[Dict]) -> Dict:
        """Evaluate trade execution performance"""
        try:
            # Calculate basic metrics
            total_trades = len(trades)
            winning_trades = len([t for t in trades if t['profit'] > 0])
            losing_trades = len([t for t in trades if t['profit'] < 0])
            
            # Calculate performance metrics
            metrics = {
                'total_trades': total_trades,
                'winning_trades': winning_trades,
                'losing_trades': losing_trades,
                'win_rate': winning_trades / total_trades if total_trades > 0 else 0,
                'average_profit': np.mean([t['profit'] for t in trades]),
                'average_loss': np.mean([t['profit'] for t in trades if t['profit'] < 0]),
                'profit_factor': abs(np.sum([t['profit'] for t in trades if t['profit'] > 0]) /
                                   np.sum([abs(t['profit']) for t in trades if t['profit'] < 0]))
            }
            
            self.metrics_history.append({
                'timestamp': datetime.now(),
                'metrics': metrics,
                'type': 'trade_performance'
            })
            
            return metrics
            
        except Exception as e:
            logger.error(f"Error evaluating trade performance: {e}")
            return {}

    def calculate_risk_metrics(self, returns: np.ndarray) -> Dict:
        """Calculate risk metrics"""
        try:
            metrics = {
                'max_drawdown': self._calculate_max_drawdown(returns),
                'volatility': np.std(returns),
                'sharpe_ratio': self._calculate_sharpe_ratio(returns),
                'sortino_ratio': self._calculate_sortino_ratio(returns)
            }
            
            self.metrics_history.append({
                'timestamp': datetime.now(),
                'metrics': metrics,
                'type': 'risk_metrics'
            })
            
            return metrics
            
        except Exception as e:
            logger.error(f"Error calculating risk metrics: {e}")
            return {}

    def analyze_strategy_evolution(self) -> Dict:
        """Analyze how strategy has evolved over time"""
        try:
            # Group metrics by time periods
            daily_metrics = self._group_metrics_by_period('daily')
            weekly_metrics = self._group_metrics_by_period('weekly')
            monthly_metrics = self._group_metrics_by_period('monthly')
            
            # Calculate improvement metrics
            improvements = {
                'price_prediction': self._calculate_improvement(
                    daily_metrics['price_prediction']['r2']
                ),
                'risk_management': self._calculate_improvement(
                    weekly_metrics['risk_management']['max_drawdown']
                ),
                'profitability': self._calculate_improvement(
                    monthly_metrics['strategy_performance']['profit_factor']
                )
            }
            
            # Track strategy evolution
            self.strategy_evolution[datetime.now()] = {
                'improvements': improvements,
                'current_metrics': {
                    'daily': daily_metrics,
                    'weekly': weekly_metrics,
                    'monthly': monthly_metrics
                }
            }
            
            return {
                'improvements': improvements,
                'current_metrics': {
                    'daily': daily_metrics,
                    'weekly': weekly_metrics,
                    'monthly': monthly_metrics
                }
            }
            
        except Exception as e:
            logger.error(f"Error analyzing strategy evolution: {e}")
            return {}

    def analyze_confidence_evolution(self) -> Dict:
        """Analyze how confidence has evolved over time"""
        try:
            # Calculate confidence metrics
            confidence_metrics = {
                'prediction_confidence': self._calculate_confidence_metrics(
                    'price_prediction'
                ),
                'trade_confidence': self._calculate_confidence_metrics(
                    'trade_decision'
                ),
                'risk_confidence': self._calculate_confidence_metrics(
                    'risk_management'
                )
            }
            
            # Track confidence evolution
            self.confidence_evolution[datetime.now()] = {
                'metrics': confidence_metrics,
                'correlations': self._calculate_confidence_correlations(confidence_metrics)
            }
            
            return {
                'metrics': confidence_metrics,
                'correlations': self._calculate_confidence_correlations(confidence_metrics)
            }
            
        except Exception as e:
            logger.error(f"Error analyzing confidence evolution: {e}")
            return {}

    def generate_performance_report(self, timeframe: str = 'all') -> Dict:
        """Generate comprehensive performance report"""
        try:
            # Filter metrics by timeframe
            filtered_metrics = self._filter_metrics_by_timeframe(timeframe)
            
            # Calculate performance metrics
            performance = {
                'price_prediction': self._calculate_performance_metrics(
                    filtered_metrics['price_prediction']
                ),
                'trade_decision': self._calculate_performance_metrics(
                    filtered_metrics['trade_decision']
                ),
                'risk_management': self._calculate_performance_metrics(
                    filtered_metrics['risk_management']
                ),
                'strategy_performance': self._calculate_performance_metrics(
                    filtered_metrics['strategy_performance']
                )
            }
            
            # Calculate improvement metrics
            improvements = self._calculate_improvement_metrics(performance)
            
            # Generate visualizations
            visualizations = self._generate_performance_visualizations(performance)
            
            return {
                'performance': performance,
                'improvements': improvements,
                'visualizations': visualizations,
                'strategy_evolution': self.analyze_strategy_evolution(),
                'confidence_evolution': self.analyze_confidence_evolution()
            }
            
        except Exception as e:
            logger.error(f"Error generating performance report: {e}")
            return {}

    def _calculate_max_drawdown(self, returns: np.ndarray) -> float:
        """Calculate maximum drawdown"""
        cumulative_returns = (1 + returns).cumprod()
        running_max = np.maximum.accumulate(cumulative_returns)
        drawdown = (cumulative_returns - running_max) / running_max
        return np.min(drawdown)

    def _calculate_sharpe_ratio(self, returns: np.ndarray) -> float:
        """Calculate Sharpe ratio"""
        return returns.mean() / returns.std()

    def _calculate_sortino_ratio(self, returns: np.ndarray) -> float:
        """Calculate Sortino ratio"""
        downside_returns = returns[returns < 0]
        if len(downside_returns) == 0:
            return np.inf
        return returns.mean() / downside_returns.std()

    def _calculate_improvement(self, metric_values: List[float]) -> Dict:
        """Calculate improvement over time"""
        if len(metric_values) < 2:
            return {'improvement': 0, 'trend': 'stable'}
            
        initial = metric_values[0]
        final = metric_values[-1]
        improvement = (final - initial) / initial * 100
        
        return {
            'improvement': improvement,
            'trend': 'up' if improvement > 0 else 'down' if improvement < 0 else 'stable'
        }

    def _calculate_confidence_metrics(self, metric_type: str) -> Dict:
        """Calculate confidence metrics"""
        metrics = self.evaluation_metrics[metric_type]
        
        return {
            'mean': {k: np.mean(v) for k, v in metrics.items()},
            'std': {k: np.std(v) for k, v in metrics.items()},
            'confidence_interval': {
                k: (
                    np.mean(v) - 1.96 * np.std(v) / np.sqrt(len(v)),
                    np.mean(v) + 1.96 * np.std(v) / np.sqrt(len(v))
                ) for k, v in metrics.items()
            }
        }

    def _calculate_confidence_correlations(self, confidence_metrics: Dict) -> Dict:
        """Calculate correlations between confidence metrics"""
        correlations = {}
        
        for metric1 in confidence_metrics:
            for metric2 in confidence_metrics:
                if metric1 != metric2:
                    corr = np.corrcoef(
                        confidence_metrics[metric1]['mean'].values(),
                        confidence_metrics[metric2]['mean'].values()
                    )[0, 1]
                    correlations[f"{metric1}_{metric2}"] = corr
        
        return correlations

    def _group_metrics_by_period(self, period: str) -> Dict:
        """Group metrics by time period"""
        metrics_by_period = {}
        
        for metric_type in self.evaluation_metrics:
            metrics_by_period[metric_type] = {}
            for metric_name, values in self.evaluation_metrics[metric_type].items():
                if period == 'daily':
                    grouped = self._group_by_day(values)
                elif period == 'weekly':
                    grouped = self._group_by_week(values)
                elif period == 'monthly':
                    grouped = self._group_by_month(values)
                
                metrics_by_period[metric_type][metric_name] = grouped
        
        return metrics_by_period

    def _filter_metrics_by_timeframe(self, timeframe: str) -> Dict:
        """Filter metrics by timeframe"""
        filtered_metrics = {}
        
        for metric_type in self.evaluation_metrics:
            filtered_metrics[metric_type] = {}
            for metric_name, values in self.evaluation_metrics[metric_type].items():
                if timeframe == 'all':
                    filtered = values
                elif timeframe == 'last_month':
                    filtered = values[-30:]
                elif timeframe == 'last_week':
                    filtered = values[-7:]
                elif timeframe == 'last_day':
                    filtered = values[-1:]
                
                filtered_metrics[metric_type][metric_name] = filtered
        
        return filtered_metrics

    def _calculate_performance_metrics(self, metrics: Dict) -> Dict:
        """Calculate performance metrics"""
        performance = {}
        
        for metric_name, values in metrics.items():
            performance[metric_name] = {
                'mean': np.mean(values),
                'std': np.std(values),
                'min': np.min(values),
                'max': np.max(values),
                'median': np.median(values),
                '25th_percentile': np.percentile(values, 25),
                '75th_percentile': np.percentile(values, 75)
            }
        
        return performance

    def _calculate_improvement_metrics(self, performance: Dict) -> Dict:
        """Calculate improvement metrics"""
        improvements = {}
        
        for metric_type, metrics in performance.items():
            improvements[metric_type] = {
                'improvement': self._calculate_improvement(list(metrics.values())),
                'trend': 'up' if metrics['mean'] > metrics['median'] else 'down'
            }
        
        return improvements

    def _generate_performance_visualizations(self, performance: Dict) -> Dict:
        """Generate visualizations of performance metrics"""
        visualizations = {}
        
        # Create plots directory if it doesn't exist
        plots_dir = Path('plots')
        plots_dir.mkdir(exist_ok=True)
        
        # Generate plots for each metric type
        for metric_type, metrics in performance.items():
            plt.figure(figsize=(12, 8))
            
            # Box plot
            plt.boxplot(metrics.values())
            plt.title(f"{metric_type} Performance Distribution")
            plt.xlabel("Metrics")
            plt.ylabel("Values")
            plt.xticks(range(1, len(metrics) + 1), metrics.keys(), rotation=45)
            
            plot_path = plots_dir / f"{metric_type}_performance.png"
            plt.savefig(plot_path)
            plt.close()
            
            visualizations[metric_type] = str(plot_path)
        
        return visualizations

    def save_performance_report(self, report: Dict, filename: str = 'performance_report.json'):
        """Save performance report to file"""
        try:
            with open(filename, 'w') as f:
                json.dump(report, f, indent=4, default=str)
            
            logger.info(f"Performance report saved to {filename}")
            
        except Exception as e:
            logger.error(f"Error saving performance report: {e}")

    def load_performance_report(self, filename: str = 'performance_report.json') -> Dict:
        """Load performance report from file"""
        try:
            with open(filename, 'r') as f:
                return json.load(f)
                
        except Exception as e:
            logger.error(f"Error loading performance report: {e}")
            return {}
