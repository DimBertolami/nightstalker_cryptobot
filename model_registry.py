import os
import json
import logging
from typing import Dict, Any
import joblib
from datetime import datetime

logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')

class ModelRegistry:
    def __init__(self, model_dir: str = "./models"):
        self.model_dir = model_dir
        os.makedirs(self.model_dir, exist_ok=True)
        self.registered_models = self._load_registered_models()

    def _load_registered_models(self) -> Dict[str, Any]:
        """
        Loads metadata of registered models from the model directory.
        """
        registered = {}
        for model_name in os.listdir(self.model_dir):
            model_path = os.path.join(self.model_dir, model_name)
            if os.path.isdir(model_path):
                metadata_path = os.path.join(model_path, "metadata.json")
                if os.path.exists(metadata_path):
                    try:
                        with open(metadata_path, 'r') as f:
                            metadata = json.load(f)
                        registered[model_name] = metadata
                        
                    except Exception as e:
                        logging.warning(f"Could not load metadata for {model_name}: {e}")
        return registered

    def register_model(self, model_name: str, model_object: Any, metadata: Dict = None) -> bool:
        """
        Registers a new model or a new version of an existing model.
        Saves model object using joblib and metadata.
        """
        model_path = os.path.join(self.model_dir, model_name)
        os.makedirs(model_path, exist_ok=True)

        # Save model object using joblib
        model_file_path = os.path.join(model_path, "model.joblib")
        try:
            joblib.dump(model_object, model_file_path)
            logging.info(f"Model object for {model_name} saved to {model_file_path}")
        except Exception as e:
            logging.error(f"Failed to save model object for {model_name}: {e}")
            return False

        # Save metadata
        metadata = metadata if metadata is not None else {}
        metadata['name'] = model_name
        metadata['timestamp'] = datetime.now().isoformat()
        metadata_path = os.path.join(model_path, "metadata.json")
        try:
            with open(metadata_path, 'w') as f:
                json.dump(metadata, f, indent=4)
            self.registered_models[model_name] = metadata
            logging.info(f"Model '{model_name}' registered successfully.")
            return True
        except Exception as e:
            logging.error(f"Failed to register model '{model_name}': {e}")
            return False

    def get_model_metadata(self, model_name: str) -> Dict[str, Any]:
        """
        Retrieves metadata for a specific model.
        """
        return self.registered_models.get(model_name)

    def list_models(self) -> Dict[str, Any]:
        """
        Lists all registered models and their latest metadata.
        """
        return self.registered_models

    def load_model(self, model_name: str) -> Any:
        """
        Loads a specific model object using joblib.
        """
        logging.info(f"Attempting to load model: {model_name}")
        model_metadata = self.get_model_metadata(model_name)
        if model_metadata:
            model_file_path = os.path.join(self.model_dir, model_name, "model.joblib")
            logging.info(f"Checking for model file at: {model_file_path}")
            if os.path.exists(model_file_path):
                try:
                    model_object = joblib.load(model_file_path)
                    
                    return model_object
                except Exception as e:
                    logging.error(f"Failed to load model object for {model_name} from {model_file_path}: {e}")
                    return None
            else:
                logging.warning(f"Model file for '{model_name}' not found at {model_file_path}.")
                return None
        else:
            logging.warning(f"Model '{model_name}' not found in registry metadata.")
            return None

if __name__ == "__main__":
    print("Running ModelRegistry example...")
    registry = ModelRegistry()

    # Register a dummy model
    dummy_model_object = {"type": "Transformer", "version": "1.0"}
    registry.register_model("TransformerModel_v1", dummy_model_object, 
                            metadata={'description': 'Initial Transformer model', 'performance': {'accuracy': 0.75}})

    # Register another dummy model
    dummy_model_object_lstm = {"type": "LSTM", "version": "1.0"}
    registry.register_model("LSTMModel_v1", dummy_model_object_lstm, 
                            metadata={'description': 'Initial LSTM model', 'performance': {'accuracy': 0.72}})

    # List all models
    print("\nRegistered Models:")
    for name, metadata in registry.list_models().items():
        print(f"  - {name}: {metadata.get('description', 'No description')} (Accuracy: {metadata.get('performance', {}).get('accuracy', 'N/A')})")

    # Load a specific model
    loaded_model = registry.load_model("TransformerModel_v1")
    print(f"\nLoaded model: {loaded_model}")

    # Try to load a non-existent model
    non_existent_model = registry.load_model("NonExistentModel")
    print(f"Loaded non-existent model: {non_existent_model}")

