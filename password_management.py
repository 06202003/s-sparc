from flask import Blueprint, request, jsonify, session
import hashlib
from app import get_db_connection, require_login
import logging

password_management = Blueprint('password_management', __name__)

@password_management.route('/change-password', methods=['POST'])
@require_login
def change_password():
    """API endpoint to change user password."""
    data = request.json
    user_id = session.get('user_id')
    old_password = data.get('old_password')
    new_password = data.get('new_password')

    if not old_password or not new_password:
        return jsonify({"error": "Both old and new passwords are required."}), 400

    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            # Verify old password
            cur.execute("SELECT password FROM users WHERE user_id = %s", (user_id,))
            row = cur.fetchone()
            if not row or not hashlib.sha256(old_password.encode()).hexdigest() == row['password']:
                return jsonify({"error": "Old password is incorrect."}), 403

            # Update to new password
            hashed_new_password = hashlib.sha256(new_password.encode()).hexdigest()
            cur.execute("UPDATE users SET password = %s WHERE user_id = %s", (hashed_new_password, user_id))
            conn.commit()

        return jsonify({"message": "Password updated successfully."}), 200
    except Exception as e:
        logging.error(f"Error changing password: {e}")
        return jsonify({"error": "An error occurred while changing the password."}), 500
    finally:
        conn.close()