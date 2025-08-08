import os
import json
import datetime
import logging
import warnings
import numpy as np
import pandas as pd
import matplotlib.pyplot as plt
from sklearn.metrics import accuracy_score, precision_score, recall_score, f1_score
from sklearn.metrics import mean_squared_error, mean_absolute_error, r2_score
import tensorflow as tf
from tabulate import tabulate
import mysql.connector
from .ml_component_base import MLComponentBase

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler("trading_performance.log"),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger("performance_tracker")

# Suppress warnings
warnings.filterwarnings('ignore')


class ModelPerformanceTracker(MLComponentBase):
    """
    Track and analyze the performance of trading models over time.
    """
    
    def __init__(self, db_path='performance_db', use_mongodb=False, mongodb_uri=None):
        super().__init__()
        self.db_path = db_path
        self.use_mongodb = False  # Disable MongoDB usage
        
        # Create storage directory if needed
        if not os.path.exists(db_path):
            os.makedirs(db_path)
        
        # Connect to MySQL database
        try:
            self.mysql_conn = mysql.connector.connect(
                host="localhost",
                user="your_mysql_user",
                password="your_mysql_password",
                database="trading_performance"
            )
            self.mysql_cursor = self.mysql_conn.cursor()
            logger.info("Connected to MySQL database trading_performance")
        except mysql.connector.Error as err:
            logger.error(f"Error connecting to MySQL: {err}")
            self.mysql_conn = None
            self.mysql_cursor = None
        
        # Track current performance metrics
        self.current_metrics = {}
        self.historical_metrics = []
        
        logger.info(f"Performance tracker initialized with storage at {db_path}")
    
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
        
        if self.mysql_conn and self.mysql_cursor:
            try:
                # Insert performance data into MySQL table
                insert_query = """
                INSERT INTO model_performance (model_id, model_type, trading_pair, timeframe, timestamp, metrics, parameters)
                VALUES (%s, %s, %s, %s, %s, %s, %s)
                """
                self.mysql_cursor.execute(insert_query, (
                    model_id, model_type, trading_pair, timeframe, timestamp,
                    json.dumps(metrics), json.dumps(parameters if parameters else {})
                ))
                self.mysql_conn.commit()
                performance_id = self.mysql_cursor.lastrowid
                
                # Handle predictions and trades storage as needed (e.g., separate tables)
                # For now, skipping detailed implementation
                
            except mysql.connector.Error as err:
                logger.error(f"MySQL insert error: {err}")
                performance_id = None
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
            if model_ids:
                cursor = self.metrics_collection.find({"model_id": {"$in": model_ids}})
            else:
                cursor = self.metrics_collection.find()
            performance_records = list(cursor)
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
                records = list(self.metrics_collection.find({"model_id": model_id}))
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
    
    def save_performance_summary(self, file_path=None):
        if not self.historical_metrics:
            logger.warning("No historical metrics available for summary")
            return None
        
        summary_df = pd.DataFrame(self.historical_metrics)
        
        if file_path is None:
            timestamp = datetime.datetime.now().strftime("%Y%m%d%H%M%S")
            file_path = os.path.join(self.db_path, f"performance_summary_{timestamp}.csv")
        
        summary_df.to_csv(file_path, index=False)
        logger.info(f"Performance summary saved to {file_path}")
        
        return file_path
    
    def get_best_model(self, metric='profit_percentage'):
        comparison = self.compare_models(top_n=1, metric=metric)
        
        if comparison.empty:
            logger.warning("No models found for comparison")
            return None
        
        return comparison.iloc[0].to_dict()


class TradingStrategyOptimizer(MLComponentBase):
    """
    Optimize trading strategies based on historical performance.
    """
    
    def __init__(self, performance_tracker):
        super().__init__()
        self.performance_tracker = performance_tracker
        self.improvement_history = []
        logger.info("Trading strategy optimizer initialized")
    
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
