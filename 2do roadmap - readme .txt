  Overall Goal: To intelligently integrate all ML/DL models and advanced indicators into your
  application, using your existing NS database, and to measure and report their performance clearly on
  the dashboard.

  ---

  Phase 1: Database Unification & Schema Consolidation (Revised)

   * Decision: We will use your existing NS database as the single source of truth. This means we need to
     integrate the LearningMetric and TradingPerformance schemas into it.
   * Action:
       1. Identify `NS` Database Connection: I need to find the database connection details for your NS
          database. Based on the file structure, it's likely defined in a PHP file like includes/config.php
          or database/. I will search for this.
       2. Consolidate Python Models:
           * Create a new Python file: backend/models/unified_models.py.
           * Move the definitions of LearningMetric and TradingPerformance (from backend/app.py) into
             unified_models.py.
           * Move the definitions of Trade, TradingSignal, and Position (from backend/trading_db.py) into
             unified_models.py.
           * Crucially, ensure all these models inherit from a single `Base` object defined in 
             `unified_models.py`.
       3. Update Database Engine in Python:
           * In backend/app.py, backend/ml_pipeline.py, backend/trading_bot.py, and any other Python scripts
             that interact with the database, update the DATABASE_URL to match your NS database connection
             string. This will likely involve changing sqlite:///trading.db to something like
             mysql+pymysql://user:password@host/ns_database_name or sqlite:///path/to/ns.db.
           * Import Base, engine, and SessionLocal (or Session) from unified_models.py.
       4. Database Initialization: Ensure Base.metadata.create_all(engine) is called only once at application
           startup (e.g., in backend/app.py or a dedicated init_db.py script that's executed when your
          backend starts). This will create the necessary tables in your NS database if they don't already
          exist.

  ---

  Phase 2: ML/DL Model & Indicator Integration (Revised)

   * Decision: Leverage the existing ML/DL models and indicator calculation logic found in
     deep_learning_models.py, advanced_indicators.py, ml_pipeline.py, model_trainer.py, and
     trading_strategy_integration.py.
   * Action:
       1. Centralized Model & Indicator Access:
           * Create a new directory backend/ml_components/.
           * Move deep_learning_models.py and advanced_indicators.py into backend/ml_components/.
           * Refactor ml_pipeline.py, model_trainer.py, and trading_strategy_integration.py to import models
             and indicators from backend/ml_components/.
       2. Data Flow for Training and Prediction:
           * Data Fetching: The fetch_large_dataset in train_advanced_models.py and fe_preprocess in
             trading_strategy_integration.py are good starting points. We need to ensure they consistently
             provide data in a format suitable for feature engineering.
           * Feature Engineering: Consolidate and standardize the feature creation logic. The create_features
             method in MLTradingPipeline (from ml_pipeline.py) and the indicator calculations in
             advanced_indicators.py are key. We'll ensure all necessary features are generated before being
             fed to models.
       3. Model Training and Evaluation:
           * `model_trainer.py`: This script will be the primary orchestrator for training.
           * `LearningMetric` Population: Modify the evaluate_models method within AdvancedModelTrainer (in
             model_trainer.py) to insert data into the LearningMetric table after each model's evaluation.
             This will capture model performance metrics (accuracy, precision, recall, f1_score, etc.) and
             training details.
       4. Trading Decision and Execution:
           * `trading_strategy_integration.py`: This script will handle making trading decisions and
             executing trades.
           * `TradingPerformance` Population: Modify the execute_trades method within
             IntegratedTradingStrategy (in trading_strategy_integration.py) to insert data into the
             TradingPerformance table after each simulated or real trade. This will capture trade-specific
             metrics (profit/loss, entry/exit prices, etc.).
       5. Risk Management Integration: Ensure the RiskManager from bot/ml/risk_management.py is properly
          integrated into the trading decision process (e.g., within make_trading_decision in ml_pipeline.py
          or trading_strategy_integration.py) to enforce risk limits.

  ---

  Phase 3: Performance Measurement & Reporting (Revised)

   * Dashboard Integration:
       * The get_chart_data function in backend/app.py will now query the unified NS database for
         LearningMetric and TradingPerformance data.
       * Ensure the data returned by get_chart_data matches the format expected by BotPerformanceCharts.js.
   * Comprehensive Reporting:
       * Utilize explanation_engine.py and model_evaluation.py to generate detailed reports on bot
         performance, decision patterns, and model comparisons. These reports can be saved as files (e.g.,
         HTML, JSON, PDF) and potentially linked from the dashboard.

  ---

  High-Level Implementation Steps:

   1. Find `NS` DB Connection: I will start by searching for your NS database connection details in your PHP
      files.
   2. Consolidate Models: Create backend/models/unified_models.py and move all model definitions there.
   3. Update Python DB Connections: Modify all Python scripts to use the NS database and import models from
      unified_models.py.
   4. Refactor ML Components: Move and refactor ML/DL model and indicator code into backend/ml_components/.
   5. Implement Data Logging: Add session.add(LearningMetric(...)) and session.add(TradingPerformance(...))
      calls in model_trainer.py and trading_strategy_integration.py respectively.
   6. Update Dashboard API: Ensure backend/app.py correctly queries the unified database.
   7. Testing and Verification: Thoroughly test the entire pipeline to ensure data flows correctly and the
      dashboard displays accurate, live data.