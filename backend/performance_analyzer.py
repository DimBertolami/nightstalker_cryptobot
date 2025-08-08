"""
Integrated Performance Analyzer and Tracker for Cryptocurrency Trading Models

This module combines the functionalities of performance tracking, logging,
analyzing machine learning model performance, and strategy optimization
for cryptocurrency trading.
"""

import os
import json
import datetime
import logging
import warnings
import numpy as np
import pandas as pd
import matplotlib.pyplot as plt
from typing import Dict, List
from sklearn.metrics import (
    mean_squared_error,
    mean_absolute_error,
    r2_score,
    explained_variance_score,
    precision_score,
    recall_score,
    f1_score
)
from pathlib import Path

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler("trading_performance.log"),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger("performance_analyzer")

# Suppress warnings
warnings.filterwarnings('ignore')

from backend.ml_components.ml_component_base import MLComponentBase

class PerformanceAnalyzer(MLComponentBase):
    def __init__(self, config: Dict):
        super().__init__()
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
                'timestamp': datetime.datetime.now(),
                'metrics': metrics,
                'type': 'model_performance'
            })
            return metrics
        except Exception as e:
            logger.error(f"Error evaluating model performance: {e}")
            return {}

    def evaluate_trade_performance(self, trades: List[Dict]) -> Dict:
        try:
            total_trades = len(trades)
            winning_trades = len([t for t in trades if t['profit'] > 0])
            losing_trades = len([t for t in trades if t['profit'] < 0])
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
                'timestamp': datetime.datetime.now(),
                'metrics': metrics,
                'type': 'trade_performance'
            })
            return metrics
        except Exception as e:
            logger.error(f"Error evaluating trade performance: {e}")
            return {}

    def calculate_risk_metrics(self, returns: np.ndarray) -> Dict:
        try:
            metrics = {
                'max_drawdown': self._calculate_max_drawdown(returns),
                'volatility': np.std(returns),
                'sharpe_ratio': self._calculate_sharpe_ratio(returns),
                'sortino_ratio': self._calculate_sortino_ratio(returns)
            }
            self.metrics_history.append({
                'timestamp': datetime.datetime.now(),
                'metrics': metrics,
                'type': 'risk_metrics'
            })
            return metrics
        except Exception as e:
            logger.error(f"Error calculating risk metrics: {e}")
            return {}

    def _calculate_max_drawdown(self, returns: np.ndarray) -> float:
        cumulative_returns = (1 + returns).cumprod()
        running_max = np.maximum.accumulate(cumulative_returns)
        drawdown = (cumulative_returns - running_max) / running_max
        return np.min(drawdown)

    def _calculate_sharpe_ratio(self, returns: np.ndarray) -> float:
        return returns.mean() / returns.std()

    def _calculate_sortino_ratio(self, returns: np.ndarray) -> float:
        downside_returns = returns[returns < 0]
        if len(downside_returns) == 0:
            return np.inf
        return returns.mean() / downside_returns.std()

    def _calculate_improvement(self, metric_values: List[float]) -> Dict:
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
        improvements = {}
        for metric_type, metrics in performance.items():
            improvements[metric_type] = {
                'improvement': self._calculate_improvement(list(metrics.values())),
                'trend': 'up' if metrics['mean'] > metrics['median'] else 'down'
            }
        return improvements

    def _generate_performance_visualizations(self, performance: Dict) -> Dict:
        visualizations = {}
        plots_dir = Path('plots')
        plots_dir.mkdir(exist_ok=True)
        for metric_type, metrics in performance.items():
            plt.figure(figsize=(12, 8))
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
        try:
            with open(filename, 'w') as f:
                json.dump(report, f, indent=4, default=str)
            logger.info(f"Performance report saved to {filename}")
        except Exception as e:
            logger.error(f"Error saving performance report: {e}")

    def load_performance_report(self, filename: str = 'performance_report.json') -> Dict:
        try:
            with open(filename, 'r') as f:
                return json.load(f)
        except Exception as e:
            logger.error(f"Error loading performance report: {e}")
            return {}

class ModelPerformanceTracker(MLComponentBase):
    """
    Track and analyze the performance of trading models over time.
    """

    def __init__(self, db_path='performance_db', use_mongodb=False, mongodb_uri=None):
        super().__init__()
        self.db_path = db_path
        self.use_mongodb = use_mongodb

        if not use_mongodb and not os.path.exists(db_path):
            os.makedirs(db_path)

        if use_mongodb:
            if mongodb_uri is None:
                mongodb_uri = "mongodb://localhost:27017/"
            # MongoDB usage commented out to avoid dependency issues
            # self.client = MongoClient(mongodb_uri)
            # self.db = self.client.trading_performance
            # self.models_collection = self.db.models
            # self.trades_collection = self.db.trades
            # self.metrics_collection = self.db.metrics
            logger.info(f"MongoDB usage is disabled in this environment.")

        self.current_metrics = {}
        self.historical_metrics = []

        logger.info(f"Performance tracker initialized with storage at {db_path}")

    def initialize(self, *args, **kwargs):
        pass

    def record_model_performance(self, model_id, model_type, trading_pair, timeframe,
                               metrics, predictions=None, trades=None, parameters=None):
        timestamp = datetime.datetime.now()
        performance_data = {
            "model_id": model_id,
            "model_type": model_type,
            "trading_pair": trading_pair,
            "timeframe": timeframe,
            "timestamp": timestamp,
            "metrics": metrics,
            "parameters": parameters if parameters else {}
        }
        if self.use_mongodb:
            logger.warning("MongoDB usage is disabled in this environment.")
            performance_id = f"{model_id}_{timestamp.strftime('%Y%m%d%H%M%S')}"
        else:
            performance_id = f"{model_id}_{timestamp.strftime('%Y%m%d%H%M%S')}"
            performance_file = os.path.join(self.db_path, f"{performance_id}.json")
            with open(performance_file, 'w') as f:
                json.dump(performance_data, f, default=str, indent=2)
            if predictions is not None and not predictions.empty:
                pred_file = os.path.join(self.db_path, f"{performance_id}_predictions.csv")
                predictions.to_csv(pred_file, index=False)
            if trades is not None and not trades.empty:
                trades_file = os.path.join(self.db_path, f"{performance_id}_trades.csv")
                trades.to_csv(trades_file, index=False)
        self.current_metrics = {
            "model_id": model_id,
            "timestamp": timestamp,
            **metrics
        }
        self.historical_metrics.append({
            "model_id": model_id,
            "timestamp": timestamp,
            **metrics
        })
        logger.info(f"Recorded performance for model {model_id}: {metrics}")
        return performance_id

    def calculate_trading_metrics(self, trades_df, initial_balance=10000):
        if trades_df is None or trades_df.empty:
            logger.warning("No trades provided for metrics calculation")
            return {}
        required_cols = ['timestamp', 'action', 'price', 'amount', 'profit']
        missing_cols = [col for col in required_cols if col not in trades_df.columns]
        if missing_cols:
            logger.error(f"Missing required columns in trades DataFrame: {missing_cols}")
            return {}
        total_trades = len(trades_df)
        winning_trades = len(trades_df[trades_df['profit'] > 0])
        losing_trades = len(trades_df[trades_df['profit'] < 0])
        win_rate = winning_trades / total_trades if total_trades > 0 else 0
        total_profit = trades_df['profit'].sum()
        profit_percentage = (total_profit / initial_balance) * 100
        balance = initial_balance
        balance_history = [balance]
        for profit in trades_df['profit']:
            balance += profit
            balance_history.append(balance)
        balance_series = pd.Series(balance_history)
        running_max = balance_series.cummax()
        drawdown = (balance_series - running_max) / running_max * 100
        max_drawdown = drawdown.min()
        if len(trades_df) > 1:
            returns = trades_df['profit'] / initial_balance
            sharpe_ratio = np.sqrt(365) * (returns.mean() / returns.std())
        else:
            sharpe_ratio = 0
        avg_profit_per_trade = total_profit / total_trades if total_trades > 0 else 0
        total_gains = trades_df[trades_df['profit'] > 0]['profit'].sum()
        total_losses = abs(trades_df[trades_df['profit'] < 0]['profit'].sum())
        profit_factor = total_gains / total_losses if total_losses != 0 else float('inf')
        metrics = {
            "total_trades": total_trades,
            "winning_trades": winning_trades,
            "losing_trades": losing_trades,
            "win_rate": win_rate,
            "total_profit": total_profit,
            "profit_percentage": profit_percentage,
            "max_drawdown": max_drawdown,
            "sharpe_ratio": sharpe_ratio,
            "avg_profit_per_trade": avg_profit_per_trade,
            "profit_factor": profit_factor,
            "final_balance": balance
        }
        return metrics

    def compare_models(self, model_ids=None, top_n=5, metric='profit_percentage'):
        if self.use_mongodb:
            logger.warning("MongoDB usage is disabled in this environment.")
            return pd.DataFrame()
        else:
            performance_records = []
            for file in os.listdir(self.db_path):
                if file.endswith('.json') and not file.endswith('_config.json'):
                    with open(os.path.join(self.db_path, file), 'r') as f:
                        record = json.load(f)
                        if model_ids is None or record.get('model_id') in model_ids:
                            performance_records.append(record)
        if not performance_records:
            logger.warning("No performance records found for comparison")
            return pd.DataFrame()
        comparison_data = []
        for record in performance_records:
            metrics = record.get('metrics', {})
            row = {
                "model_id": record.get('model_id'),
                "model_type": record.get('model_type'),
                "trading_pair": record.get('trading_pair'),
                "timeframe": record.get('timeframe'),
                "timestamp": record.get('timestamp')
            }
            for m_key, m_value in metrics.items():
                row[m_key] = m_value
            comparison_data.append(row)
        comparison_df = pd.DataFrame(comparison_data)
        if metric in comparison_df.columns:
            comparison_df = comparison_df.sort_values(by=metric, ascending=False).head(top_n)
        return comparison_df

    def plot_performance_history(self, model_id=None, metric='profit_percentage'):
        if model_id:
            if self.use_mongodb:
                logger.warning("MongoDB usage is disabled in this environment.")
                return None
            else:
                records = []
                for file in os.listdir(self.db_path):
                    if file.endswith('.json') and model_id in file:
                        with open(os.path.join(self.db_path, file), 'r') as f:
                            records.append(json.load(f))
        else:
            records = self.historical_metrics
        if not records:
            logger.warning(f"No historical data found for model_id={model_id}")
            return None
        plot_data = []
        for record in records:
            timestamp = record.get('timestamp')
            if isinstance(timestamp, str):
                timestamp = datetime.datetime.fromisoformat(timestamp.replace('Z', '+00:00'))
            metrics = record.get('metrics', {})
            if not metrics and metric in record:
                value = record[metric]
            else:
                value = metrics.get(metric)
            if value is not None:
                plot_data.append({
                    'timestamp': timestamp,
                    'model_id': record.get('model_id'),
                    'value': value
                })
        plot_df = pd.DataFrame(plot_data)
        plot_df = plot_df.sort_values('timestamp')
        fig, ax = plt.subplots(figsize=(12, 6))
        if model_id:
            ax.plot(plot_df['timestamp'], plot_df['value'], marker='o', linestyle='-')
            ax.set_title(f"{metric} History for Model {model_id}")
        else:
            for model, group in plot_df.groupby('model_id'):
                ax.plot(group['timestamp'], group['value'], marker='o', linestyle='-', label=model)
            ax.legend(title="Model ID")
            ax.set_title(f"{metric} History Across Models")
        ax.set_xlabel("Date")
        ax.set_ylabel(metric)
        ax.grid(True, alpha=0.3)
        plt.tight_layout()
        return fig

class TradingStrategyOptimizer(MLComponentBase):
    """
    Optimize trading strategies based on historical performance.
    """

    def __init__(self, performance_tracker):
        super().__init__()
        self.performance_tracker = performance_tracker
        self.improvement_history = []
        logger.info("Trading strategy optimizer initialized")

    def initialize(self, *args, **kwargs):
        pass

    def analyze_model_weaknesses(self, model_id, trades_df):
        if trades_df is None or trades_df.empty:
            logger.warning("No trades provided for weakness analysis")
            return {}
        required_cols = ['timestamp', 'action', 'price', 'profit', 'market_condition']
        missing_cols = [col for col in required_cols if col not in trades_df.columns]
        if missing_cols:
            logger.warning(f"Missing columns for detailed analysis: {missing_cols}")
            if 'market_condition' in missing_cols and 'profit' in trades_df.columns:
                trades_df['market_trend'] = np.where(trades_df['profit'] > 0, 'up', 'down')
        if 'market_condition' in trades_df.columns:
            condition_performance = trades_df.groupby('market_condition').agg({
                'profit': ['sum', 'mean', 'count'],
                'action': 'count'
            })
            if not condition_performance.empty:
                worst_condition = condition_performance['profit']['sum'].idxmin()
                worst_condition_data = {
                    'condition': worst_condition,
                    'total_profit': condition_performance['profit']['sum'][worst_condition],
                    'avg_profit': condition_performance['profit']['mean'][worst_condition],
                    'trade_count': condition_performance['action']['count'][worst_condition]
                }
            else:
                worst_condition_data = {}
        else:
            condition_performance = pd.DataFrame()
            worst_condition_data = {}
        losing_trades = trades_df[trades_df['profit'] < 0]
        if not losing_trades.empty and 'timestamp' in losing_trades.columns:
            if isinstance(losing_trades['timestamp'].iloc[0], str):
                losing_trades['timestamp'] = pd.to_datetime(losing_trades['timestamp'])
            losing_trades['hour'] = losing_trades['timestamp'].dt.hour
            losing_by_hour = losing_trades.groupby('hour').agg({
                'profit': ['sum', 'count']
            })
            worst_hour = losing_by_hour['profit']['sum'].idxmin()
            time_weakness = {
                'worst_hour': worst_hour,
                'loss_at_worst_hour': losing_by_hour['profit']['sum'][worst_hour],
                'trades_at_worst_hour': losing_by_hour['profit']['count'][worst_hour]
            }
        else:
            time_weakness = {}
        trades_df['win'] = trades_df['profit'] > 0
        streaks = (trades_df['win'] != trades_df['win'].shift()).cumsum()
        losing_streaks = trades_df[~trades_df['win']].groupby(streaks).size()
        max_consecutive_losses = losing_streaks.max() if not losing_streaks.empty else 0
        weakness_analysis = {
            'model_id': model_id,
            'timestamp': datetime.datetime.now(),
            'worst_market_condition': worst_condition_data,
            'time_weakness': time_weakness,
            'max_consecutive_losses': max_consecutive_losses,
            'total_losing_trades': len(losing_trades),
            'avg_loss_per_losing_trade': losing_trades['profit'].mean() if not losing_trades.empty else 0
        }
        return weakness_analysis

    def suggest_improvements(self, model_id, weakness_analysis, model_params=None):
        suggestions = {
            'model_id': model_id,
            'timestamp': datetime.datetime.now(),
            'parameter_adjustments': {},
            'strategy_changes': [],
            'data_improvements': []
        }
        if model_params:
            max_losses = weakness_analysis.get('max_consecutive_losses', 0)
            if max_losses > 5:
                suggestions['parameter_adjustments']['risk_level'] = 'lower'
                suggestions['parameter_adjustments']['position_size'] = 'reduce'
                suggestions['strategy_changes'].append(
                    "Implement more aggressive stop-loss due to frequent consecutive losses"
                )
        worst_condition = weakness_analysis.get('worst_market_condition', {}).get('condition')
        if worst_condition:
            suggestions['data_improvements'].append(
                f"Add more training data for '{worst_condition}' market conditions"
            )
            if worst_condition == 'volatile':
                suggestions['strategy_changes'].append(
                    "Consider adding volatility indicators like ATR or Bollinger Band Width"
                )
            elif worst_condition == 'trending':
                suggestions['strategy_changes'].append(
                    "Consider adding trend strength indicators like ADX"
                )
            elif worst_condition == 'sideways':
                suggestions['strategy_changes'].append(
                    "Consider adding range-bound indicators like RSI or Stochastic oscillators"
                )
        time_weakness = weakness_analysis.get('time_weakness', {})
        if time_weakness and 'worst_hour' in time_weakness:
            suggestions['strategy_changes'].append(
                f"Consider avoiding trades around hour {time_weakness['worst_hour']} or adjusting strategy for this time period"
            )
        logger.info(f"Generated improvement suggestions for model {model_id}")
        self.improvement_history.append({
            'model_id': model_id,
            'timestamp': datetime.datetime.now(),
            'weakness_analysis': weakness_analysis,
            'suggestions': suggestions
        })
        return suggestions

    def implement_suggestions(self, suggestions, model_params):
        updated_params = model_params.copy()
        param_adjustments = suggestions.get('parameter_adjustments', {})
        if 'risk_level' in param_adjustments:
            if param_adjustments['risk_level'] == 'lower':
                if 'stop_loss_pct' in updated_params:
                    updated_params['stop_loss_pct'] *= 0.8
                if 'take_profit_pct' in updated_params:
                    updated_params['take_profit_pct'] *= 1.2
        if 'position_size' in param_adjustments:
            if param_adjustments['position_size'] == 'reduce':
                if 'position_size_pct' in updated_params:
                    updated_params['position_size_pct'] *= 0.8
        strategy_changes = suggestions.get('strategy_changes', [])
        for change in strategy_changes:
            if "volatility indicators" in change.lower():
                updated_params['use_atr'] = True
                updated_params['use_bbands'] = True
            elif "trend strength indicators" in change.lower():
                updated_params['use_adx'] = True
                updated_params['use_macd'] = True
            elif "range-bound indicators" in change.lower():
                updated_params['use_rsi'] = True
                updated_params['use_stoch'] = True
        logger.info(f"Implemented suggested improvements: {updated_params}")
        return updated_params
