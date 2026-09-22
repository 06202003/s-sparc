import os
import uuid
import logging
from backend.core.db import get_db_connection

PUE = 1.12
WUE_SITE_L_PER_KWH = 0.30
WUE_SOURCE_L_PER_KWH = 4.35
CIF_KG_PER_KWH = 0.384
ENERGY_PER_TOKEN_WH = 0.003
SERVER_NAME = os.getenv("SERVER_NAME", "s-sparc-lab-server-01")

def calculate_environmental_impact(total_tokens: int) -> dict:
    """
    Computes environmental footprint calibrated for Indonesian power grid CIF and modern datacenter PUE:
    - Energy (Wh & kWh)
    - Carbon (kg CO2e & g CO2e)
    - Freshwater Consumption (mL & L)
    - Tree Absorption Equivalency (years of tree absorption)
    """
    energy_wh = total_tokens * ENERGY_PER_TOKEN_WH * PUE
    energy_kwh = energy_wh / 1000.0
    carbon_kg = energy_kwh * CIF_KG_PER_KWH
    carbon_g = carbon_kg * 1000.0
    water_ml = energy_kwh * (WUE_SITE_L_PER_KWH + WUE_SOURCE_L_PER_KWH) * 1000.0
    
    # 1 mature tree absorbs approx 21.77 kg CO2 / year = 0.0596 kg CO2 / day
    trees_saved_equiv = carbon_kg / 21.77
    
    return {
        "energy_wh": round(energy_wh, 4),
        "energy_kwh": round(energy_kwh, 6),
        "carbon_kg": round(carbon_kg, 6),
        "carbon_g_co2e": round(carbon_g, 3),
        "water_ml": round(water_ml, 3),
        "trees_saved_equiv": round(trees_saved_equiv, 6),
        "pue": PUE,
        "cif_kg_per_kwh": CIF_KG_PER_KWH
    }

def log_environmental_impact(user_id: str, job_id: str, total_tokens: int, assessment_id: str = None, course_id: str = None):
    impact = calculate_environmental_impact(total_tokens)
    from backend.core.db import resolve_user_uuid, resolve_assessment_id
    resolved_uid = resolve_user_uuid(user_id)
    resolved_aid = resolve_assessment_id(assessment_id)
    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            # 1. Log to environmental_impact_logs
            try:
                cur.execute(
                    "INSERT INTO environmental_impact_logs (id, user_id, job_id, course_id, assessment_id, energy_wh, energy_kwh, carbon_kg, water_ml, created_at) "
                    "VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, NOW())",
                    (str(uuid.uuid4()), resolved_uid, job_id, course_id, resolved_aid, impact["energy_wh"], impact["energy_kwh"], impact["carbon_kg"], impact["water_ml"])
                )
            except Exception as e1:
                logging.warning(f"Failed to insert environmental_impact_logs: {e1}")

            # 2. Log to local_carbon_logs
            try:
                cur.execute(
                    "INSERT INTO local_carbon_logs (id, server_name, carbon_kg, created_at) "
                    "VALUES (%s, %s, %s, NOW())",
                    (str(uuid.uuid4()), SERVER_NAME, impact["carbon_kg"])
                )
            except Exception as e2:
                logging.warning(f"Failed to insert local_carbon_logs: {e2}")

        conn.commit()
    finally:
        conn.close()
