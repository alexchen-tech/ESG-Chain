from pydantic import BaseModel
from typing import Optional


class RiskFactorInput(BaseModel):
    dimension: str
    factor_name: str
    score: float
    weight: float = 1.0
    evidence: Optional[str] = None


class RiskAssessmentRequest(BaseModel):
    supplier_id: str
    assessment_year: int
    factors: list[RiskFactorInput]
    notes: Optional[str] = None


class RiskAssessmentResponse(BaseModel):
    id: str
    supplier_id: str
    assessment_year: int
    score_environment: Optional[float]
    score_social: Optional[float]
    score_governance: Optional[float]
    score_country: Optional[float]
    score_supply_chain: Optional[float]
    total_score: Optional[float]
    risk_level: Optional[str]
    job_id: Optional[str]
