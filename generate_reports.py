import pandas as pd
import numpy as np
import json
import logging
from datetime import datetime, timedelta
from sqlalchemy import create_engine, text
from typing import Dict, Any, List

# Assuming these are available in the environment or can be imported
from explanation_engine import ExplanationEngine
from model_evaluation import evaluate_classification_model, evaluate_trading_performance, evaluate_deep_learning_model, compare_models, backtest_ensemble

logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')

class ReportGenerator:
    def __init__(self, db_connection_string: str, config: Dict = None):
        self.db_connection_string = db_connection_string
        self.config = config if config is not None else {}
        self.engine = self._create_db_engine()
        self.explanation_engine = ExplanationEngine(config=self.config.get('explanation_engine', {}), db_engine=self.engine)

    def _create_db_engine(self):
        try:
            engine = create_engine(self.db_connection_string)
            with engine.connect() as connection:
                connection.execute(text("SELECT 1"))
            logging.info("Database connection successful.")
            return engine
        except Exception as e:
            logging.error(f"Database connection failed: {e}")
            raise

    def generate_comprehensive_report(self, timeframe: str = 'daily') -> str:
        report_data = {
            "title": f"Comprehensive Crypto Trading Bot Report - {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}",
            "summary": self._get_summary_data(timeframe),
            "performance_metrics": self._get_performance_metrics(),
            "trading_performance": self._get_trading_performance_data(),
            "decision_patterns": self._get_decision_patterns_data(),
            "model_comparison": self._get_model_comparison_data()
        }
        return self._render_html_report(report_data)

    def _get_summary_data(self, timeframe: str) -> Dict:
        # Placeholder for summary data from explanation_engine
        # This would involve calling explanation_engine.generate_report(timeframe)
        # For now, return dummy data
        return {
            "period": timeframe,
            "total_decisions": 100,
            "buy_signals": 50,
            "sell_signals": 30,
            "hold_signals": 20,
            "avg_confidence": 0.75
        }

    def _get_performance_metrics(self) -> Dict:
        # Fetch data from learning_metrics table
        try:
            query = "SELECT AVG(accuracy) as avg_accuracy, AVG(model_precision) as avg_precision, AVG(recall) as avg_recall, AVG(f1_score) as avg_f1_score FROM learning_metrics"
            with self.engine.connect() as connection:
                result = connection.execute(text(query)).fetchone()
                if result:
                    return {
                        "accuracy": result.avg_accuracy,
                        "precision": result.avg_precision,
                        "recall": result.avg_recall,
                        "f1_score": result.avg_f1_score
                    }
                return {}
        except Exception as e:
            logging.error(f"Error fetching performance metrics: {e}")
            return {}

    def _get_trading_performance_data(self) -> Dict:
        # This would typically involve fetching actual trading data and predictions
        # and then calling evaluate_trading_performance from model_evaluation.py
        # For now, return dummy data
        return {
            "initial_balance": 10000,
            "final_balance": 12000,
            "total_return": 0.20,
            "annualized_return": 0.25,
            "max_drawdown": 0.10
        }

    def _get_decision_patterns_data(self) -> List[Dict]:
        # Fetch data from trading_signals table
        try:
            query = "SELECT trade_signal, COUNT(*) as frequency, AVG(confidence) as avg_confidence FROM trading_signals GROUP BY trade_signal"
            with self.engine.connect() as connection:
                result = connection.execute(text(query)).fetchall()
                patterns = []
                for row in result:
                    patterns.append({
                        "signal": row.trade_signal,
                        "frequency": row.frequency,
                        "avg_confidence": f'{row.avg_confidence:.2f}' if isinstance(row.avg_confidence, (int, float)) else 'N/A'
                    })
                return patterns
        except Exception as e:
            logging.error(f"Error fetching decision patterns: {e}")
            return []

    def _get_model_comparison_data(self) -> List[Dict]:
        # This would involve fetching evaluation results for different models
        # and potentially calling compare_models from model_evaluation.py
        # For now, return dummy data
        return [
            {"model": "Model A", "accuracy": f'{0.85:.2f}', "f1_score": f'{0.82:.2f}'},
            {"model": "Model B", "accuracy": f'{0.88:.2f}', "f1_score": f'{0.86:.2f}'}
        ]

    def _render_html_report(self, data: Dict) -> str:
        html_content = f"""
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>{data['title']}</title>
            <style>
                body {{ font-family: Arial, sans-serif; line-height: 1.6; margin: 20px; background-color: #f4f4f4; color: #333; }}
                .container {{ max-width: 900px; margin: auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); }}
                h1 {{ color: #0056b3; text-align: center; margin-bottom: 20px; }}
                h2 {{ color: #0056b3; border-bottom: 2px solid #eee; padding-bottom: 5px; margin-top: 30px; }}
                .section {{ margin-bottom: 20px; }}
                .metric-grid {{ display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 15px; }}
                .metric-item {{ background-color: #e9f7ff; padding: 15px; border-radius: 5px; border-left: 5px solid #007bff; }}
                .metric-item h3 {{ margin-top: 0; color: #007bff; }}
                table {{ width: 100%; border-collapse: collapse; margin-top: 15px; }}
                th, td {{ border: 1px solid #ddd; padding: 8px; text-align: left; }}
                th {{ background-color: #f2f2f2; }}
                .footer {{ text-align: center; margin-top: 40px; font-size: 0.9em; color: #777; }}
            </style>
        </head>
        <body>
            <div class="container">
                <h1>{data['title']}</h1>

                <div class="section">
                    <h2>Summary</h2>
                    <p>Report Period: {data['summary']['period']}</p>
                    <p>Total Decisions Analyzed: {data['summary']['total_decisions']}</p>
                    <p>Buy Signals: {data['summary']['buy_signals']}</p>
                    <p>Sell Signals: {data['summary']['sell_signals']}</p>
                    <p>Hold Signals: {data['summary']['hold_signals']}</p>
                    <p>Average Confidence: {data['summary']['avg_confidence']}</p>
                </div>

                <div class="section">
                    <h2>Model Performance Metrics</h2>
                    <div class="metric-grid">
                        <div class="metric-item">
                            <h3>Accuracy</h3>
                            <p>{data['performance_metrics'].get('accuracy', 'N/A')}</p>
                        </div>
                        <div class="metric-item">
                            <h3>Precision</h3>
                            <p>{data['performance_metrics'].get('precision', 'N/A')}</p>
                        </div>
                        <div class="metric-item">
                            <h3>Recall</h3>
                            <p>{data['performance_metrics'].get('recall', 'N/A')}</p>
                        </div>
                        <div class="metric-item">
                            <h3>F1 Score</h3>
                            <p>{data['performance_metrics'].get('f1_score', 'N/A')}</p>
                        </div>
                    </div>
                </div>

                <div class="section">
                    <h2>Trading Performance</h2>
                    <div class="metric-grid">
                        <div class="metric-item">
                            <h3>Initial Balance</h3>
                            <p>${data['trading_performance']['initial_balance']:.2f}</p>
                        </div>
                        <div class="metric-item">
                            <h3>Final Balance</h3>
                            <p>${data['trading_performance']['final_balance']:.2f}</p>
                        </div>
                        <div class="metric-item">
                            <h3>Total Return</h3>
                            <p>{data['trading_performance']['total_return']:.2%}</p>
                        </div>
                        <div class="metric-item">
                            <h3>Annualized Return</h3>
                            <p>{data['trading_performance']['annualized_return']:.2%}</p>
                        </div>
                        <div class="metric-item">
                            <h3>Max Drawdown</h3>
                            <p>{data['trading_performance']['max_drawdown']:.2%}</p>
                        </div>
                    </div>
                </div>

                <div class="section">
                    <h2>Decision Patterns</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Signal</th>
                                <th>Frequency</th>
                                <th>Average Confidence</th>
                            </tr>
                        </thead>
                        <tbody>
                            {''.join([f"<tr><td>{pattern['signal']}</td><td>{pattern['frequency']}</td><td>{pattern['avg_confidence']}</td></tr>" for pattern in data['decision_patterns']])}
                        </tbody>
                    </table>
                </div>

                <div class="section">
                    <h2>Model Comparison</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Model</th>
                                <th>Accuracy</th>
                                <th>F1 Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            {''.join([f"<tr><td>{model['model']}</td><td>{model['accuracy']}</td><td>{model['f1_score']}</td></tr>" for model in data['model_comparison']])}
                        </tbody>
                    </table>
                </div>

                <div class="footer">
                    <p>Report generated by Crypto Trading Bot System on {datetime.now().strftime('%Y-%m-%d')}</p>
                </div>
            </div>
        </body>
        </html>
        """
        return html_content

if __name__ == "__main__":
    # Example Usage
    # Replace with your actual database connection string
    # For XAMPP MySQL, it's typically 'mysql+mysqlconnector://root:@localhost/NS'
    # (username: root, password: '', host: localhost, database: NS)
    db_connection_string = "mysql+mysqlconnector://dimi:1304@localhost/NS"
    
    try:
        report_generator = ReportGenerator(db_connection_string=db_connection_string)
        html_report = report_generator.generate_comprehensive_report(timeframe='weekly')
        
        # Create reports directory if it doesn't exist
        reports_dir = "/opt/lampp/htdocs/NS/reports"
        import os
        os.makedirs(reports_dir, exist_ok=True)
        
        # Save the report to an HTML file
        report_filename = datetime.now().strftime("comprehensive_report_%Y%m%d_%H%M%S.html")
        report_filepath = os.path.join(reports_dir, report_filename)
        
        with open(report_filepath, "w") as f:
            f.write(html_report)
        
        logging.info(f"Report generated and saved to {report_filepath}")
        
    except Exception as e:
        logging.error(f"Failed to generate report: {e}")