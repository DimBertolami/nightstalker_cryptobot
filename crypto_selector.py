import sys
import pandas as pd
import numpy as np
from typing import Dict, Any, List
import logging
from datetime import datetime
import tensorflow as tf # Added for model loading
from feature_engine import FeatureEngine
from model_registry import ModelRegistry
from selection_tracker import SelectionTracker
from backend.database import engine # Import the SQLAlchemy engine
from advanced_dl_models import build_transformer_model, build_inception_time_model, build_temporal_fusion_transformer, build_ensemble_model

# Dummy classes to satisfy joblib.load for TensorFlow models
class InceptionTimeModel:
    pass

class LSTMModel:
    pass

class TransformerModel:
    pass

class EnsembleMetaLearner:
    pass

# logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')

class CryptoSelector:
    def __init__(self, config: Dict = None):
        self.config = config if config is not None else {}
        self.feature_engine = FeatureEngine(config.get('feature_engine', {}))
        # Initialize models, risk management, etc. here later
        self.model_registry = ModelRegistry(model_dir="/opt/lampp/htdocs/NS/.models")
        self.selection_tracker = SelectionTracker(db_engine=engine) # Pass the SQLAlchemy engine
        self.models = self._load_models()
        self.ensemble_meta_learner = self.model_registry.load_model("EnsembleMetaLearner")
        # if self.ensemble_meta_learner:
            # logging.info("EnsembleMetaLearner loaded successfully.")
        # else:
            # logging.warning("EnsembleMetaLearner not loaded. Predictions will use simple average.")

    def select_coins(self, raw_data: pd.DataFrame) -> pd.DataFrame:
        """
        Main entry point for coin selection.
        Integrates feature engineering, model prediction, and risk management.
        """
        import os
        # logging.info(f"Current working directory of crypto_selector.py: {os.getcwd()}")

        # 1. Feature Engineering
        # Ensure 'close' and 'volume' columns exist for FeatureEngine
        if 'current_price' in raw_data.columns:
            raw_data = raw_data.rename(columns={'current_price': 'close'})
        if 'volume_24h' in raw_data.columns:
            raw_data = raw_data.rename(columns={'volume_24h': 'volume'})
        features_df = self.feature_engine.engineer_features(raw_data)

        # 2. Model Prediction
        model_predictions = []
        for model_name, model_object in self.models.items():

        # 3. Apply Intelligent Scoring Algorithm (Ensemble Meta-Learner)
            if model_predictions:
                combined_preds_df = self._combine_predictions(model_predictions)
                features_df = features_df.merge(combined_preds_df, on='symbol', how='left')
                features_df['composite_score'] = self._calculate_composite_score(features_df)
                features_df['predicted_action'] = combined_preds_df['combined_predicted_action']
            else:
                features_df['composite_score'] = np.random.rand(len(features_df))
                features_df['predicted_action'] = np.random.choice(['buy', 'sell', 'hold'], len(features_df))

        # 4. Risk Management Integration
        # logging.info("Applying risk management...")
        sys.stderr.flush()
        # This would adjust scores or filter coins based on risk factors
        # For now, a dummy adjustment
        features_df['risk_adjusted_score'] = self._apply_risk_management(features_df['composite_score'], features_df) # Pass features_df for context

        # 5. Select top coins based on risk-adjusted score
        # For demonstration, let's select top 5 coins with 'buy' signal
        selected_coins = features_df[features_df['predicted_action'] == 'buy'] \
                                    .sort_values(by='risk_adjusted_score', ascending=False)
        
        # logging.info(f"Coin selection complete. Selected {len(selected_coins)} coins.")
        sys.stderr.flush()
        
        # Record the selection
        if not selected_coins.empty:
            # Create a copy to avoid modifying the original DataFrame for subsequent steps
            selected_coins_for_tracking = selected_coins.copy()
            # Convert selected_coins_for_tracking to a list of dictionaries and manually convert datetime objects
            selected_coins_list = selected_coins_for_tracking.to_dict(orient='records')
            for record in selected_coins_list:
                for key, value in record.items():
                    if isinstance(value, datetime):
                        record[key] = value.isoformat()
            self.selection_tracker.record_selection(selected_coins_list, {'selection_timestamp': datetime.now().isoformat(), 'model_run_id': 'dummy_run_id'}) # Add more metadata as needed

        selected_coins_df = selected_coins # Use the already selected coins

                
        return selected_coins # Return the DataFrame

    def _load_models(self) -> Dict[str, Any]:
        # Loads all trained models from the model registry.
        loaded_models = {}
        for model_name, metadata in self.model_registry.list_models().items():
            model_object = self.model_registry.load_model(model_name)
            if model_object:
                loaded_models[model_name] = model_object
        return loaded_models

    def _calculate_composite_score(self, features_df: pd.DataFrame) -> pd.Series:
        """
        Calculates the composite score based on the defined formula.
        Uses existing features as proxies for the components.
        """
        # Ensure necessary columns exist, fill missing with 0 or a sensible default
        # This is a simplified mapping. In a real system, these would be derived more rigorously.
        technical_momentum = features_df['rsi'].fillna(0) / 100.0 # Normalize RSI to 0-1
        volume_confirmation = features_df['on_balance_volume'].fillna(0)
        # Normalize OBV for scoring, e.g., min-max scaling or rank
        if not volume_confirmation.empty and volume_confirmation.max() > 0:
            volume_confirmation = (volume_confirmation - volume_confirmation.min()) / (volume_confirmation.max() - volume_confirmation.min())
        else:
            volume_confirmation = pd.Series(0.5, index=features_df.index) # Default if no variance

        # Risk_Adjusted_Return: Placeholder, could be derived from backtesting or a combination of other scores
        # For now, let's use a combination of combined_prediction_score and inverse volatility
        # Ensure 'combined_prediction_score' exists, provide a default if not.
        combined_prediction_score = features_df['combined_prediction_score'] if 'combined_prediction_score' in features_df.columns else pd.Series(0.5, index=features_df.index)
        risk_adjusted_return = combined_prediction_score * (1 - features_df['age_adjusted_volatility'].fillna(0))
        # Normalize risk_adjusted_return
        if not risk_adjusted_return.empty and risk_adjusted_return.max() > 0:
            risk_adjusted_return = (risk_adjusted_return - risk_adjusted_return.min()) / (risk_adjusted_return.max() - risk_adjusted_return.min())
        else:
            risk_adjusted_return = pd.Series(0.5, index=features_df.index)

        # Market_Regime_Score: Placeholder, would come from a market regime classification model
        market_regime_score = pd.Series(0.7, index=features_df.index) # Dummy value for now

        # Correlation_Adjusted_Score: Placeholder, requires portfolio correlation analysis
        correlation_adjusted_score = pd.Series(0.8, index=features_df.index) # Dummy value for now

        composite_score = (
            technical_momentum * 0.25 +
            volume_confirmation * 0.20 +
            risk_adjusted_return * 0.20 +
            market_regime_score * 0.15 +
            correlation_adjusted_score * 0.20
        )
        return composite_score

    def _apply_risk_management(self, composite_scores: pd.Series, features_df: pd.DataFrame) -> pd.Series:
        """
        Applies dynamic position sizing, drawdown control, correlation adjustments, and regime-adaptive scoring.
        """
        # Dynamic Position Sizing: Based on model confidence (composite_scores) and market volatility
        # Higher composite_score and lower volatility_impact should lead to higher effective score.
        volatility_impact = features_df['age_adjusted_volatility'].fillna(0)
        if not volatility_impact.empty and volatility_impact.max() > 0:
            volatility_impact = (volatility_impact - volatility_impact.min()) / (volatility_impact.max() - volatility_impact.min())
        else:
            volatility_impact = pd.Series(0.0, index=features_df.index)

        # A simple dynamic sizing factor: higher for confident, lower for volatile
        # This factor will scale the composite score.
        dynamic_sizing_factor = 1 - (volatility_impact * 0.3) # Up to 30% reduction for high volatility
        dynamic_sizing_factor = np.clip(dynamic_sizing_factor, 0.1, 1.0) # Ensure factor is between 0.1 and 1.0

        risk_adjusted_scores = composite_scores * dynamic_sizing_factor

        # Regime-Adaptive Scoring: Adjust selection criteria based on bull/bear/sideways markets
        # This would typically involve a market regime classification (e.g., from feature_engine or external source)
        # For now, a placeholder. Assume 'market_regime_score' from composite score calculation is used.
        # If market_regime_score is low (e.g., bear market), we might be more conservative.
        # Example: if features_df['market_regime'] == 'bear': risk_adjusted_scores *= 0.8

        # Maximum Drawdown Control: This is typically a portfolio-level risk management strategy
        # that limits the overall portfolio exposure or triggers stop-losses when drawdown limits are hit.
        # It's not directly applied to individual coin scores but influences overall position sizing.
        # Placeholder for future integration with a portfolio management module.

        # Correlation Matrix: Adjust portfolio weights based on coin correlations.
        # This also requires a portfolio context and multi-asset analysis.
        # Placeholder for future integration with a portfolio optimization module.

        return risk_adjusted_scores

    def _combine_predictions(self, model_predictions: List[pd.DataFrame]) -> pd.DataFrame:
        """
        Combines predictions from multiple models using a weighted average or a meta-learner.
        For now, a simple average, but will be replaced by an ensemble meta-learner.
        """
        if not model_predictions:
            return pd.DataFrame()

        # Assuming each DataFrame in model_predictions has 'symbol' and 'prediction_score'
        # Merge all prediction scores based on 'symbol'
        combined_df = model_predictions[0][['symbol', 'prediction_score']].rename(columns={'prediction_score': f'prediction_score_model_0'})

        for i, pred_df in enumerate(model_predictions[1:]):
            combined_df = pd.merge(combined_df, pred_df[['symbol', 'prediction_score']], on='symbol', how='left', suffixes=(None, f'_model_{i+1}'))
            combined_df.rename(columns={'prediction_score': f'prediction_score_model_{i+1}'}, inplace=True)

        prediction_columns = [col for col in combined_df.columns if 'prediction_score_model_' in col]

        if self.ensemble_meta_learner:
            # logging.info("Using Ensemble Meta-Learner to combine predictions.")
            # Prepare features for the meta-learner
            meta_features = combined_df[prediction_columns]
            # Predict using the meta-learner
            combined_df['combined_prediction_score'] = self.ensemble_meta_learner.predict_proba(meta_features)[:, 1]
            combined_df['combined_predicted_action'] = self.ensemble_meta_learner.predict(meta_features)
        else:
            # logging.info("Ensemble Meta-Learner not available. Using simple average for combining predictions.")
            combined_df['combined_prediction_score'] = combined_df[prediction_columns].mean(axis=1)
            combined_df['combined_predicted_action'] = combined_df['combined_prediction_score'].apply(lambda x: 'buy' if x > 0.5 else ('sell' if x < 0.3 else 'hold'))

        return combined_df

    def _monitor_performance(self):
        """
        Monitors the performance of the selection system in real-time.
        """
        # logging.info("Monitoring performance (placeholder)...")
        # Placeholder for model drift detection, feature importance tracking, performance attribution
        pass

if __name__ == "__main__":
    import sys
    import json

    # Read raw data from stdin
    raw_input = sys.stdin.read()
    
    try:
        input_data = json.loads(raw_input)
        # Assuming input_data is a list of dictionaries, convert to DataFrame
        raw_df = pd.DataFrame(input_data)
        
        selector = CryptoSelector(config={})
        selected_coins_df = selector.select_coins(raw_df)
        
        # Convert DataFrame to JSON string, handling datetime serialization automatically
        output_json = selected_coins_df.to_json(orient='records', date_format='iso')
        
        # Print JSON output to stdout
        print(output_json)
        sys.stdout.flush()
        
    except json.JSONDecodeError as e:
        # logging.error(f"JSON Decode Error: {e}")
        print(json.dumps({"error": "Invalid JSON input", "details": str(e)}))
        sys.exit(1)
    except Exception as e:
        # logging.error(f"An unexpected error occurred: {e}")
        print(json.dumps({"error": "Internal server error", "details": str(e)}))
        sys.exit(1)