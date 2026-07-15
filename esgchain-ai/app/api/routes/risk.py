from fastapi import APIRouter, Depends
from sqlalchemy.ext.asyncio import AsyncSession

from app.core.security import get_current_user
from app.db.postgresql import get_db
from app.schemas.risk import RiskAssessmentRequest, RiskAssessmentResponse
from app.services.risk_service import assess_risk

router = APIRouter(prefix="/risk", tags=["risk"])


@router.post("/assess", response_model=RiskAssessmentResponse)
async def create_risk_assessment(
    request: RiskAssessmentRequest,
    db: AsyncSession = Depends(get_db),
    current_user: dict = Depends(get_current_user),
) -> RiskAssessmentResponse:
    """
    供應商風險評分

    維度：environment, social, governance, country, supply_chain
    分數範圍：0–100（越高越好）
    """
    return await assess_risk(db, request)
