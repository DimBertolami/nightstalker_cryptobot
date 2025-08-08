"""
Model registry for managing all prediction models.

Handles model versioning, performance tracking, and ensemble predictions.
"""

import numpy as np
import pandas as pd
import tensorflow as tf
from sklearn.ensemble import RandomForestRegressor, GradientBoostingRegressor, ExtraTreesRegressor
from sklearn.neural_network import MLPRegressor
from sklearn.model_selection import train_test_split
from sklearn.metrics import mean_squared_error, r2_score
import joblib
import os
from typing import Dict, List, Tuple
import logging

class ModelRegistry:
    def __init__(self, config: dict):
        self.config = config
        self.logger = logging.getLogger(__name__)
        self.models = {}
        self.model_performance = {}
        self.model_dir = config.get('model_dir', 'models/')
        os.makedirs(self.model_dir, exist_ok=True)

    def register_model(self, name: str, model, performance: dict):
        """Register a model with its performance metrics."""
        self.models[name] = model
        self.model_performance[name] = performance
        self.logger.info(f"Registered model {name} with performance: {performance}")

    def train_all_models(self, X: pd.DataFrame, y: pd.Series) -> dict:
        """Train all registered models."""
        X_train, X_test, y_train, y_test = train_test_split(
            X, y, test_size=0.2, random_state=42
        )

        # Traditional ML models
        self._train_traditional_models(X_train, X_test, y_train, y_test)
        
        # Deep learning models
        self._train_deep_learning_models(X_train, X_test, y_train, y_test)
        
        return self.model_performance

    def _train_traditional_models(self, X_train, X_test, y_train, y_test):
        """Train traditional machine learning models."""
        
        # Random Forest
        rf_model = RandomForestRegressor(
            n_estimators=100,
            max_depth=10,
            random_state=42,
            n_jobs=-1
        )
        rf_model.fit(X_train, y_train)
        rf_pred = rf_model.predict(X_test)
        rf_performance = {
            'rmse': np.sqrt(mean_squared_error(y_test, rf_pred)),
            'r2': r2_score(y_test, rf_pred),
            'weight': 0.25
        }
        self.register_model('random_forest', rf_model, rf_performance)

        # Gradient Boosting
        gb_model = GradientBoostingRegressor(
            n_estimators=100,
            learning_rate=0.1,
            max_depth=5,
            random_state=42
        )
        gb_model.fit(X_train, y_train)
        gb_pred = gb_model.predict(X_test)
        gb_performance = {
            'rmse': np.sqrt(mean_squared_error(y_test, gb_pred)),
            'r2': r2_score(y_test, gb_pred),
            'weight': 0.25
        }
        self.register_model('gradient_boosting', gb_model, gb_performance)

        # Extra Trees
        et_model = ExtraTreesRegressor(
            n_estimators=100,
            max_depth=10,
            random_state=42,
            n_jobs=-1
        )
        et_model.fit(X_train, y_train)
        et_pred = et_model.predict(X_test)
        et_performance = {
            'rmse': np.sqrt(mean_squared_error(y_test, et_pred)),
            'r2': r2_score(y_test, et_pred),
            'weight': 0.25
        }
        self.register_model('extra_trees', et_model, et_performance)

        # Neural Network
        nn_model = MLPRegressor(
            hidden_layer_sizes=(100, 50),
            max_iter=1000,
            random_state=42
        )
        nn_model.fit(X_train, y_train)
        nn_pred = nn_model.predict(X_test)
        nn_performance = {
            'rmse': np.sqrt(mean_squared_error(y_test, nn_pred)),
            'r2': r2_score(y_test, nn_pred),
            'weight': 0.25
        }
        self.register_model('neural_network', nn_model, nn_performance)

    def _train_deep_learning_models(self, X_train, X_test, y_train, y_test):
        """Train deep learning models."""
        
        # LSTM model
        lstm_model = self._build_lstm_model(X_train.shape[1])
        
        # Reshape data for LSTM
        X_train_lstm = X_train.values.reshape((X_train.shape[0], 1, X_train.shape[1]))
        X_test_lstm = X_test.values.reshape((X_test.shape[0], 1, X_test.shape[1]))
        
        lstm_model.fit(
            X_train_lstm, y_train,
            epochs=50,
            batch_size=32,
            validation_data=(X_test_lstm, y_test),
            verbose=0
        )
        
        lstm_pred = lstm_model.predict(X_test_lstm).flatten()
        lstm_performance = {
            'rmse': np.sqrt(mean_squared_error(y_test, lstm_pred)),
            'r2': r2_score(y_test, lstm_pred),
            'weight': 0.25
        }
        self.register_model('lstm', lstm_model, lstm_performance)

    def _build_lstm_model(self, input_dim):
        """Build LSTM model."""
        model = tf.keras.Sequential([
            tf.keras.layers.LSTM(128, return_sequences=True, input_shape=(1, input_dim)),
            tf.keras.layers.Dropout(0.2),
            tf.keras.layers.LSTM(64, return_sequences=False),
            tf.keras.layers.Dropout(0.2),
            tf.keras.layers.Dense(32, activation='relu'),
            tf.keras.layers.Dense(1)
        ])
        
        model.compile(
            optimizer='adam',
            loss='mse',
            metrics=['mae']
        )
        
        return model

    def predict_all(self, features: pd.DataFrame) -> dict:
        """Get predictions from all models."""
        predictions = {}
        
        for name, model in self.models.items():
            try:
                if name == 'lstm':
                    # Reshape for LSTM
                    features_reshaped = features.values.reshape((features.shape[0], 1, features.shape[1]))
                    pred = model.predict(features_reshaped).flatten()
                else:
                    pred = model.predict(features)
                
                predictions[name] = pred
            except Exception as e:
                self.logger.error(f"Error predicting with model {name}: {e}")
                predictions[name] = np.zeros(len(features))
        
        return predictions

    def ensemble_prediction(self, predictions: dict) -> Tuple[float, float]:
        """Calculate ensemble prediction and confidence."""
        if not predictions:
            return 0.0, 0.0
        
        # Get weights based on performance
        weights = {}
        total_weight = 0
        
        for name, performance in self.model_performance.items():
            if name in predictions:
                weight = performance.get('weight', 1.0)
                weights[name] = weight
                total_weight += weight
        
        if total_weight == 0:
            return 0.0, 0.0
        
        # Calculate weighted prediction
        weighted_pred = 0
        for name, pred in predictions.items():
            if name in weights:
                weighted_pred += np.mean(pred) * weights[name]
        
        weighted_pred /= total_weight
        
        # Calculate confidence based on model agreement
        predictions_array = np.array([pred for pred in predictions.values()])
        confidence = 1 - (np.std(predictions_array) / (np.mean(predictions_array) + 1e-8))
        
        return weighted_pred, max(0, min(1, confidence))

    def save_models(self):
        """Save all models to disk."""
        for name, model in self.models.items():
            model_path = os.path.join(self.model_dir, f"{name}.pkl")
            joblib.dump(model, model_path)
            self.logger.info(f"Saved model {name} to {model_path}")

    def load_models(self):
        """Load all models from disk."""
        for filename in os.listdir(self.model_dir):
            if filename.endswith('.pkl'):
                name = filename[:-4]
                model_path = os.path.join(self.model_dir, filename)
                self.models[name] = joblib.load(model_path)
                self.logger.info(f"Loaded model {name} from {model_path}")

    def get_model_performance(self) -> dict:
        """Get performance metrics for all models."""
        return self.model_performance

    def update_model_weights(self, new_weights: dict):
        """Update model weights based on recent performance."""
        for name, weight in new_weights.items():
            if name in self.model_performance:
                self.model_performance[name]['weight'] = weight
                self.logger.info(f"Updated weight for model {name} to {weight}")

    def get_feature_importance(self, model_name: str) -> dict:
        """Get feature importance for a specific model."""
        if model_name not in self.models:
            return {}
        
        model = self.models[model_name]
        
        if hasattr(model, 'feature_importances_'):
            return dict(zip(self.feature_names, model.feature_importances_))
        
        return {}
