from pydantic import BaseModel, EmailStr, Field

class RegisterRequest(BaseModel):
    username: str = Field(..., description="Unique username for the student/instructor account", example="2172003")
    email: EmailStr = Field(..., description="Valid institutional or personal email address", example="2172003@maranatha.ac.id")
    password: str = Field(..., description="Plain-text password (minimum 4 characters)", min_length=4, example="Password123#")

class LoginRequest(BaseModel):
    username: str = Field(..., description="Registered account username", example="2172003")
    password: str = Field(..., description="Account password", example="Password123#")

class ChangePasswordRequest(BaseModel):
    old_password: str = Field(..., description="Current active account password", example="Password123#")
    new_password: str = Field(..., description="New password to set (minimum 4 characters)", min_length=4, example="NewPassword456#")

class ForgotPasswordRequest(BaseModel):
    identifier: str = Field(..., description="Registered username or email address", example="2172003")

class ResetPasswordWithTokenRequest(BaseModel):
    token: str = Field(..., description="Secure reset token generated from forgot-password request", example="eN9z3kL0pA_4XwY1...")
    new_password: str = Field(..., description="New password to replace the forgotten one", min_length=4, example="MyNewSecurePass99#")

class DirectResetPasswordRequest(BaseModel):
    username: str = Field(..., description="Registered username", example="2172003")
    email: str = Field(..., description="Registered email that matches the username", example="2172003@maranatha.ac.id")
    new_password: str = Field(..., description="New password to set directly", min_length=4, example="MyNewSecurePass99#")


