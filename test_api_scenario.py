

import requests
import json
import time

# === CONFIG ===
BASE_URL = "http://localhost:5000"
LOGIN_USER = "testuser"
LOGIN_PASS = "testpass123"
LOGIN_EMAIL = "testuser@example.com"

# === SESSION HANDLING ===
session = requests.Session()

def test_register():
    url = f"{BASE_URL}/register"
    data = {"username": LOGIN_USER, "email": LOGIN_EMAIL, "password": LOGIN_PASS}
    try:
        resp = session.post(url, json=data)
        if resp.status_code == 201:
            print("[register] Registered new user. PASS")
        elif resp.status_code == 409:
            print("[register] User already exists. PASS")
        else:
            print(f"[register] Unexpected response: {resp.status_code}, {resp.text} FAIL")
    except Exception as e:
        print(f"[register] Exception: {e} FAIL")

def test_login():
    url = f"{BASE_URL}/login"
    data = {"username": LOGIN_USER, "password": LOGIN_PASS}
    resp = session.post(url, json=data)
    if resp.status_code == 200:
        print("[login] Login success. PASS")
        return True
    else:
        print(f"[login] Login failed: {resp.status_code}, {resp.text} FAIL")
        return False

def test_logout():
    url = f"{BASE_URL}/logout"
    resp = session.post(url)
    if resp.status_code == 200:
        print("[logout] Logout success. PASS")
    else:
        print(f"[logout] Logout failed: {resp.status_code}, {resp.text} FAIL")

def test_check_status(job_id):
    status_url = f"{BASE_URL}/check-status/{job_id}"
    resp = session.get(status_url)
    try:
        res = resp.json()
    except Exception as e:
        print(f"[check-status] Failed to parse JSON: {e}. Raw response: '{resp.text.strip()}' FAIL")
        return None
    print(f"[check-status] status={resp.status_code}, response={res}")
    if resp.status_code == 200:
        print("[check-status] PASS")
    else:
        print("[check-status] FAIL")
    return res

def poll_job(job_id):
    status_url = f"{BASE_URL}/check-status/{job_id}"
    for _ in range(20):  # max 20x polling (20 detik)
        resp = session.get(status_url)
        try:
            if resp.status_code != 200 or not resp.content or resp.text.strip() == '':
                print(f"[poll_job] Empty or non-200 response: status={resp.status_code}, body='{resp.text.strip()}'")
                time.sleep(1)
                continue
            res = resp.json()
        except Exception as e:
            print(f"[poll_job] Failed to parse JSON: {e}. Raw response: '{resp.text.strip()}'")
            time.sleep(1)
            continue
        if res.get("status") == "done":
            return res
        elif res.get("status") == "error":
            return res
        time.sleep(1)
    return {"status": "timeout", "message": "Polling timeout"}



url = f"{BASE_URL}/generate-code"


emisi_local_list = []
emisi_gpt_list = []
emisi_gpt_for_simulasi = []  # for simulating 'all use GPT'

# Track last environmental impact from API
last_energy_wh = 0
last_water_ml = 0
last_carbon_kg = 0


def format_kg(value, max_decimals=6):
    """Format a kgCO2eq value with thousand separators and up to max_decimals decimals.
    If value < 0.001, use scientific notation. Else, use decimals with trimming.
    """
    try:
        v = float(value)
    except Exception:
        return str(value)
    if abs(v) < 0.001 and v != 0:
        return f"{v:.2e} kgCO2eq"
    # Format with max_decimals, then trim trailing zeros
    fmt = f"{{:,.{max_decimals}f}}".format(v)
    if '.' in fmt:
        fmt = fmt.rstrip('0').rstrip('.')
    if '.' in fmt:
        decs = len(fmt.split('.')[-1])
        if decs < 2:
            fmt = f"{fmt:.2f}"
    else:
        fmt = fmt + '.00'
    return fmt + " kgCO2eq"



# --- Modular scenario test ---
def test_generate_code(prompt, expect_mode=None, last_prompt=None, verbose=True):
    data = {"prompt": prompt}
    if last_prompt:
        data["last_prompt"] = last_prompt
    response = session.post(url, json=data)
    try:
        result = response.json()
    except Exception as e:
        print(f"[generate-code] Failed to parse JSON: {e}. Raw response: '{response.text.strip()}' FAIL")
        return None
    mode = result.get('mode')
    if verbose:
        print(f"\nPrompt: {prompt}")
        if 'prompt_matched' in result:
            print(f"Pencocokan prompt: '{result['prompt_matched']}'")
        if mode:
            print(f"Mode jawaban: {mode} (similarity: {result.get('similarity')})")
        if result.get('code'):
            print(f"Final Code: \n{result.get('code')}")
        if result.get('message'):
            print(f"{result.get('message')}")
    emisi_local = 0
    emisi_gpt = 0
    emisi_gpt_simulasi = 0
    global last_energy_wh, last_water_ml, last_carbon_kg
    # --- GPT Mode (queued) ---
    if mode == "gpt-queued":
        job_id = result.get("job_id")
        if verbose:
            print("Just a moment, I'll process the answer first...")
        status_res = test_check_status(job_id)
        final_result = poll_job(job_id)
        if verbose:
            print(f"Answer is ready!")
            if final_result.get('code'):
                print(f"Final Code:\n{final_result.get('code')}")
            if final_result.get('message'):
                print(f"{final_result.get('message')}")
        if 'environmental_impact' in final_result:
            emisi_gpt = final_result['environmental_impact'].get('carbon_kg', 0)
            if verbose:
                print(f"🌱 Emisi LLM/GPT (token): {format_kg(emisi_gpt)}")
            emisi_gpt_list.append(emisi_gpt)
            emisi_gpt_for_simulasi.append(emisi_gpt)
            last_energy_wh = final_result['environmental_impact'].get('energy_wh', 0)
            last_water_ml = final_result['environmental_impact'].get('water_ml', 0)
            last_carbon_kg = final_result['environmental_impact'].get('carbon_kg', 0)
        if 'gamification' in final_result and verbose:
            g = final_result['gamification']
            print(f"🏅 Gamification: Total tokens: {g.get('total_tokens')}, Sisa: {g.get('remaining_tokens')}, Poin: {g.get('points')}")
        # Mode check
        if expect_mode and mode != expect_mode:
            print(f"[generate-code] FAIL: Expected mode '{expect_mode}', got '{mode}'")
        return final_result
    # --- Retrieval/Suggestion ---
    else:
        if 'environmental_impact' in result:
            emisi_local = result['environmental_impact'].get('carbon_kg', 0)
            if verbose:
                print(f"Emisi Lokal (CodeCarbon): {format_kg(emisi_local)}")
            emisi_local_list.append(emisi_local)
            last_energy_wh = result['environmental_impact'].get('energy_wh', 0)
            last_water_ml = result['environmental_impact'].get('water_ml', 0)
            last_carbon_kg = result['environmental_impact'].get('carbon_kg', 0)
        code = result.get('code')
        if code:
            token_count = len(str(code).split())
            CARBON_PER_QUERY_SHORT = 0.0004375
            emisi_gpt_simulasi = token_count * CARBON_PER_QUERY_SHORT
        else:
            emisi_gpt_simulasi = 0
        emisi_gpt_for_simulasi.append(emisi_gpt_simulasi)
        if verbose:
            print(f"Emisi GPT Simulasi (token dari retrieval): {format_kg(emisi_gpt_simulasi)}")
        if 'gamification' in result and verbose:
            g = result['gamification']
            print(f"Gamification: Total tokens: {g.get('total_tokens')}, Sisa: {g.get('remaining_tokens')}, Poin: {g.get('points')}")
        if expect_mode and mode != expect_mode:
            print(f"[generate-code] FAIL: Expected mode '{expect_mode}', got '{mode}'")
        return result

# --- Scenario test helpers ---
def run_scenario_tests():
    print("\n=== Automated Scenario Tests ===")
    # These prompts should be adjusted to match your DB for retrieval/suggestion
    prompts = [
        ("print('Hello World')", "retrieval"),
        ("sort ascending 23 list in python", "suggestion"),
        ("GPT Mode", "gpt-queued"),
        ("explain quicksort in Java", "gpt-queued"),
    ]
    for prompt, expect_mode in prompts:
        print(f"\n[SCENARIO] Prompt: {prompt} | Expected Mode: {expect_mode}")
        test_generate_code(prompt, expect_mode=expect_mode, verbose=True)
    print("\n=== Scenario Tests Complete ===\n")



total_emisi_local = sum(emisi_local_list)
total_emisi_gpt = sum(emisi_gpt_list)
total_emisi_gpt_simulasi = sum(emisi_gpt_for_simulasi)
total_emisi = total_emisi_local + total_emisi_gpt

# Ambil total energy, water, carbon dari response terakhir jika ada
total_energy_wh = last_energy_wh
total_water_ml = last_water_ml
total_carbon_kg = last_carbon_kg

lamp_minutes = (total_energy_wh / 5) * 60
game_seconds = (total_energy_wh / 200) * 3600
tiktok_seconds = (total_energy_wh / 1.85) * 60
motor_meters = (total_carbon_kg / 0.09) * 1000

if __name__ == "__main__":
    import sys
    def print_menu():
        print("\n=== PILIH MODE TESTING ===")
        print("1. Automated Scenario Testing (uji semua skenario retrieval, suggestion, GPT)")
        print("2. Manual Prompt Testing (uji prompt satu per satu)")
        print("0. Keluar")

    test_register()
    if not test_login():
        print("[main] Login failed, aborting tests.")
        sys.exit(1)

    while True:
        print_menu()
        try:
            choice = input("Pilih mode [1/2/0]: ").strip()
        except (EOFError, KeyboardInterrupt):
            print("\n[main] Input interrupted. Exiting...")
            break
        if choice == '1':
            run_scenario_tests()
            break
        elif choice == '2':
            print("\n=== Mode Chat Interaktif ===")
            print("Ketik 'exit' untuk keluar dan melihat rangkuman emisi.")
            while True:
                try:
                    prompt = input("\nPrompt : ")
                except (EOFError, KeyboardInterrupt):
                    print("\n[main] Input interrupted. Exiting...")
                    break
                if prompt.strip().lower() == 'exit':
                    break
                test_generate_code(prompt, expect_mode=None, verbose=True)
            break
        elif choice == '0':
            print("Keluar.")
            test_logout()
            sys.exit(0)
        else:
            print("Pilihan tidak valid. Silakan pilih 1, 2, atau 0.")

    # --- Summary ---
    total_emisi_local = sum(emisi_local_list)
    total_emisi_gpt = sum(emisi_gpt_list)
    total_emisi_gpt_simulasi = sum(emisi_gpt_for_simulasi)
    total_emisi = total_emisi_local + total_emisi_gpt
    total_energy_wh = last_energy_wh
    total_water_ml = last_water_ml
    total_carbon_kg = last_carbon_kg

    print("\n==== Rangkuman Emisi ====")
    print(f"Local Carbon Emmision (retrieval/suggestion, CodeCarbon): {format_kg(total_emisi_local)}")
    print(f"LLM GPT Carbon Emmision (token, real): {format_kg(total_emisi_gpt)}")
    print(f"Total Carbon Emmision (retrieval/suggestion, CodeCarbon + GPT): {format_kg(total_emisi)}")
    print(f"Carbon Emission if all use GPT (simulation): {format_kg(total_emisi_gpt_simulasi)}")
    print(f"Water usage with retrieval: {format_kg(total_water_ml)}")
    print(f"Electricity savings with retrieval: {format_kg(total_energy_wh)}")
    if total_emisi_gpt_simulasi > 0:
        penghematan = total_emisi_gpt_simulasi - total_emisi

    # --- Environmental Equivalence Simulation ---
    print("\n==== Environmental Impact Equivalence Simulation ====")
    print("Equivalent to charging a mobile phone by:", round((total_energy_wh / 15) * 100, 2), "% of a full charge")
    print("Equivalent to cooking instant noodles:", round((total_energy_wh / 900) * 100, 2), "% of one serving")
    print("Equivalent to brewing coffee:", round((total_water_ml / 150) * 100, 2), "% of one cup")
    lamp_minutes = (total_energy_wh / 5) * 60
    print("Equivalent to lighting a 5-watt lamp for:", round(lamp_minutes, 2), "minutes")
    game_seconds = (total_energy_wh / 200) * 3600
    print("Equivalent to playing PC games for:", round(game_seconds, 2), "seconds")
    tiktok_seconds = (total_energy_wh / 1.85) * 60
    print("Equivalent to watching short videos on a smartphone for:", round(tiktok_seconds, 2), "seconds")
    motor_meters = (total_carbon_kg / 0.09) * 1000
    print("Equivalent to traveling by motorcycle for:", round(motor_meters, 2), "meters")

    test_logout()



1-Jun-26
1-Jul-26
1-Sep-26
#VALUE!
#VALUE!
#VALUE!
#VALUE!
#VALUE!
#VALUE!
#VALUE!
#VALUE!
#VALUE!
#VALUE!
#VALUE!
#VALUE!
