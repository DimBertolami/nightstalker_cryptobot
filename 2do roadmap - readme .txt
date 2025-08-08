Overall Goal: To intelligently integrate all ML/DL models and advanced indicators into your
  application, using your existing NS database, and to measure and report their performance clearly on
  the dashboard.

  ---

  Phase 1: Database Unification & Schema Consolidation (Revised)

   * Decision: We will use your existing NS database as the single source of truth. This means we need to
     integrate the LearningMetric and TradingPerformance schemas into it.
   * Action:
DONE   1. configure `NS` Database Connection: connection details for NS database in includes/config.php
DONE   2. Consolidate Python Models:
DONE           * Create a new Python file: backend/models/unified_models.py.
DONE           * Move the definitions of LearningMetric and TradingPerformance (from backend/app.py) into
             unified_models.py.
DONE           * Move the definitions of Trade, TradingSignal, and Position (from backend/trading_db.py) into
             unified_models.py.
DONE           * Change the ML Models and deep neural network code to classes that inherrit from a base class. 
           /opt/lampp/htdocs/NS/backend/ml_components
		   621 	advanced_dl_models.py
		 17109 	advanced_indicators.py
		   233 	advanced_model_trainer.py
		  4121 	ai_strategy.py
		  3278 	database.py
		  9845 	decision_engine.py
		   295 	deep_learning_models.py
		   882 	lstm.py
		  1026 	ml_component_base.py
		 10289 	ml_strategy.py
		  2480 	model.py
		   644 	model.py.save
		   235 	moving_average_crossover.py
		 19863 	performance_tracker.py
		  1086 	randomForest.py
		  9407 	risk_management.py
		  1050 	rnn_lstm.py
		 25045 	trading_strategy_integration.py
		  1044 	xgboost.py
DONE           * Move Indicators from /opt/lampp/htdocs/NS/bot/indicators to the same directory as above.


DONE           * Crucially, ensure all these models inherit from a single `Base` object defined in 
             `unified_models.py`.
DONE       3. Update Database Engine in Python:
DONE           * In backend/app.py, backend/ml_pipeline.py, backend/trading_bot.py, and any other Python scripts
             that interact with the database, update the DATABASE_URL to match your NS database connection
             string. This will likely involve changing sqlite:///trading.db to something like
             mysql+pymysql://user:password@host/ns_database_name or sqlite:///path/to/ns.db.
DONE           * Import Base, engine, and SessionLocal (or Session) from unified_models.py.
DONE       4. Database Initialization: Ensure Base.metadata.create_all(engine) is called only once at application
           startup (e.g., in backend/app.py or a dedicated init_db.py script that's executed when your
          backend starts). This will create the necessary tables in your NS database if they don't already
          exist.

  ---

  Phase 2: ML/DL Model & Indicator Integration (Revised)

   * Decision: Leverage the existing ML/DL models and indicator calculation logic found in
     deep_learning_models.py, advanced_indicators.py, ml_pipeline.py, model_trainer.py, and
     trading_strategy_integration.py.
   * Action:
DONE       1. Centralized Model & Indicator Access:
DONE           * Create a new directory backend/ml_components/.
DONE           * Move deep_learning_models.py and advanced_indicators.py into backend/ml_components/.
DONE           * Refactor ml_pipeline.py, model_trainer.py, and trading_strategy_integration.py to import models
             and indicators from backend/ml_components/.
DONE       2. Data Flow for Training and Prediction:
DONE           * Data Fetching: The fetch_large_dataset in train_advanced_models.py and fe_preprocess in
             trading_strategy_integration.py are good starting points. We need to ensure they consistently
             provide data in a format suitable for feature engineering.
DONE           * Feature Engineering: Consolidate and standardize the feature creation logic. The create_features
             method in MLTradingPipeline (from ml_pipeline.py) and the indicator calculations in
             advanced_indicators.py are key. We'll ensure all necessary features are generated before being
             fed to models.
DONE       3. Model Training and Evaluation:
DONE           * `model_trainer.py`: This script will be the primary orchestrator for training.
DONE           * `LearningMetric` Population: Modify the evaluate_models method within AdvancedModelTrainer (in
             model_trainer.py) to insert data into the LearningMetric table after each model's evaluation.
             This will capture model performance metrics (accuracy, precision, recall, f1_score, etc.) and
             training details.
DONE       4. Trading Decision and Execution:
DONE           * `trading_strategy_integration.py`: This script will handle making trading decisions and
             executing trades.
DONE           * `TradingPerformance` Population: Modify the execute_trades method within
             IntegratedTradingStrategy (in trading_strategy_integration.py) to insert data into the
             TradingPerformance table after each simulated or real trade. This will capture trade-specific
             metrics (profit/loss, entry/exit prices, etc.).
DONE       5. Risk Management Integration: Ensure the RiskManager from bot/ml/risk_management.py is properly
          integrated into the trading decision process (e.g., within make_trading_decision in ml_pipeline.py
          or trading_strategy_integration.py) to enforce risk limits.

  ---

  Phase 3: Performance Measurement & Reporting (Revised)

   * Dashboard Integration:
DONE       * The get_chart_data function in backend/app.py will now query the unified NS database for
         LearningMetric and TradingPerformance data.
DONE       * Ensure the data returned by get_chart_data matches the format expected by BotPerformanceCharts.js.
   * Comprehensive Reporting:
       * Utilize explanation_engine.py and model_evaluation.py to generate detailed reports on bot
         performance, decision patterns, and model comparisons. These reports can be saved as files (e.g.,
         HTML, JSON, PDF) and potentially linked from the dashboard.

  ---

  High-Level Implementation Steps:

DONE   1. Find `NS` DB Connection: I will start by searching for your NS database connection details in your PHP
      files.
DONE   2. Consolidate Models: Create backend/models/unified_models.py and move all model definitions there.
DONE   3. Update Python DB Connections: Modify all Python scripts to use the NS database and import models from
      unified_models.py.
DONE   4. Refactor ML Components: Move and refactor ML/DL model and indicator code into backend/ml_components/.
DONE   5. Implement Data Logging: Add session.add(LearningMetric(...)) and session.add(TradingPerformance(...))
      calls in model_trainer.py and trading_strategy_integration.py respectively.
DONE   6. Update Dashboard API: Ensure backend/app.py correctly queries the unified database.
DONE   7. Testing and Verification: Thoroughly test the entire pipeline to ensure data flows correctly and the
      dashboard displays accurate, live data.