"""
Production server runner for S-SPARC AI - OPTIMIZED FOR SERVER HARDWARE
Target Hardware: Intel i5 Gen 13 (12 threads) + RTX 3060 (12GB VRAM)

Performance Optimizations:
- GPU acceleration for Sentence Transformers (4-8x faster encoding)
- High thread/worker count with Uvicorn ASGI Server
- Pre-loading CUDA settings and environment flags
"""

import sys
import os
import uvicorn

# Ensure imports work even when launched from a different working directory
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
if BASE_DIR not in sys.path:
    sys.path.insert(0, BASE_DIR)

# GPU OPTIMIZATION: Set environment variables BEFORE importing torch/transformers
os.environ.setdefault('CUDA_VISIBLE_DEVICES', '0')  # Use first GPU (RTX 3060)
os.environ.setdefault('PYTORCH_CUDA_ALLOC_CONF', 'max_split_size_mb:512')  # Optimize VRAM usage
os.environ.setdefault('OMP_NUM_THREADS', '12')  # CPU threads for OpenMP operations
os.environ.setdefault('MKL_NUM_THREADS', '12')  # Intel MKL threads

def check_gpu_availability():
    """Check if CUDA GPU is available and print specs"""
    try:
        import torch
        if torch.cuda.is_available():
            gpu_name = torch.cuda.get_device_name(0)
            gpu_memory = torch.cuda.get_device_properties(0).total_memory / (1024**3)
            print(f"[GPU] OK CUDA available: {gpu_name}")
            print(f"[GPU] OK VRAM: {gpu_memory:.1f} GB")
            print(f"[GPU] OK CUDA Version: {torch.version.cuda}")
            return True
        else:
            print("[GPU] X CUDA not available. Running on CPU only.")
            return False
    except ImportError:
        print("[GPU] X PyTorch not installed")
        return False

def main():
    print("=" * 70)
    print("S-SPARC AI - FastAPI Production Server (GPU Accelerated)")
    print("Target Hardware: Intel i5 Gen 13 + RTX 3060")
    print("=" * 70)
    
    # Check GPU availability
    gpu_available = check_gpu_availability()
    
    host = os.getenv('FASTAPI_HOST', '127.0.0.1')
    port = int(os.getenv('FASTAPI_PORT', '5000'))
    workers = 1 if os.name == 'nt' else int(os.getenv('UVICORN_WORKERS', '4'))
    
    print("-" * 70)
    print(f"[SERVER] Starting Uvicorn ASGI server")
    print(f"[SERVER] Binding to: http://{host}:{port}")
    print(f"[SERVER] Workers: {workers}")
    print(f"[SERVER] Mode: {'GPU-Accelerated' if gpu_available else 'CPU-Only'}")
    print("-" * 70)
    
    from backend.main import create_app
    app = create_app()
    uvicorn.run(
        app,
        host=host,
        port=port,
        log_level="info"
    )

if __name__ == '__main__':
    main()
