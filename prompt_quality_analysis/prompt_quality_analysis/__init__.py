"""Compatibility package for running from inside prompt_quality_analysis/"""

from .evaluator import PromptQualityEvaluator, EvaluationResult

__all__ = ["PromptQualityEvaluator", "EvaluationResult"]
