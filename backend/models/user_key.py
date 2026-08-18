from pydantic import BaseModel, Field
from typing import Optional

class SaveApiKeyRequest(BaseModel):
    api_key: str = Field(..., min_length=10, description="Google Gemini API Key")
    provider: Optional[str] = Field("gemini", description="AI Provider (default: gemini)")
    terms_accepted: Optional[bool] = Field(True, description="Acceptance of Terms & Conditions for personal API key usage")

class ApiKeyStatusResponse(BaseModel):
    has_key: bool
    provider: str = "gemini"
    masked_key: Optional[str] = None
    terms_accepted: bool = True
    message: Optional[str] = None

class QueryQuotaResponse(BaseModel):
    has_key: bool
    provider: str = "gemini"
    masked_key: Optional[str] = None
    daily_limit: int = Field(1500, description="Max queries per day on Gemini Free Tier")
    daily_used: int = Field(0, description="Queries used today")
    daily_remaining: int = Field(1500, description="Remaining queries today")
    rate_limit_rpm: int = Field(15, description="Requests per minute limit")
    cooldown_seconds: int = Field(60, description="Cooldown seconds between consecutive requests")
    tier_label: str = Field("Personal Gemini Key (1,500 RPD / 15 RPM)", description="Access Tier Description")
    terms_accepted: bool = Field(True, description="Whether T&C are acknowledged")
