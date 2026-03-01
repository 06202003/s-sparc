"""
Quick GPU Test Script for S-SPARC AI
Tests GPU availability and performance before production deployment
"""

import sys

def test_cuda_availability():
    """Test if CUDA is available and working"""
    print("=" * 70)
    print("1. Testing CUDA Availability")
    print("=" * 70)
    
    try:
        import torch
        print(f"✓ PyTorch version: {torch.__version__}")
        
        if torch.cuda.is_available():
            print(f"✓ CUDA available: True")
            print(f"✓ CUDA version: {torch.version.cuda}")
            print(f"✓ GPU count: {torch.cuda.device_count()}")
            print(f"✓ GPU name: {torch.cuda.get_device_name(0)}")
            
            # Get GPU memory
            props = torch.cuda.get_device_properties(0)
            total_memory = props.total_memory / (1024**3)
            print(f"✓ Total VRAM: {total_memory:.1f} GB")
            
            # Check current memory usage
            allocated = torch.cuda.memory_allocated(0) / (1024**3)
            reserved = torch.cuda.memory_reserved(0) / (1024**3)
            print(f"✓ VRAM allocated: {allocated:.2f} GB")
            print(f"✓ VRAM reserved: {reserved:.2f} GB")
            print(f"✓ VRAM free: {total_memory - reserved:.2f} GB")
            
            return True
        else:
            print("✗ CUDA not available")
            print("\nTo enable GPU acceleration:")
            print("1. Install NVIDIA CUDA Toolkit 12.1")
            print("2. Reinstall PyTorch with CUDA:")
            print("   pip install torch torchvision torchaudio --index-url https://download.pytorch.org/whl/cu121")
            return False
    except ImportError:
        print("✗ PyTorch not installed")
        print("\nInstall PyTorch:")
        print("pip install torch torchvision torchaudio --index-url https://download.pytorch.org/whl/cu121")
        return False
    except Exception as e:
        print(f"✗ Error: {e}")
        return False

def test_sentence_transformers():
    """Test Sentence Transformers with GPU"""
    print("\n" + "=" * 70)
    print("2. Testing Sentence Transformers GPU Support")
    print("=" * 70)
    
    try:
        from sentence_transformers import SentenceTransformer
        import torch
        import time
        
        device = 'cuda' if torch.cuda.is_available() else 'cpu'
        print(f"Loading test model on {device.upper()}...")
        
        # Load a small model for testing
        model = SentenceTransformer('paraphrase-MiniLM-L6-v2', device=device)
        print(f"✓ Model loaded on {device.upper()}")
        
        # Test encoding speed
        test_text = "This is a test sentence for GPU acceleration."
        
        # Warmup
        _ = model.encode([test_text])
        
        # Benchmark
        print("\nBenchmarking encoding speed...")
        start = time.time()
        for _ in range(10):
            _ = model.encode([test_text])
        duration = time.time() - start
        avg_time = duration / 10
        
        print(f"✓ Average encoding time: {avg_time*1000:.1f}ms per sentence")
        
        if device == 'cuda':
            print(f"✓ GPU acceleration working!")
            if avg_time < 0.05:  # < 50ms is good for GPU
                print("✓ Performance: EXCELLENT (GPU optimized)")
            elif avg_time < 0.1:  # < 100ms is okay
                print("✓ Performance: GOOD")
            else:
                print("⚠ Performance: Slower than expected (check GPU load)")
        else:
            print(f"✓ Running on CPU")
            if avg_time < 0.1:
                print("✓ Performance: GOOD for CPU")
            else:
                print("✓ Performance: Normal for CPU")
        
        return True
    except ImportError as e:
        print(f"✗ sentence-transformers not installed: {e}")
        print("\nInstall sentence-transformers:")
        print("pip install sentence-transformers")
        return False
    except Exception as e:
        print(f"✗ Error: {e}")
        return False

def test_project_models():
    """Test loading actual project models"""
    print("\n" + "=" * 70)
    print("3. Testing Project Models (paraphrase-multilingual-mpnet-base-v2)")
    print("=" * 70)
    
    try:
        import os
        import time
        import torch
        from sentence_transformers import SentenceTransformer
        
        # Check if model exists
        model_path = os.path.join('pretrained_model', 'paraphrase-multilingual-mpnet-base-v2')
        if not os.path.exists(model_path):
            print(f"✗ Model not found at: {model_path}")
            print("Download models first!")
            return False
        
        print(f"✓ Model directory found: {model_path}")
        
        device = 'cuda' if torch.cuda.is_available() else 'cpu'
        print(f"Loading model on {device.upper()}...")
        
        start = time.time()
        model = SentenceTransformer(model_path, device=device)
        load_time = time.time() - start
        
        print(f"✓ Model loaded in {load_time:.1f} seconds")
        
        if device == 'cuda':
            # Check VRAM usage
            allocated = torch.cuda.memory_allocated(0) / (1024**3)
            print(f"✓ VRAM used by model: {allocated:.2f} GB")
        
        # Test encoding
        test_prompts = [
            "Buat fungsi untuk menghitung faktorial",
            "Create a function to calculate factorial",
            "Write a Python program for bubble sort"
        ]
        
        print("\nTesting encoding with sample prompts...")
        start = time.time()
        embeddings = model.encode(test_prompts)
        duration = time.time() - start
        
        print(f"✓ Encoded {len(test_prompts)} prompts in {duration*1000:.1f}ms")
        print(f"✓ Average: {duration/len(test_prompts)*1000:.1f}ms per prompt")
        print(f"✓ Embedding shape: {embeddings.shape}")
        
        if device == 'cuda':
            if duration < 0.5:  # < 500ms for 3 prompts is excellent
                print("✓ Performance: EXCELLENT - GPU is working optimally!")
            elif duration < 1.0:
                print("✓ Performance: GOOD - GPU acceleration active")
            else:
                print("⚠ Performance: Acceptable but slower than expected")
        else:
            print("✓ Running on CPU (expect 2-5x slower than GPU)")
        
        return True
    except Exception as e:
        print(f"✗ Error: {e}")
        import traceback
        traceback.print_exc()
        return False

def test_multiple_models():
    """Test loading all 3 models like production"""
    print("\n" + "=" * 70)
    print("4. Testing All 3 Production Models (GPU Memory Test)")
    print("=" * 70)
    
    try:
        import os
        import time
        import torch
        from sentence_transformers import SentenceTransformer
        
        device = 'cuda' if torch.cuda.is_available() else 'cpu'
        
        if device == 'cpu':
            print("⚠ Running on CPU - skipping multi-model GPU memory test")
            return True
        
        model_paths = [
            'pretrained_model/paraphrase-multilingual-mpnet-base-v2',
            'pretrained_model/LaBSE',
            'pretrained_model/multilingual-e5-base'
        ]
        
        models = []
        total_load_time = 0
        
        print("Loading all 3 models on GPU...")
        for i, path in enumerate(model_paths, 1):
            if not os.path.exists(path):
                print(f"✗ Model {i} not found: {path}")
                continue
            
            print(f"\nLoading model {i}/3: {os.path.basename(path)}")
            start = time.time()
            model = SentenceTransformer(path, device=device)
            load_time = time.time() - start
            total_load_time += load_time
            
            models.append(model)
            
            allocated = torch.cuda.memory_allocated(0) / (1024**3)
            print(f"  ✓ Loaded in {load_time:.1f}s")
            print(f"  ✓ Cumulative VRAM: {allocated:.2f} GB")
        
        if len(models) == 3:
            print(f"\n✓ All 3 models loaded successfully!")
            print(f"✓ Total load time: {total_load_time:.1f}s")
            
            allocated = torch.cuda.memory_allocated(0) / (1024**3)
            reserved = torch.cuda.memory_reserved(0) / (1024**3)
            total_vram = torch.cuda.get_device_properties(0).total_memory / (1024**3)
            
            print(f"✓ Total VRAM allocated: {allocated:.2f} GB")
            print(f"✓ Total VRAM reserved: {reserved:.2f} GB")
            print(f"✓ VRAM remaining: {total_vram - reserved:.2f} GB")
            
            if reserved < 10:  # RTX 3060 has 12GB
                print("✓ Memory usage: OPTIMAL (leaves room for inference)")
            elif reserved < 11:
                print("⚠ Memory usage: HIGH (may limit batch size)")
            else:
                print("⚠ Memory usage: CRITICAL (consider reducing model precision)")
            
            # Test concurrent encoding
            print("\nTesting concurrent encoding with all 3 models...")
            test_text = "Buat program Python untuk sorting data"
            start = time.time()
            emb1 = models[0].encode([test_text])
            emb2 = models[1].encode([test_text])
            emb3 = models[2].encode([test_text])
            duration = time.time() - start
            
            print(f"✓ Encoded with 3 models in {duration*1000:.1f}ms")
            print(f"✓ Average per model: {duration/3*1000:.1f}ms")
            
            if duration < 1.0:
                print("✓ Performance: EXCELLENT - Ready for production!")
            elif duration < 2.0:
                print("✓ Performance: GOOD - Production ready")
            else:
                print("⚠ Performance: Acceptable but may need optimization")
        else:
            print(f"⚠ Only {len(models)}/3 models loaded")
        
        return len(models) == 3
    except Exception as e:
        print(f"✗ Error: {e}")
        import traceback
        traceback.print_exc()
        return False

def main():
    print("\n")
    print("╔══════════════════════════════════════════════════════════════════════╗")
    print("║         S-SPARC AI - GPU Performance Test Suite                     ║")
    print("║         Target: Intel i5 Gen 13 + RTX 3060                          ║")
    print("╚══════════════════════════════════════════════════════════════════════╝")
    print("\n")
    
    results = {
        'cuda': False,
        'sentence_transformers': False,
        'project_models': False,
        'multiple_models': False
    }
    
    # Run tests
    results['cuda'] = test_cuda_availability()
    results['sentence_transformers'] = test_sentence_transformers()
    results['project_models'] = test_project_models()
    results['multiple_models'] = test_multiple_models()
    
    # Summary
    print("\n" + "=" * 70)
    print("Test Summary")
    print("=" * 70)
    
    passed = sum(results.values())
    total = len(results)
    
    for test_name, result in results.items():
        status = "✓ PASS" if result else "✗ FAIL"
        print(f"{status} - {test_name.replace('_', ' ').title()}")
    
    print(f"\nResult: {passed}/{total} tests passed")
    
    if passed == total:
        print("\n🎉 All tests passed! GPU acceleration is ready for production!")
        print("\nNext steps:")
        print("1. Start production server: python run_production_server.py")
        print("2. Test with real requests")
        print("3. Monitor GPU usage: nvidia-smi -l 1")
    elif results['cuda']:
        print("\n⚠ GPU detected but some tests failed.")
        print("Check error messages above and fix issues before production.")
    else:
        print("\n❌ GPU not available. Will run on CPU (slower).")
        print("For GPU acceleration:")
        print("1. Install CUDA Toolkit 12.1")
        print("2. Reinstall PyTorch: pip install torch --index-url https://download.pytorch.org/whl/cu121")
        print("3. Run this test again")
    
    print("\n")
    return 0 if passed == total else 1

if __name__ == '__main__':
    sys.exit(main())
