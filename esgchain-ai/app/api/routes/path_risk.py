from fastapi import APIRouter, Depends

from app.core.security import get_current_user
from app.schemas.path_risk import (
    PathRiskRequest,
    PathRiskResponse,
    SupplierReplacementRequest,
    SupplierReplacementResponse,
)
from app.services.path_risk_service import calculate_path_risk, calculate_replacement_candidates

router = APIRouter(prefix="/path-risk", tags=["path-risk"])


@router.post("", response_model=PathRiskResponse)
async def compute_path_risk(
    request: PathRiskRequest,
    current_user: dict = Depends(get_current_user),
) -> PathRiskResponse:
    """
    計算商品出口路徑風險分

    公式：Chain_Risk × 市場法規放大係數
    Chain_Risk = Σ(axis1_score_i/100 × carbon_share_i)
    Amplifier  = 1 + missing_mandatory / total_mandatory
    """
    return calculate_path_risk(request)


@router.post("/replacement-candidates", response_model=SupplierReplacementResponse)
async def get_replacement_candidates(
    request: SupplierReplacementRequest,
    current_user: dict = Depends(get_current_user),
) -> SupplierReplacementResponse:
    """
    替換供應商模擬推薦

    模擬以候選供應商替換後的路徑風險改善幅度，依改善幅度降序排列
    """
    return calculate_replacement_candidates(request)
