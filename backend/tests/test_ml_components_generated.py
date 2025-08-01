import unittest
import pandas as pd
from backend.ml_components.advanced_indicators import AdvancedIndicators
from backend.ml_components.advanced_model_trainer import AdvancedModelTrainer
from backend.ml_components.decision_engine import DecisionEngine
from backend.ml_components.moving_average_crossover import MovingAverageCrossover
from backend.ml_components.performance_tracker import ModelPerformanceTracker, TradingStrategyOptimizer

class TestMLComponents(unittest.TestCase):
    def setUp(self):
        self.config = {}
        self.data = pd.DataFrame({
            'Close': [1, 2, 3, 4, 5, 6],
            'High': [1, 2, 3, 4, 5, 6],
            'Low': [1, 2, 3, 4, 5, 6],
            'Volume': [100, 200, 300, 400, 500, 600]
        })

    def test_advanced_indicators_initialize(self):
        indicators = AdvancedIndicators(self.config)
        indicators.initialize()  # Should not raise

    def test_advanced_model_trainer_methods(self):
        trainer = AdvancedModelTrainer()
        trainer.evaluate()
        trainer.load()
        trainer.save()
        trainer.train()

    def test_decision_engine_methods(self):
        engine = DecisionEngine(self.config)
        decision = engine.generate_decision(self.data)
        self.assertIn('decision', decision)

    def test_moving_average_crossover_methods(self):
        mac = MovingAverageCrossover()
        mac.initialize()
        mac.evaluate()
        mac.load()
        mac.save()
        mac.train()

    def test_performance_tracker_methods(self):
        tracker = ModelPerformanceTracker()
        tracker.evaluate()
        tracker.load()
        tracker.save()
        tracker.train()

    def test_trading_strategy_optimizer_methods(self):
        optimizer = TradingStrategyOptimizer()
        optimizer.evaluate()
        optimizer.load()
        optimizer.save()
        optimizer.train()

if __name__ == '__main__':
    unittest.main()
