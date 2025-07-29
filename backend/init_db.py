import os
import sys

# Add the current directory to Python path
sys.path.append(os.path.dirname(os.path.abspath(__file__)))

from trading_db import init_db

if __name__ == '__main__':
    init_db()
