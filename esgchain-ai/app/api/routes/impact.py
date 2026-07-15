"""
供應商 Impact 計分端點。由 esgchain-api（ImpactScoreService）server-to-server 呼叫，
遵守 CLAUDE.md：計分邏輯集中在 esgchain-ai，esgchain-api 僅蒐集輸入並讀取結果。
"""
from fastapi import APIRouter

from app.schemas.impact import ImpactScoreRequest, ImpactScoreResponse
from app.services.impact_service import calculate_impact_score

router = APIRouter(tags=["impact"])


@router.post("/impact-scoring", response_model=ImpactScoreResponse)
async def score_impact(request: ImpactScoreRequest) -> ImpactScoreResponse:
    """四因子加權計算供應商 Impact 值（1–5）。純函式，不觸碰 DB。"""
    return calculate_impact_score(request)
