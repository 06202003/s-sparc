import uuid
import hashlib
from fastapi import APIRouter, Request, HTTPException, status, Depends
from backend.core.db import (
    get_db_connection, 
    resolve_user_uuid, 
    get_user_api_key, 
    set_user_api_key, 
    delete_user_api_key,
    get_user_query_quota
)
from backend.models.auth import (
    RegisterRequest, 
    LoginRequest, 
    ChangePasswordRequest, 
    ForgotPasswordRequest, 
    ResetPasswordWithTokenRequest, 
    DirectResetPasswordRequest
)
from backend.models.user_key import SaveApiKeyRequest, ApiKeyStatusResponse, QueryQuotaResponse

router = APIRouter()

def hash_password(password: str) -> str:
    return hashlib.sha256(password.encode("utf-8")).hexdigest()

def get_current_user_id(request: Request) -> str:
    header_uid = request.headers.get("X-User-ID") or request.headers.get("x-user-id")
    if header_uid and str(header_uid).strip():
        return resolve_user_uuid(str(header_uid).strip())

    user_id = request.session.get("user_id")
    if not user_id:
        raise HTTPException(status_code=401, detail="Unauthorized. Silakan login.")
    return resolve_user_uuid(str(user_id).strip())

def require_admin(user_id: str = Depends(get_current_user_id)) -> str:
    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            cur.execute("SELECT COALESCE(is_admin,0) AS is_admin FROM users WHERE user_id=%s LIMIT 1", (user_id,))
            row = cur.fetchone()
            if not row or int(row.get('is_admin', 0) or 0) != 1:
                raise HTTPException(status_code=403, detail="Forbidden. Admins only.")
        return user_id
    except HTTPException:
        raise
    except Exception:
        raise HTTPException(status_code=403, detail="Forbidden. Admins only.")
    finally:
        conn.close()

@router.post(
    "/register", 
    status_code=status.HTTP_201_CREATED,
    summary="Register New Account",
    description="Registers a new student or instructor user account in the system with SHA-256 password hashing.",
    response_description="Confirmation message on successful registration"
)
async def register(data: RegisterRequest):
    if not data.username or not data.email or not data.password:
        raise HTTPException(status_code=400, detail="Username, email, dan password wajib diisi.")
        
    password_hash = hash_password(data.password)
    user_id = str(uuid.uuid4())
    
    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            cur.execute("SELECT user_id FROM users WHERE username=%s OR email=%s", (data.username, data.email))
            if cur.fetchone():
                raise HTTPException(status_code=409, detail="Username atau email sudah terdaftar.")
            
            cur.execute(
                "INSERT INTO users (user_id, username, email, password_hash) VALUES (%s, %s, %s, %s)",
                (user_id, data.username, data.email, password_hash)
            )
        conn.commit()
        return {"message": "Registrasi berhasil."}
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
    finally:
        conn.close()

@router.post(
    "/login",
    summary="User Login & Session Initialization",
    description="Authenticates username and password against database records, establishing a secure HTTP-only session cookie.",
    response_description="Login success message and active session establishment"
)
async def login(data: LoginRequest, request: Request):
    if not data.username or not data.password:
        raise HTTPException(status_code=400, detail="Username dan password wajib diisi.")
        
    password_hash = hash_password(data.password)
    
    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            cur.execute("SELECT user_id FROM users WHERE username=%s AND password_hash=%s", (data.username, password_hash))
            user = cur.fetchone()
            if not user:
                raise HTTPException(status_code=401, detail="Username atau password salah.")
            
            request.session["user_id"] = user["user_id"]
        return {"message": "Login berhasil."}
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
    finally:
        conn.close()

@router.post(
    "/logout",
    summary="User Logout",
    description="Terminates the current user session and clears authentication cookies.",
    response_description="Logout confirmation message"
)
async def logout(request: Request):
    request.session.pop("user_id", None)
    return {"message": "Logout berhasil."}

@router.get(
    "/whoami",
    summary="Get Authenticated User Profile",
    description="Retrieves the canonical user_id and username for the currently authenticated session.",
    response_description="User ID and username details"
)
async def whoami(user_id: str = Depends(get_current_user_id), request: Request = None):
    """Return basic info about the currently authenticated user (from server session)."""
    username = request.session.get('username') if request else None
    
    if not username:
        conn = get_db_connection()
        try:
            with conn.cursor() as cur:
                cur.execute("SELECT username FROM users WHERE user_id=%s LIMIT 1", (user_id,))
                row = cur.fetchone()
                if row:
                    username = row.get('username')
        except Exception:
            pass
        finally:
            conn.close()
            
    return {"user_id": user_id, "username": username}


@router.post(
    "/change-password",
    summary="Change Account Password",
    description="Updates the user's password after verifying their existing current password.",
    response_description="Success message confirming password update"
)
async def change_password(data: ChangePasswordRequest, user_id: str = Depends(get_current_user_id)):
    old_password_hash = hash_password(data.old_password)
    new_password_hash = hash_password(data.new_password)

    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            cur.execute("SELECT user_id FROM users WHERE user_id=%s AND password_hash=%s", (user_id, old_password_hash))
            user = cur.fetchone()
            if not user:
                raise HTTPException(status_code=401, detail="Password lama salah.")
                
            cur.execute("UPDATE users SET password_hash=%s WHERE user_id=%s", (new_password_hash, user_id))
        conn.commit()
        return {"message": "Password berhasil diubah."}
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
    finally:
        conn.close()


@router.post(
    "/forgot-password/request",
    summary="Request Password Reset Token & OTP",
    description="Finds account by username or email, generates a secure 32-character reset token and 6-digit OTP valid for 30 minutes, and stores it in the database.",
    response_description="Reset token, OTP code, username, masked email, and expiration time"
)
async def forgot_password_request(data: ForgotPasswordRequest):
    identifier = data.identifier.strip()
    if not identifier:
        raise HTTPException(status_code=400, detail="Username atau email wajib diisi.")

    import secrets
    import random
    reset_token = secrets.token_urlsafe(32)
    otp_code = str(random.randint(100000, 999999))
    reset_id = str(uuid.uuid4())

    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT user_id, username, email FROM users WHERE username=%s OR email=%s LIMIT 1",
                (identifier, identifier)
            )
            user = cur.fetchone()
            if not user:
                raise HTTPException(status_code=404, detail="Akun dengan username atau email tersebut tidak ditemukan.")

            user_id = user["user_id"]
            username = user["username"]
            email = user.get("email") or ""

            # Mask email for privacy (e.g., d***@domain.com)
            masked_email = email
            if "@" in email:
                parts = email.split("@")
                masked_user = parts[0][0] + "***" + (parts[0][-1] if len(parts[0]) > 1 else "")
                masked_email = f"{masked_user}@{parts[1]}"

            # Invalidate previous tokens for this user
            cur.execute("UPDATE password_resets SET used=1 WHERE user_id=%s", (user_id,))

            # Insert new reset token valid for 30 minutes
            cur.execute(
                "INSERT INTO password_resets (id, user_id, reset_token, otp_code, expires_at, used, created_at) "
                "VALUES (%s, %s, %s, %s, DATE_ADD(NOW(), INTERVAL 30 MINUTE), 0, NOW())",
                (reset_id, user_id, reset_token, otp_code)
            )
        conn.commit()

        return {
            "message": "Permintaan reset password berhasil dibuat.",
            "reset_token": reset_token,
            "otp_code": otp_code,
            "username": username,
            "masked_email": masked_email,
            "expires_in_minutes": 30
        }
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
    finally:
        conn.close()


@router.post(
    "/forgot-password/verify-token",
    summary="Verify Reset Token or OTP",
    description="Validates that a provided reset token or 6-digit OTP code exists, is unexpired, and has not yet been used.",
    response_description="Validity status, username, and user ID"
)
async def forgot_password_verify_token(data: dict):
    token = (data.get("token") or "").strip()
    otp = (data.get("otp") or "").strip()
    if not token and not otp:
        raise HTTPException(status_code=400, detail="Token atau kode OTP wajib disertakan.")

    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            if token:
                cur.execute(
                    "SELECT pr.id, pr.user_id, u.username, u.email FROM password_resets pr "
                    "JOIN users u ON pr.user_id = u.user_id "
                    "WHERE pr.reset_token=%s AND pr.used=0 AND pr.expires_at > NOW() LIMIT 1",
                    (token,)
                )
            else:
                cur.execute(
                    "SELECT pr.id, pr.user_id, u.username, u.email FROM password_resets pr "
                    "JOIN users u ON pr.user_id = u.user_id "
                    "WHERE pr.otp_code=%s AND pr.used=0 AND pr.expires_at > NOW() LIMIT 1",
                    (otp,)
                )
            record = cur.fetchone()
            if not record:
                raise HTTPException(status_code=400, detail="Token atau kode OTP tidak valid atau sudah kedaluwarsa.")

            return {
                "valid": True,
                "username": record["username"],
                "user_id": record["user_id"]
            }
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
    finally:
        conn.close()


@router.post(
    "/forgot-password/reset",
    summary="Reset Password with Token",
    description="Updates the user's password in the database and consumes the reset token to prevent reuse.",
    response_description="Success message confirming the password change"
)
async def forgot_password_reset(data: ResetPasswordWithTokenRequest):
    token = data.token.strip()
    new_password = data.new_password.strip()

    if not token or not new_password:
        raise HTTPException(status_code=400, detail="Token dan password baru wajib diisi.")
    if len(new_password) < 4:
        raise HTTPException(status_code=400, detail="Password baru minimal 4 karakter.")

    new_hash = hash_password(new_password)
    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT pr.id, pr.user_id FROM password_resets pr "
                "WHERE pr.reset_token=%s AND pr.used=0 AND pr.expires_at > NOW() LIMIT 1",
                (token,)
            )
            record = cur.fetchone()
            if not record:
                raise HTTPException(status_code=400, detail="Token reset tidak valid atau sudah kedaluwarsa.")

            user_id = record["user_id"]
            reset_id = record["id"]

            # Update password
            cur.execute("UPDATE users SET password_hash=%s WHERE user_id=%s", (new_hash, user_id))
            # Mark reset token as used
            cur.execute("UPDATE password_resets SET used=1 WHERE id=%s", (reset_id,))
        conn.commit()

        return {"message": "Password berhasil diubah. Silakan login kembali dengan password baru."}
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
    finally:
        conn.close()


@router.post(
    "/forgot-password/direct-reset",
    summary="Direct Password Reset with Username + Email",
    description="Directly updates password when username and registered institutional email match an existing record.",
    response_description="Success message confirming password reset"
)
async def forgot_password_direct_reset(data: DirectResetPasswordRequest):
    username = data.username.strip()
    email = data.email.strip()
    new_password = data.new_password.strip()

    if not username or not email or not new_password:
        raise HTTPException(status_code=400, detail="Username, email, dan password baru wajib diisi.")
    if len(new_password) < 4:
        raise HTTPException(status_code=400, detail="Password baru minimal 4 karakter.")

    new_hash = hash_password(new_password)
    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT user_id FROM users WHERE username=%s AND email=%s LIMIT 1",
                (username, email)
            )
            user = cur.fetchone()
            if not user:
                raise HTTPException(status_code=404, detail="Kombinasi username dan email tidak cocok dengan data terdaftar.")

            user_id = user["user_id"]
            cur.execute("UPDATE users SET password_hash=%s WHERE user_id=%s", (new_hash, user_id))
        conn.commit()

        return {"message": "Password berhasil diubah. Silakan login kembali dengan password baru."}
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
    finally:
        conn.close()


@router.get(
    "/user/api-key",
    summary="Get User's Configured Google Gemini API Key Status",
    description="Checks if the authenticated user has registered their personal Google Gemini API key.",
    response_model=ApiKeyStatusResponse
)
async def get_user_api_key_status(user_id: str = Depends(get_current_user_id)):
    api_key = get_user_api_key(user_id, provider="gemini")
    if not api_key:
        return ApiKeyStatusResponse(
            has_key=False,
            provider="gemini",
            masked_key=None,
            message="Belum ada API key yang terdaftar untuk akun ini."
        )
    
    # Mask API Key (e.g. AIzaSy...xxxx)
    if len(api_key) > 10:
        masked = api_key[:6] + "..." + api_key[-4:]
    else:
        masked = "***"
        
    return ApiKeyStatusResponse(
        has_key=True,
        provider="gemini",
        masked_key=masked,
        message="API key aktif terdaftar."
    )


@router.post(
    "/user/api-key",
    summary="Save / Update User's Google Gemini API Key",
    description="Registers or updates the personal Google Gemini API key for the authenticated user."
)
async def save_user_api_key(data: SaveApiKeyRequest, user_id: str = Depends(get_current_user_id)):
    raw_key = data.api_key.strip()
    if not raw_key or len(raw_key) < 10:
        raise HTTPException(status_code=400, detail="API key tidak valid. Panjang minimal 10 karakter.")

    provider = (data.provider or "gemini").lower().strip()
    terms_accepted = bool(data.terms_accepted if data.terms_accepted is not None else True)
    success = set_user_api_key(user_id, raw_key, provider=provider, terms_accepted=terms_accepted)
    if not success:
        raise HTTPException(status_code=500, detail="Gagal menyimpan API key ke database.")

    masked = raw_key[:6] + "..." + raw_key[-4:] if len(raw_key) > 10 else "***"
    return {
        "status": "success",
        "message": "API key berhasil disimpan dan Syarat & Ketentuan telah disetujui.",
        "provider": provider,
        "masked_key": masked,
        "terms_accepted": terms_accepted
    }


@router.delete(
    "/user/api-key",
    summary="Remove User's Registered API Key",
    description="Removes the user's personal Google Gemini API key from the system."
)
async def remove_user_api_key(user_id: str = Depends(get_current_user_id)):
    success = delete_user_api_key(user_id, provider="gemini")
    if not success:
        raise HTTPException(status_code=500, detail="Gagal menghapus API key.")
    return {
        "status": "success",
        "message": "API key berhasil dihapus."
    }


@router.get(
    "/user/query-quota",
    response_model=QueryQuotaResponse,
    summary="Get User's Real-time Gemini API Query Quota",
    description="Returns the real-time daily remaining queries, used queries, and rate limits for the authenticated user's personal API key."
)
async def get_query_quota(user_id: str = Depends(get_current_user_id)):
    quota_data = get_user_query_quota(user_id, provider="gemini")
    return QueryQuotaResponse(**quota_data)



