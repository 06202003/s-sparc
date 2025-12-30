import joblib
from retrieval_utils import SemanticRetrievalModel, get_ensemble_embedding

# Load model PKL (pastikan path benar)
retrieval_model = joblib.load("semantic_similarity/semantic_retrieval_model.pkl")
retrieval_model.encoder_func = get_ensemble_embedding  # Pastikan fungsi asli sudah diimport

user_prompt = input("Masukkan prompt: ")
results = retrieval_model.search(user_prompt, top_k=3)

print("\nHasil retrieval (top-3):")
for i, row in results.iterrows():
    print(f"\n[Rank {i+1}] Similarity: {row['score']:.3f}")
    print(f"Prompt: {row['prompt']}")
    print(f"Code:\n{row['code']}")
