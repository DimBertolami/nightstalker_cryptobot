import logging
import json
from typing import Dict, Any, List
import pandas as pd
import numpy as np
from datetime import datetime, timedelta
from sklearn.preprocessing import StandardScaler
from sklearn.cluster import KMeans
from sklearn.feature_extraction.text import CountVectorizer
from sklearn.decomposition import LatentDirichletAllocation

logger = logging.getLogger(__name__)

class ExplanationEngine:
    def __init__(self, config: Dict, db_engine=None):
        self.config = config
        self.db_engine = db_engine
        self.scaler = StandardScaler()
        self.topic_model = LatentDirichletAllocation(n_components=5, random_state=42)
        self.vectorizer = CountVectorizer()
        self.decision_history = []
        self.insight_history = []
        self.patterns = {}

    def explain_decision(self, decision_data: Dict) -> Dict:
        """Generate human-readable explanation for a decision"""
        try:
            explanation = {
                'summary': self._generate_summary(decision_data),
                'reasoning': self._generate_reasoning(decision_data),
                'confidence': self._explain_confidence(decision_data),
                'context': self._explain_context(decision_data),
                'patterns': self._identify_patterns(decision_data)
            }
            
            self.decision_history.append({
                'timestamp': datetime.now(),
                'decision': decision_data['decision'],
                'explanation': explanation
            })
            
            return explanation
            
        except Exception as e:
            logger.error(f"Error explaining decision: {e}")
            return {
                'summary': 'Error generating explanation',
                'reasoning': [],
                'confidence': 'Unknown',
                'context': {},
                'patterns': []
            }

    def _generate_summary(self, decision_data: Dict) -> str:
        """Generate concise summary of decision"""
        decision = decision_data['decision'].upper()
        confidence = int(decision_data['confidence'] * 100)
        market_regime = decision_data['market_regime']
        
        summary = f"Decision: {decision} (Confidence: {confidence}%)\n" \
                  f"Market Regime: {market_regime}\n" \
                  f"Reason: {', '.join(decision_data['reasoning'][:2])}"
        
        return summary

    def _generate_reasoning(self, decision_data: Dict) -> List[str]:
        """Generate detailed reasoning"""
        reasoning = decision_data['reasoning']
        insights = decision_data['insights']
        
        detailed_reasons = []
        
        for reason in reasoning:
            if 'trend' in reason.lower():
                trend = insights.get('trend', {})
                detailed_reasons.append(
                    f"Trend Analysis: {trend.get('direction', 'Unknown')} with strength {trend.get('strength', 0.5):.2f}"
                )
            
            if 'momentum' in reason.lower():
                momentum = insights.get('momentum', {})
                detailed_reasons.append(
                    f"Momentum: {'Overbought' if momentum.get('overbought', False) else 'Oversold'} with confidence {momentum.get('confidence', 0.5):.2f}"
                )
            
            if 'volatility' in reason.lower():
                volatility = insights.get('volatility', {})
                detailed_reasons.append(
                    f"Volatility: {'High' if volatility.get('high_volatility', False) else 'Low'} with risk level {volatility.get('risk_level', 0.5):.2f}"
                )
            
            if 'sentiment' in reason.lower():
                sentiment = insights.get('sentiment', {})
                detailed_reasons.append(
                    f"Sentiment: {'Positive' if sentiment.get('positive_sentiment', False) else 'Negative'} with confidence {sentiment.get('confidence', 0.5):.2f}"
                )
        
        return detailed_reasons

    def _explain_confidence(self, decision_data: Dict) -> Dict:
        """Break down confidence score"""
        confidence = decision_data['confidence']
        breakdown = {
            'overall': confidence,
            'components': {}
        }
        
        for strategy in ['trend', 'momentum', 'volatility', 'sentiment']:
            insights = decision_data['insights'].get(strategy, {})
            strength = insights.get('strength', 0.5)
            confidence = insights.get('confidence', 0.5)
            breakdown['components'][strategy] = {
                'strength': strength,
                'confidence': confidence,
                'contribution': strength * confidence
            }
        
        return breakdown

    def _explain_context(self, decision_data: Dict) -> Dict:
        """Provide context for decision"""
        context = {
            'market_conditions': self._get_market_conditions(decision_data),
            'historical_patterns': self._get_historical_patterns(),
            'risk_factors': self._get_risk_factors(decision_data),
            'timeframe': self._get_timeframe_context()
        }
        
        return context

    def _identify_patterns(self, decision_data: Dict) -> List[Dict]:
        """Identify patterns in decision making"""
        patterns = []
        
        # Check for similar decisions
        similar_decisions = self._find_similar_decisions(decision_data)
        if similar_decisions:
            patterns.append({
                'type': 'Decision Pattern',
                'description': f"Similar decision pattern found {len(similar_decisions)} times",
                'confidence': np.mean([d['confidence'] for d in similar_decisions])
            })
        
        # Check for market regime patterns
        regime_patterns = self._find_regime_patterns(decision_data)
        if regime_patterns:
            patterns.extend(regime_patterns)
        
        return patterns

    def generate_report(self, timeframe: str = 'daily') -> Dict:
        """Generate comprehensive report"""
        try:
            report = {
                'summary': self._generate_report_summary(timeframe),
                'performance': self._analyze_performance(timeframe),
                'decision_patterns': self._analyze_decision_patterns(timeframe),
                'risk_analysis': self._analyze_risk(timeframe),
                'market_insights': self._analyze_market(timeframe)
            }
            
            return report
            
        except Exception as e:
            logger.error(f"Error generating report: {e}")
            return {
                'summary': 'Error generating report',
                'performance': {},
                'decision_patterns': [],
                'risk_analysis': {},
                'market_insights': {}
            }

    def _generate_report_summary(self, timeframe: str) -> str:
        """Generate summary of recent activity"""
        recent_decisions = self._get_recent_decisions(timeframe)
        
        if not recent_decisions:
            return "No recent decisions to report"
            
        buy_count = sum(1 for d in recent_decisions if d['decision'] == 'buy')
        sell_count = sum(1 for d in recent_decisions if d['decision'] == 'sell')
        hold_count = sum(1 for d in recent_decisions if d['decision'] == 'hold')
        
        avg_confidence = np.mean([d['confidence'] for d in recent_decisions])
        
        summary = f"Summary of {timeframe} activity:\n"
        summary += f"Decisions: Buy({buy_count}) Sell({sell_count}) Hold({hold_count})\n"
        summary += f"Average Confidence: {avg_confidence:.2f}\n"
        summary += f"Market Regime: {recent_decisions[-1]['market_regime']}\n"
        
        return summary

    def _analyze_performance(self, timeframe: str) -> Dict:
        """Analyze trading performance"""
        if not self.db_engine:
            return {}

        try:
            query = "SELECT AVG(accuracy) as avg_accuracy, AVG(`precision`) as avg_precision, AVG(recall) as avg_recall, AVG(f1_score) as avg_f1_score FROM learning_metrics"
            df = pd.read_sql(query, self.db_engine)
            performance = df.to_dict('records')[0]
            performance['win_rate'] = self._calculate_win_rate(None)
            performance['decision_diversity'] = self._calculate_decision_diversity(None)
            performance['risk_profile'] = self._analyze_risk_profile(None)
            return performance
        except Exception as e:
            logger.error(f"Error analyzing performance: {e}")
            return {}

    def _analyze_decision_patterns(self, timeframe: str) -> List[Dict]:
        """Analyze patterns in decision making"""
        if not self.db_engine:
            return []

        try:
            query = "SELECT `signal`, COUNT(*) as frequency, AVG(confidence) as avg_confidence FROM trading_signals GROUP BY `signal`"
            df = pd.read_sql(query, self.db_engine)
            patterns = []
            for _, row in df.iterrows():
                patterns.append({
                    'type': 'Decision Pattern',
                    'description': f"Decisions with signal '{row['signal']}'",
                    'frequency': row['frequency'],
                    'confidence': row['avg_confidence']
                })
            return patterns
        except Exception as e:
            logger.error(f"Error analyzing decision patterns: {e}")
            return []

    def _get_timeframe_context(self) -> str:
        # Dummy implementation
        return "Daily"

    def _get_risk_factors(self, decision_data: Dict) -> List[str]:
        # Dummy implementation
        return ["Market volatility", "Geopolitical events"]

    def _get_historical_patterns(self) -> List[str]:
        # Dummy implementation
        return ["Similar patterns observed in the past"]

    def _get_market_conditions(self, decision_data: Dict) -> str:
        # Dummy implementation
        return "Bullish"

    def _find_regime_patterns(self, decision_data: Dict) -> List[Dict]:
        # Dummy implementation
        return []

    def _find_similar_decisions(self, decision_data: Dict) -> List[Dict]:
        # Dummy implementation
        return []

    def _analyze_volatility_profile(self, recent_decisions: List[Dict]) -> str:
        # Dummy implementation
        return "Medium"

    def _analyze_sentiment(self, recent_decisions: List[Dict]) -> str:
        # Dummy implementation
        return "Positive"

    def _analyze_trend_strength(self, recent_decisions: List[Dict]) -> str:
        # Dummy implementation
        return "Strong"

    def _analyze_market_regime(self, recent_decisions: List[Dict]) -> str:
        # Dummy implementation
        return "Trending"

    def _summarize_cluster(self, cluster_decisions: List[Dict]) -> str:
        # Dummy implementation
        return "Decisions based on strong trend signals"

    def _analyze_risk_profile(self, recent_decisions: List[Dict]) -> str:
        # Dummy implementation
        return "Medium"

    def _calculate_decision_diversity(self, recent_decisions: List[Dict]) -> float:
        # Dummy implementation
        return 0.5

    def _calculate_win_rate(self, recent_decisions: List[Dict]) -> float:
        # Dummy implementation
        return 0.6

    def _calculate_overall_risk(self, risk_factors: List[Dict]) -> str:
        # Dummy implementation
        return "Medium"

    def _analyze_volatility(self, recent_decisions: List[Dict]) -> Dict:
        # Dummy implementation
        return {'level': 'medium', 'trend': 'stable', 'impact': 'moderate'}

    def _analyze_regime_risk(self, recent_decisions: List[Dict]) -> Dict:
        # Dummy implementation
        return {'current': 'trending', 'transition_risk': 'low', 'historical_impact': 'low'}

    def _get_recent_decisions(self, timeframe: str) -> List[Dict]:
        """Get decisions within a given timeframe."""
        if not self.decision_history:
            return []

        now = datetime.now()
        if timeframe == 'daily':
            delta = timedelta(days=1)
        elif timeframe == 'weekly':
            delta = timedelta(weeks=1)
        else:
            delta = timedelta(days=1) # default to daily

        start_time = now - delta
        return [d for d in self.decision_history if d['timestamp'] >= start_time]

    def _analyze_risk(self, timeframe: str) -> Dict:
        """Analyze risk factors"""
        recent_decisions = self._get_recent_decisions(timeframe)
        
        if not recent_decisions:
            return {
                'factors': [],
                'overall_risk': 'N/A'
            }
        
        risk_factors = []
        
        # Analyze volatility
        volatility = self._analyze_volatility(recent_decisions)
        risk_factors.append({
            'type': 'Volatility',
            'level': volatility.get('level', 'N/A'),
            'trend': volatility.get('trend', 'N/A'),
            'impact': volatility.get('impact', 'N/A')
        })
        
        # Analyze market regime risk
        regime_risk = self._analyze_regime_risk(recent_decisions)
        risk_factors.append({
            'type': 'Market Regime',
            'current': regime_risk.get('current', 'N/A'),
            'transition_risk': regime_risk.get('transition_risk', 'N/A'),
            'historical_impact': regime_risk.get('historical_impact', 'N/A')
        })
        
        return {
            'factors': risk_factors,
            'overall_risk': self._calculate_overall_risk(risk_factors)
        }

    def _analyze_market(self, timeframe: str) -> Dict:
        """Analyze market conditions"""
        return {
            'market_trend': 'Bullish',
            'key_support_level': '50,000',
            'key_resistance_level': '60,000',
            'market_sentiment': 'Positive'
        }
