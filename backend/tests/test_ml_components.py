import unittest
import os
import pandas as pd
import numpy as np
from datetime import datetime, timedelta

from backend.ml_components.moving_average_crossover import MovingAverageCrossover
from model_trainer import AdvancedModelTrainer
from backend.advanced_indicators import AdvancedIndicators
from backend.ai_assistant import DecisionEngine
from performance_tracker import ModelPerformanceTracker, TradingStrategyOptimizer

class TestMLComponents(unittest.TestCase):

    def setUp(self):
        # Create sample data for testing
        dates = pd.date_range(start='2023-01-01', periods=100, freq='D')
        prices = np.linspace(100, 200, 100) + np.random.normal(0, 5, 100)
        self.data = pd.DataFrame({
            'close': prices,
            'open': prices * 0.98,
            'high': prices * 1.02,
            'low': prices * 0.95,
            'volume': np.random.randint(100, 1000, 100)
        }, index=dates)

        # For AdvancedModelTrainer, create synthetic dataset
        self.synthetic_data = pd.DataFrame({
            'timestamp': pd.date_range(start='2023-01-01', periods=200, freq='H'),
            'open': np.random.rand(200) * 100,
            'high': np.random.rand(200) * 100,
            'low': np.random.rand(200) * 100,
            'close': np.random.rand(200) * 100,
            'volume': np.random.rand(200) * 1000,
            'target': np.random.randint(0, 2, 200)
        })

        self.features = ['open', 'high', 'low', 'close', 'volume']

        # Config for AdvancedIndicators and DecisionEngine
        self.config = {}

    def test_moving_average_crossover(self):
        mac = MovingAverageCrossover(self.data)
        mac.initialize(self.data)
        mac.calculate_indicators()
        signals = mac.backtest()
        self.assertIn('signal', signals.columns)
        self.assertIn('positions', signals.columns)
        self.assertTrue(len(signals) > 0)

    def test_advanced_model_trainer(self):
        trainer = AdvancedModelTrainer()
        trainer.initialize()
        X_train, X_val, X_test, y_train, y_val, y_test, scalers = trainer.prepare_training_data(
            self.synthetic_data, self.features, 'target'
        )
        models, histories = trainer.train(X_train, y_train, X_val, y_val, model_types=['transformer'])
        self.assertIn('transformer', models)
        eval_results, best_model = trainer.evaluate(models, X_test, y_test, data_test=self.synthetic_data)
        self.assertIn('accuracy', eval_results[best_model])
        updated_params = trainer.self_improve(best_model, models, eval_results)
        self.assertIsInstance(updated_params, dict)

    def test_advanced_indicators(self):
        indicators = AdvancedIndicators(self.config)
        indicators.initialize()
        df = indicators.calculate_indicators(self.data)
        self.assertIn('SMA_10', df.columns)
        self.assertIn('RSI_14', df.columns)

    def test_decision_engine(self):
        engine = DecisionEngine(self.config)
        engine.initialize()
        insights = engine.analyze_market(self.data)
        self.assertIn('trend', insights)
        decision = engine.generate_decision(self.data)
        self.assertIn('decision', decision)
        self.assertIn(decision['decision'], ['buy', 'sell', 'hold'])

    def test_performance_tracker(self):
        tracker = ModelPerformanceTracker()
        tracker.initialize()
        metrics = {
            'accuracy': 0.8,
            'precision': 0.75,
            'recall': 0.7,
            'f1_score': 0.72
        }
        perf_id = tracker.record_model_performance(
            model_id='test_model',
            model_type='test',
            trading_pair='BTC-USD',
            timeframe='1h',
            metrics=metrics
        )
        self.assertIsInstance(perf_id, str)
        comparison = tracker.compare_models()
        self.assertIsInstance(comparison, pd.DataFrame)

    def test_trading_strategy_optimizer(self):
        tracker = ModelPerformanceTracker()
        optimizer = TradingStrategyOptimizer(tracker)
        optimizer.initialize()
        sample_trades = pd.DataFrame({
            'timestamp': pd.date_range(start='2023-01-01', periods=10, freq='H'),
            'action': ['buy', 'sell'] * 5,
            'price': np.random.rand(10) * 100,
            'profit': np.random.randn(10),
            'market_condition': ['trending', 'volatile', 'sideways', 'trending', 'volatile',
                                 'sideways', 'trending', 'volatile', 'sideways', 'trending']
        })
        weaknesses = optimizer.analyze_model_weaknesses('test_model', sample_trades)
        self.assertIn('model_id', weaknesses)
        suggestions = optimizer.suggest_improvements('test_model', weaknesses)
        self.assertIn('parameter_adjustments', suggestions)
        updated_params = optimizer.implement_suggestions(suggestions, {'stop_loss_pct': 0.05})
        self.assertIsInstance(updated_params, dict)

if __name__ == '__main__':
    unittest.main()
