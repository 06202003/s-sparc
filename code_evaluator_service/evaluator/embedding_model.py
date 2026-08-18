from __future__ import annotations

import logging
import threading

import numpy as np
from sentence_transformers import SentenceTransformer

from .config import Settings


class EmbeddingModel:
    def __init__(self, settings: Settings) -> None:
        self.settings = settings
        self.logger = logging.getLogger(__name__)
        self._model: SentenceTransformer | None = None
        self._lock = threading.Lock()

    def _ensure_model(self) -> SentenceTransformer:
        if self._model is not None:
            return self._model

        with self._lock:
            if self._model is None:
                self.logger.info("Loading embedding model: %s", self.settings.embedding_model_name)
                self._model = SentenceTransformer(self.settings.embedding_model_name)
        return self._model

    @staticmethod
    def _prepare_query(text: str) -> str:
        return f"query: {(text or '').strip()}"

    @staticmethod
    def _prepare_passage(text: str) -> str:
        return f"passage: {(text or '').strip()}"

    def similarity(self, prompt: str, code: str) -> float:
        model = self._ensure_model()
        embeddings = model.encode(
            [self._prepare_query(prompt), self._prepare_passage(code)],
            convert_to_numpy=True,
            normalize_embeddings=True,
        )
        similarity = float(np.clip(np.dot(embeddings[0], embeddings[1]), 0.0, 1.0))
        return similarity
