from flask import Flask, request, jsonify
from flask_cors import CORS
from flask_limiter import Limiter
from flask_limiter.util import get_remote_address
from dotenv import load_dotenv
import os
import openai
import logging
try:
    from codecarbon import EmissionsTracker 
except ImportError:
    EmissionsTracker = None 

# Load environment variables from .env file
load_dotenv()

# Constants
OPENAI_MODEL = "gpt-4"  # ganti dari gpt-3.5-turbo ke gpt-4
DEFAULT_LIMITS = ["100 per day", "10 per minute"]

# Constants for environmental impact per token based on prompt size
ENERGY_PER_TOKEN_WH_SHORT = 0.0010525
ENERGY_PER_TOKEN_WH_MEDIUM = 0.0006070
ENERGY_PER_TOKEN_WH_LONG = 0.0001555

# Total Air and Carbon per query based on prompt size
WATER_PER_QUERY_SHORT = 0.00625  # Total air per short-form query
WATER_PER_QUERY_MEDIUM = 0.001875  # Total air per medium-form query
WATER_PER_QUERY_LONG = 5.217391304347826e-4  # Total air per long-form query

CARBON_PER_QUERY_SHORT = 0.0004375  # Total carbon per short-form query (in kgCO2e)
CARBON_PER_QUERY_MEDIUM = 0.000002  # Total carbon per medium-form query (in kgCO2e)
CARBON_PER_QUERY_LONG = 5.217391304347826e-7  # Total carbon per long-form query (in kgCO2e)

# Initialize Flask app
app = Flask(__name__)
CORS(app)

# Initialize Flask-Limiter
limiter = Limiter(
    get_remote_address,
    app=app,
    default_limits=DEFAULT_LIMITS,
)

# Configure OpenAI API key
OPENAI_API_KEY = os.getenv("OPENAI_API_KEY")
openai.api_key = OPENAI_API_KEY

# Create OpenAI client for openai>=1.0.0
try:
    client = openai.OpenAI(api_key=OPENAI_API_KEY) if OPENAI_API_KEY else openai.OpenAI()
except Exception:
    client = openai.OpenAI()  # fallback

# Configure logging
logging.basicConfig(level=logging.INFO)

@app.route('/generate-code', methods=['POST'])
@limiter.limit("6 per minute")
def generate_code():
    """
    Endpoint to generate code using OpenAI API.
    """
    try:
        data = request.get_json(silent=True) or {}
        prompt = data.get("prompt")
        if not prompt or not isinstance(prompt, str):
            return jsonify({"error": "Missing or invalid 'prompt' in request body"}), 400

        if not openai.api_key:
            logging.error("OpenAI API key not configured")
            return jsonify({"error": "OpenAI API key not configured"}), 500

        system_content = (
            "You are an expert programming assistant helping undergraduate computer science students. "
            "Your task is to generate only the source code that solves the user's request. Follow these strict instructions:\n\n"
            "1. Output only the code — do not include explanations, descriptions, comments, or markdown formatting (such as triple backticks).\n\n"
            "2. The code must follow the requested programming paradigm:\n"
            "   - For procedural programming, avoid using classes. Structure the code with functions and ensure a clear main execution path.\n"
            "   - For object-oriented programming (OOP), use classes, encapsulate related data and behavior, and follow proper OOP design principles.\n\n"
            "3. If the language is Python and procedural style is requested, include a 'def main()' function and end with 'if __name__ == \"__main__\": main()'.\n\n"
            "4. Limit the code strictly to the specified topic(s). Do not introduce unrelated concepts, even if they might improve or extend the code.\n\n"
            "5. Use clean, readable, and idiomatic code that reflects good practice for the specified language.\n\n"
            "6. Never explain what the code does. Do not write inline or block comments.\n\n"
            "7. Do not include any logging, print statements, or UI unless explicitly required by the question.\n\n"
            "8. Output only the final code solution without imports unless required by the logic of the code.\n\n"
            "Always assume the user is a student looking to learn the core logic for a specific topic in a clear and isolated way."
        )

        messages = [
            {"role": "system", "content": system_content},
            {"role": "user", "content": prompt},
        ]

        response = client.chat.completions.create(
            model=OPENAI_MODEL,
            temperature=0.3,
            messages=messages,
        )

        # Extract generated code with attribute/dict fallback
        try:
            generated_code = response.choices[0].message.content.strip()
        except Exception:
            try:
                generated_code = response["choices"][0]["message"]["content"].strip()
            except Exception:
                generated_code = ""

        # If you want the endpoint to return the generated code as JSON, uncomment the next line:
        # return jsonify({"code": generated_code}), 200

        # For now return an acknowledgement so the endpoint doesn't hang (CLI mode will print in terminal)
        return jsonify({"status": "generated", "note": "If running CLI, see terminal for interactive replies."}), 200

    except openai.error.OpenAIError as api_error:
        logging.error(f"OpenAI API Error: {api_error}")
        return jsonify({"error": "OpenAI API error occurred"}), 500
    except Exception as e:
        logging.error(f"Server Error: {e}")
        return jsonify({"error": "An internal server error occurred"}), 500

@app.errorhandler(429)
def ratelimit_handler(e):
    """
    Custom error handler for rate limit exceeded.
    """
    return jsonify(error="Rate limit exceeded. Please try again later."), 429

def count_tokens(messages, model="gpt-4o"):
    """
    Count total tokens for a list of messages using tiktoken.
    """
    try:
        import tiktoken
    except ImportError:
        print("Please install tiktoken: pip install tiktoken")
        return 0

    # Select encoding for the model
    try:
        encoding = tiktoken.encoding_for_model(model)
    except Exception:
        encoding = tiktoken.get_encoding("cl100k_base")

    num_tokens = 0
    for msg in messages:
        # Each message follows OpenAI chat format
        num_tokens += 4  # every message metadata
        for key, value in msg.items():
            num_tokens += len(encoding.encode(str(value)))
    num_tokens += 2  # every reply is primed with <im_start>assistant
    return num_tokens

def compute_environmental_impact(token_count: int):
    if 0 < token_count <= 400:
        energy = token_count * ENERGY_PER_TOKEN_WH_SHORT
        water = token_count * WATER_PER_QUERY_SHORT 
        carbon = token_count * CARBON_PER_QUERY_SHORT
    elif 400 < token_count <= 2000:
        energy = token_count * ENERGY_PER_TOKEN_WH_MEDIUM
        water = token_count * WATER_PER_QUERY_MEDIUM
        carbon = token_count * CARBON_PER_QUERY_MEDIUM 
    elif token_count > 2000:
        energy = token_count * ENERGY_PER_TOKEN_WH_LONG
        water = token_count * WATER_PER_QUERY_LONG  
        carbon = token_count * CARBON_PER_QUERY_LONG 
    else:
        raise ValueError("Token count must be greater than 0")

    return energy, water, carbon


def run_cli():
    """
    Run interactive CLI chat loop using same system prompt and OpenAI.
    """
    
    system_content = (
        "You are an expert programming assistant helping undergraduate computer science students. "
        "Your task is to generate only the source code that solves the user's request. Follow these strict instructions:\n\n"
        "1. Output only the code — do not include explanations, descriptions, comments, or markdown formatting (such as triple backticks).\n\n"
        "2. The code must follow the requested programming paradigm:\n"
        "   - For procedural programming, avoid using classes. Structure the code with functions and ensure a clear main execution path.\n"
        "   - For object-oriented programming (OOP), use classes, encapsulate related data and behavior, and follow proper OOP design principles.\n\n"
        "3. If the language is Python and procedural style is requested, include a 'def main()' function and end with 'if __name__ == \"__main__\": main()'.\n\n"
        "4. Limit the code strictly to the specified topic(s). Do not introduce unrelated concepts, even if they might improve or extend the code.\n\n"
        "5. Use clean, readable, and idiomatic code that reflects good practice for the specified language.\n\n"
        "6. Never explain what the code does. Do not write inline or block comments.\n\n"
        "7. Do not include any logging, print statements, or UI unless explicitly required by the question.\n\n"
        "8. Output only the final code solution without imports unless required by the logic of the code.\n\n"
        "Always assume the user is a student looking to learn the core logic for a specific topic in a clear and isolated way."
    )

    messages = [{"role": "system", "content": system_content}]
    user_token_total = 0
    system_tokens_total = 0
    assistant_token_total = 0
   
    try:
        local_emissions_total = 0.0
        local_energy_total = 0.0

        stop_words = {"exit", "quit", "q", "selesai", "seeslai"}
        import re
        import tiktoken

        # Initialize tiktoken encoder once
        try:
            encoding = tiktoken.encoding_for_model(OPENAI_MODEL)
        except Exception:
            encoding = tiktoken.get_encoding("cl100k_base")

        stop_pattern = re.compile(r'^[\W_]*(?:' + r'|'.join(re.escape(w) for w in stop_words) + r')[\W_]*$', re.IGNORECASE)

        # Calculate system prompt tokens at start
        try:
            system_tokens = len(encoding.encode(system_content))
            print(f"\nSystem prompt tokens: {system_tokens}")
            print("This will be included in all interactions\n")
        except Exception:
            system_tokens = 0

        while True:
            message = input("User : ")
            if not message:
                continue

            normalized = message.strip()
            if stop_pattern.match(normalized):
                print("Exiting CLI.")
                break

            # Hitung token input user
            user_tokens = len(encoding.encode(message))
            user_token_total += user_tokens
            system_tokens_total += system_tokens
            
            messages.append({"role": "user", "content": message})
            
            call_tracker = None
            if EmissionsTracker is not None:
                try:
                    call_tracker = EmissionsTracker(
                        project_name="cli_inference",
                        measure_power_secs=1,
                        save_to_file=False,
                        log_level="error",  # Suppress logs:contentReference[oaicite:1]{index=1}
                    )
                    call_tracker.start()
                except Exception:
                    call_tracker = None

            chat = client.chat.completions.create(
                model=OPENAI_MODEL, 
                messages=messages
            )

            if call_tracker is not None:
                try:
                    emissions = call_tracker.stop()  # kg CO₂
                    local_emissions_total += emissions
                    try:
                        energy = call_tracker._total_energy.kwh
                        local_energy_total += energy if energy else 0.0
                    except Exception:
                        pass
                except Exception:
                    pass
            
            reply = chat.choices[0].message.content.strip()
            if reply:
                assistant_tokens = len(encoding.encode(reply))
                assistant_token_total += assistant_tokens
                
                print(f"ChatGPT: {reply}")
                print(f"\nTokens this interaction:")
                print(f"  System: {system_tokens}")
                print(f"  Input: {user_tokens}")
                print(f"  Output: {assistant_tokens}")
                print(f"  Total: {system_tokens + user_tokens + assistant_tokens}")
                print(f"Accumulated total: { system_tokens_total + user_token_total + assistant_token_total}\n")
                
                messages.append({"role": "assistant", "content": reply})
    except KeyboardInterrupt:
        print("\nExiting CLI.")
    finally:
        total_tokens = system_tokens_total + user_token_total + assistant_token_total
        print(f"\nSystem prompt tokens: {system_tokens_total}")
        print(f"Total user tokens: {user_token_total}")
        print(f"Total assistant tokens: {assistant_token_total}")
        print(f"Total tokens this session: {total_tokens}")

        total_energy_wh, total_water_ml, total_carbon_kg = compute_environmental_impact(total_tokens)

        if total_tokens < 10000:
            print("WARNING: Minimum token usage not reached (min 10000).")
        elif total_tokens > 25000:
            print("WARNING: Maximum token usage exceeded (max 25000).")

        print("\n=== LLM environmental impact ===")
        print(f"Energi total sesi (Wh): {total_energy_wh:.3f}")
        print(f"Penggunaan air total (ml): {total_water_ml:.3f}")
        print(f"Emisi karbon total (kg CO₂) (per-token model): {total_carbon_kg:.6f}")

        # Show local emissions/energy if measured
        if local_emissions_total > 0.0:
            print("\n=== Local environmental impact (measured by CodeCarbon) ===")
            if local_energy_total > 0.0:
                print(f"Energi total terukur (kWh): {local_energy_total:.6f}")
            print(f"Emisi karbon terukur (kg CO₂): {local_emissions_total:.6f}")

        total_carbon_all = total_carbon_kg + local_emissions_total
        print(f"Emisi karbon total (kg CO₂): {total_carbon_all:.6f}")

        print("\n=== Environmental Equivalence ===")

        print("🔋 Isi Ulang Baterai HP ≈", round((total_energy_wh / 15) * 100, 2), "% baterai")
        print("🍜 Memasak mie instan ≈", round((total_energy_wh / 900) * 100, 2), "% dari satu porsi mie instan")
        print("☕ Kopi ≈", round((total_water_ml / 150) * 100, 2), "% dari satu cangkir kopi")
        lampu_menit = (total_energy_wh / 5) * 60
        print("💡 Lampu 5 Watt ≈", round(lampu_menit, 2), "menit menyala")
        game_detik = (total_energy_wh / 200) * 3600
        print("🎮 Bermain Game PC selama ≈", round(game_detik, 2), "detik")
        tiktok_detik = (total_energy_wh / 1.85) * 60
        print("📱 Scroll TikTok selama ≈", round(tiktok_detik, 2), "detik")
        motor_meter = (total_carbon_kg / 0.09) * 1000
        print("🛵 Berkendara dengan motor sejauh ≈", round(motor_meter, 2), "meter")

        # Jika token dalam rentang, hitung sisa token dan poin gamifikasi
        if 10000 <= total_tokens <= 25000:
            remaining_tokens = 25000 - total_tokens
            # Konversi sisa token ke point (misal: 1 token = 1 point)
            gamification_points = remaining_tokens
            print(f"Sisa token: {remaining_tokens}")
            print(f"Point gamifikasi yang didapat: {gamification_points}")
            
        
if __name__ == '__main__':
    # Run interactive CLI when executed directly.
    run_cli()
    # If you prefer to run the Flask server instead, comment out run_cli() above and uncomment the next line:
    # app.run(debug=True)
