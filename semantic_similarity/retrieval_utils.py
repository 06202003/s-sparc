import numpy as np
from sentence_transformers import SentenceTransformer
from transformers import pipeline
import torch
import faiss
from langdetect import detect

# Definisi class dan fungsi utama untuk semantic retrieval
class SemanticRetrievalModel:
    """
    Semantic Retrieval Model with flexible encoder and weights.
    """
    def __init__(self, df, index, embeddings, encoder_func, best_k=5, weights=None):
        self.df = df
        self.index = index
        self.embeddings = embeddings
        self.encoder_func = encoder_func
        self.best_k = best_k
        self.weights = weights

    def search(self, query: str, top_k: int = None, return_distance=False, weights=None):
        if top_k is None:
            top_k = self.best_k
        if weights is None:
            weights = self.weights
        emb = self.encoder_func(query, weights=weights)
        emb = emb / np.linalg.norm(emb, axis=1, keepdims=True)
        D, I = self.index.search(emb, top_k)
        # Convert L2 distance to cosine similarity: sim = 1 - (d**2)/2 (for normalized vectors)
        similarities = 1 - (D[0] / 2)
        results = self.df.iloc[I[0]].copy()
        results['score'] = similarities
        if return_distance:
            results['distance'] = D[0]
        return results[['prompt', 'score', 'code'] + (['distance'] if return_distance else [])]


def get_ensemble_embedding(text, weights):
    """
    Generate ensemble embedding with per-model normalization, weighting, and final normalization.
    Args:
        text (str): Input text.
        weights (tuple/list): Weights for each model (length 3).
    Returns:
        np.ndarray: Normalized ensemble embedding (shape: [1, total_dim]).
    """
    global model1, model2, model3, translator
    try:
        lang = detect(text)
    except Exception:
        lang = 'en'
    if lang == 'id':
        text = translator(text)[0]['translation_text']
    emb1 = model1.encode([text], convert_to_numpy=True)
    emb2 = model2.encode([text], convert_to_numpy=True)
    emb3 = model3.encode([text], convert_to_numpy=True)
    emb1 = emb1 / np.linalg.norm(emb1, axis=1, keepdims=True)
    emb2 = emb2 / np.linalg.norm(emb2, axis=1, keepdims=True)
    emb3 = emb3 / np.linalg.norm(emb3, axis=1, keepdims=True)
    emb1 = emb1 * weights[0]
    emb2 = emb2 * weights[1]
    emb3 = emb3 * weights[2]
    emb = np.concatenate([emb1, emb2, emb3], axis=1)
    emb = emb / np.linalg.norm(emb, axis=1, keepdims=True)
    return emb

# Model dan pipeline harus di-load di script utama sebelum pakai get_ensemble_embedding
