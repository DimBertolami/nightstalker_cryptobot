import os
from dotenv import load_dotenv
from pathlib import Path
import logging

# Set up logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s'
)

# Load environment variables
env_path = Path('.') / '.env'
if env_path.exists():
    load_dotenv(env_path)
else:
    logging.warning(".env file not found. Using default settings.")

# Configuration class
class Config:
    def __init__(self):
        # Authentication
        self.AUTH_USERNAME = os.getenv('AUTH_USERNAME', 'default_user')
        self.AUTH_PASSWORD = os.getenv('AUTH_PASSWORD')
        
        # API Keys
        self.API_KEY = os.getenv('API_KEY')
        self.SECRET_KEY = os.getenv('SECRET_KEY')
        
        # Development settings
        self.DEBUG = os.getenv('DEBUG', 'false').lower() == 'true'
        self.PORT = int(os.getenv('PORT', '5000'))
        
        # Validate required credentials
        if not self.AUTH_USERNAME or not self.AUTH_PASSWORD:
            logging.error("Missing required authentication credentials")
            raise ValueError("Missing required authentication credentials")
        
        # Log configuration (without sensitive info)
        logging.info(f"Configuration loaded:")
        logging.info(f"  - Debug mode: {self.DEBUG}")
        logging.info(f"  - Port: {self.PORT}")
        logging.info(f"  - Username: {self.AUTH_USERNAME}")

# Create a singleton instance
config = Config()

# Export the config instance as a module-level variable
__all__ = ['config']
