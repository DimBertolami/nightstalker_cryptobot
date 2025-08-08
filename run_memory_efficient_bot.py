import logging
import os
import sys
import json
from backend.memory_efficient_bot import MemoryEfficientTradingBot

# Setup logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler('logs/memory_efficient_bot.log'),
        logging.StreamHandler()
    ]
)

logger = logging.getLogger(__name__)

def main():
    try:
        logger.info("Starting Memory-Efficient Trading Bot...")
        config_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'config', 'memory_efficient_config.json')
        bot = MemoryEfficientTradingBot(config_path)
        bot.run()
    except KeyboardInterrupt:
        logger.info("Bot stopped by user")
        bot.stop()
    except Exception as e:
        logger.error(f"Critical error: {str(e)}")
        sys.exit(1)

if __name__ == "__main__":
    main()
