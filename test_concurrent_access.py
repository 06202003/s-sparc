#!/usr/bin/env python3
"""
Concurrent Access Test Script for S-SPARC AI
Tests multiple users accessing the system simultaneously
"""

import requests
import threading
import time
from datetime import datetime

# Configuration
BASE_URL = "http://localhost:5000"
TEST_USERS = 5

# Color codes for terminal output
class Colors:
    GREEN = '\033[92m'
    RED = '\033[91m'
    YELLOW = '\033[93m'
    BLUE = '\033[94m'
    END = '\033[0m'

def log(message, color=Colors.BLUE):
    timestamp = datetime.now().strftime("%H:%M:%S.%f")[:-3]
    print(f"{color}[{timestamp}] {message}{Colors.END}")

def test_login(user_id, results):
    """Test login for a single user"""
    start = time.time()
    try:
        response = requests.post(
            f"{BASE_URL}/login",
            json={
                "username": f"testuser{user_id}",
                "password": "test123"
            },
            timeout=5
        )
        elapsed = time.time() - start
        
        if response.status_code == 200:
            log(f"✓ User {user_id} login SUCCESS ({elapsed:.2f}s)", Colors.GREEN)
            results[user_id] = {'status': 'success', 'time': elapsed, 'code': response.status_code}
        else:
            log(f"✗ User {user_id} login FAILED: {response.status_code} ({elapsed:.2f}s)", Colors.RED)
            results[user_id] = {'status': 'failed', 'time': elapsed, 'code': response.status_code}
    except Exception as e:
        elapsed = time.time() - start
        log(f"✗ User {user_id} login ERROR: {str(e)} ({elapsed:.2f}s)", Colors.RED)
        results[user_id] = {'status': 'error', 'time': elapsed, 'error': str(e)}

def test_concurrent_logins():
    """Test multiple users logging in simultaneously"""
    log(f"=== Test 1: Concurrent Login ({TEST_USERS} users) ===", Colors.YELLOW)
    
    results = {}
    threads = []
    
    # Start all threads at once
    start_time = time.time()
    for i in range(TEST_USERS):
        t = threading.Thread(target=test_login, args=(i, results))
        threads.append(t)
        t.start()
    
    # Wait for all threads to complete
    for t in threads:
        t.join()
    
    total_time = time.time() - start_time
    
    # Analyze results
    success = sum(1 for r in results.values() if r['status'] == 'success')
    failed = sum(1 for r in results.values() if r['status'] != 'success')
    avg_time = sum(r['time'] for r in results.values()) / len(results)
    max_time = max(r['time'] for r in results.values())
    
    log(f"\n=== Results ===", Colors.YELLOW)
    log(f"Total time: {total_time:.2f}s")
    log(f"Success: {success}/{TEST_USERS}")
    log(f"Failed: {failed}/{TEST_USERS}")
    log(f"Average response time: {avg_time:.2f}s")
    log(f"Max response time: {max_time:.2f}s")
    
    if success == TEST_USERS and max_time < 3.0:
        log("✓ PASS: All concurrent logins succeeded quickly!", Colors.GREEN)
        return True
    else:
        log("✗ FAIL: Some issues detected", Colors.RED)
        return False

def test_generate_code_queue(user_id, results):
    """Test code generation (should return job_id immediately)"""
    start = time.time()
    try:
        # First login
        login_resp = requests.post(
            f"{BASE_URL}/login",
            json={"username": f"testuser{user_id}", "password": "test123"},
            timeout=5
        )
        
        if login_resp.status_code != 200:
            log(f"✗ User {user_id} login failed", Colors.RED)
            results[user_id] = {'status': 'login_failed'}
            return
        
        # Get session cookie
        cookies = login_resp.cookies
        
        # Generate code (should queue and return immediately)
        gen_resp = requests.post(
            f"{BASE_URL}/generate-code",
            json={
                "prompt": f"Write a Python function to sort a list of integers using bubble sort algorithm. This is test request {user_id}." + " " * 90,  # Make it 100+ chars
                "language": "Python"
            },
            cookies=cookies,
            timeout=5
        )
        
        elapsed = time.time() - start
        
        if gen_resp.status_code == 202:
            data = gen_resp.json()
            if 'job_id' in data:
                log(f"✓ User {user_id} code generation QUEUED ({elapsed:.2f}s) - Job ID: {data['job_id'][:8]}...", Colors.GREEN)
                results[user_id] = {'status': 'queued', 'time': elapsed, 'job_id': data['job_id']}
            else:
                log(f"✗ User {user_id} no job_id in response ({elapsed:.2f}s)", Colors.RED)
                results[user_id] = {'status': 'no_job_id', 'time': elapsed}
        else:
            log(f"✗ User {user_id} code generation FAILED: {gen_resp.status_code} ({elapsed:.2f}s)", Colors.RED)
            results[user_id] = {'status': 'failed', 'time': elapsed, 'code': gen_resp.status_code}
    
    except Exception as e:
        elapsed = time.time() - start
        log(f"✗ User {user_id} code generation ERROR: {str(e)} ({elapsed:.2f}s)", Colors.RED)
        results[user_id] = {'status': 'error', 'time': elapsed, 'error': str(e)}

def test_concurrent_code_generation():
    """Test multiple users generating code simultaneously"""
    log(f"\n=== Test 2: Concurrent Code Generation ({TEST_USERS} users) ===", Colors.YELLOW)
    
    results = {}
    threads = []
    
    # Start all threads at once
    start_time = time.time()
    for i in range(TEST_USERS):
        t = threading.Thread(target=test_generate_code_queue, args=(i, results))
        threads.append(t)
        t.start()
    
    # Wait for all threads to complete
    for t in threads:
        t.join()
    
    total_time = time.time() - start_time
    
    # Analyze results
    queued = sum(1 for r in results.values() if r['status'] == 'queued')
    failed = sum(1 for r in results.values() if r['status'] != 'queued')
    avg_time = sum(r['time'] for r in results.values() if 'time' in r) / len([r for r in results.values() if 'time' in r])
    max_time = max(r['time'] for r in results.values() if 'time' in r)
    
    log(f"\n=== Results ===", Colors.YELLOW)
    log(f"Total time: {total_time:.2f}s")
    log(f"Queued: {queued}/{TEST_USERS}")
    log(f"Failed: {failed}/{TEST_USERS}")
    log(f"Average response time: {avg_time:.2f}s")
    log(f"Max response time: {max_time:.2f}s")
    
    if queued == TEST_USERS and max_time < 5.0:
        log("✓ PASS: All requests queued successfully!", Colors.GREEN)
        return True
    else:
        log("✗ FAIL: Some requests failed to queue", Colors.RED)
        return False

def main():
    print(f"\n{Colors.YELLOW}{'='*60}")
    print("S-SPARC AI Concurrent Access Test")
    print(f"{'='*60}{Colors.END}\n")
    
    log(f"Testing server at: {BASE_URL}", Colors.BLUE)
    log(f"Number of concurrent users: {TEST_USERS}", Colors.BLUE)
    
    # Test server availability
    try:
        resp = requests.get(BASE_URL, timeout=3)
        log("✓ Server is reachable", Colors.GREEN)
    except Exception as e:
        log(f"✗ Server is NOT reachable: {e}", Colors.RED)
        log("Make sure the server is running: python app.py --host 0.0.0.0 --port 5000", Colors.YELLOW)
        return
    
    # Run tests
    test1_pass = test_concurrent_logins()
    time.sleep(1)
    test2_pass = test_concurrent_code_generation()
    
    # Final summary
    print(f"\n{Colors.YELLOW}{'='*60}")
    print("FINAL SUMMARY")
    print(f"{'='*60}{Colors.END}\n")
    
    if test1_pass and test2_pass:
        log("✓✓✓ ALL TESTS PASSED ✓✓✓", Colors.GREEN)
        log("System is ready for concurrent user access!", Colors.GREEN)
    else:
        log("✗✗✗ SOME TESTS FAILED ✗✗✗", Colors.RED)
        log("Please check the server logs and configuration", Colors.YELLOW)

if __name__ == "__main__":
    main()
