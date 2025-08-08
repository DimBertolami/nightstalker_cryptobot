#!/usr/bin/env python3
"""
Test script for the intelligent crypto selection system.

This script provides comprehensive testing for all components:
1. Configuration validation
2. Feature engineering
3. Model training
4. Prediction pipeline
5. Performance tracking
"""

import os
import sys
import yaml
import pandas as pd
import numpy as np
from datetime import datetime, timedelta
import logging

# Add project root to path
sys.path.append('/opt/lampp/htdocs/NS')

# Import our components
from crypto_selector import CryptoSelector
from backend.ml_components.feature_engine import FeatureEngineer
from backend.ml_components.model_registry import ModelRegistry
from backend.ml_components.selection_tracker import SelectionTracker
from backend.database import get_db

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

class CryptoSelectorTester:
    def __init__(self):
        self.config_path = '/opt/lampp/htdocs/NS/config/crypto_selector_config.yaml'
        self.test_data_path = '/opt/lampp/htdocs/NS/test_data'
        
    def load_config(self):
        """Load and validate configuration."""
        try:
            with open(self.config_path, 'r') as f:
                config = yaml.safe_load(f)
            logger.info("✅ Configuration loaded successfully")
            return config
        except Exception as e:
            logger.error(f"❌ Configuration error: {e}")
            return None
    
    def create_test_data(self):
        """Create synthetic test data for age, marketcap, volume."""
        np.random.seed(42)
        
        # Generate 100 days of data
        dates = pd.date_range(start='2023-01-01', end='2023-04-10', freq='D')
        
        # Create synthetic data
        test_data = pd.DataFrame({
            'date': dates,
            'age': np.random.randint(30, 365, len(dates)),
            'marketcap': np.random.lognormal(20, 2, len(dates)),  # Log-normal distribution
            'volume': np.random.lognormal(15, 1.5, len(dates)),  # Log-normal distribution
            'price': np.random.lognormal(2, 0.5, len(dates))    # Log-normal distribution
        })
        
        # Add some trends
        test_data['marketcap'] = test_data['marketcap'] * (1 + np.linspace(0, 0.5, len(dates)))
        test_data['volume'] = test_data['volume'] * (1 + np.sin(np.linspace(0, 4*np.pi, len(dates))) * 0.3)
        
        # Save test data
        os.makedirs(self.test_data_path, exist_ok=True)
        test_data.to_csv(f'{self.test_data_path}/synthetic_crypto_data.csv', index=False)
        
        logger.info(f"✅ Created test data with {len(test_data)} records")
        return test_data
    
    def test_feature_engineering(self, test_data):
        """Test feature engineering component."""
        logger.info("🧪 Testing Feature Engineering...")
        
        config = self.load_config()
        if not config:
            return False
            
        feature_engineer = FeatureEngineer(config)
        
        # Test feature creation
        features = feature_engineer.create_features(test_data)
        
        # Validate features
        if features.empty:
            logger.error("❌ Feature engineering returned empty DataFrame")
            return False
            
        if features.isnull().values.any():
            logger.error("❌ Features contain NaN values")
            return False
            
        logger.info(f"✅ Feature engineering successful - {len(features.columns)} features created")
        logger.info(f"   Features: {list(features.columns)}")
        return True
    
    def test_model_training(self, test_data):
        """Test model training pipeline."""
        logger.info("🧪 Testing Model Training...")
        
        config = self.load_config()
        if not config:
            return False
            
        # Create features
        feature_engineer = FeatureEngineer(config)
        features = feature_engineer.create_features(test_data)
        
        # Create target variable (next day's price change)
        y = test_data['price'].shift(-1)[:-1]  # Remove last NaN
        
        # Remove last row from features to match y
        features = features[:-1]
        
        # Test model registry
        model_registry = ModelRegistry(config)
        
        # Train models
        performance = model_registry.train_all_models(features, y)
        
        if not performance:
            logger.error("❌ Model training failed")
            return False
            
        logger.info("✅ Model training successful")
        for model_name, metrics in performance.items():
            logger.info(f"   {model_name}: RMSE={metrics['rmse']:.4f}, R2={metrics['r2']:.4f}")
        
        return True
    
    def test_prediction_pipeline(self, test_data):
        """Test complete prediction pipeline."""
        logger.info("🧪 Testing Prediction Pipeline...")
        
        config = self.load_config()
        if not config:
            return False
            
        # Get database session
        db_session = next(get_db())
        
        # Initialize selector
        selector = CryptoSelector(config, db_session)
        
        # Test with last 30 days
        recent_data = test_data.tail(30)
        
        # Run prediction
        current_price = recent_data['price'].iloc[-1]
        result = selector.run(recent_data, current_price)
        
        if 'error' in result:
            logger.error(f"❌ Prediction failed: {result['error']}")
            return False
            
        logger.info("✅ Prediction pipeline successful")
        logger.info(f"   Decision: {result.get('decision', {}).get('decision', 'N/A')}")
        logger.info(f"   Confidence: {result.get('predictions', {}).get('confidence', 0):.4f}")
        
        return True
    
    def test_performance_tracking(self, test_data):
        """Test performance tracking."""
        logger.info("🧪 Testing Performance Tracking...")
        
        config = self.load_config()
        if not config:
            return False
            
        db_session = next(get_db())
        tracker = SelectionTracker(config, db_session)
        
        # Record some test decisions
        for i, row in test_data.tail(5).iterrows():
            tracker.record_decision(
                decision='BUY' if np.random.random() > 0.5 else 'SELL',
                price=row['price'],
                prediction=row['price'] * (1 + np.random.normal(0, 0.02)),
                confidence=np.random.uniform(0.6, 0.9),
                risk_score=np.random.uniform(0.1, 0.5),
                coin_id='TEST_COIN'
            )
        
        # Test performance metrics
        metrics = tracker.calculate_performance_metrics(days=7)
        
        if not metrics:
            logger.error("❌ Performance tracking failed")
            return False
            
        logger.info("✅ Performance tracking successful")
        logger.info(f"   Total trades: {metrics.get('total_trades', 0)}")
        logger.info(f"   Win rate: {metrics.get('win_rate', 0):.2%}")
        
        return True
    
    def run_all_tests(self):
        """Run comprehensive test suite."""
        logger.info("🚀 Starting Crypto Selector Test Suite")
        
        # Create test data
        test_data = self.create_test_data()
        
        # Run tests
        tests = [
            ("Configuration", lambda: self.load_config() is not None),
            ("Feature Engineering", lambda: self.test_feature_engineering(test_data)),
            ("Model Training", lambda: self.test_model_training(test_data)),
            ("Prediction Pipeline", lambda: self.test_prediction_pipeline(test_data)),
            ("Performance Tracking", lambda: self.test_performance_tracking(test_data))
        ]
        
        results = {}
        for test_name, test_func in tests:
            try:
                results[test_name] = test_func()
                status = "✅ PASS" if results[test_name] else "❌ FAIL"
                logger.info(f"{status} - {test_name}")
            except Exception as e:
                results[test_name] = False
                logger.error(f"❌ FAIL - {test_name}: {e}")
        
        # Summary
        passed = sum(results.values())
        total = len(results)
        
        logger.info("\n📊 Test Summary:")
        logger.info(f"   Passed: {passed}/{total}")
        logger.info(f"   Success Rate: {passed/total*100:.1f}%")
        
        if passed == total:
            logger.info("🎉 All tests passed! System is ready for deployment.")
        else:
            logger.warning("⚠️  Some tests failed. Please review the logs above.")
        
        return results

if __name__ == "__main__":
    tester = CryptoSelectorTester()
    results = tester.run_all_tests()
    
    # Exit with appropriate code
    sys.exit(0 if all(results.values()) else 1)
