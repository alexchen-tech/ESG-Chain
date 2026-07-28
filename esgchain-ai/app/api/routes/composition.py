"""
產品循環經濟成分彙總端點。由 esgchain-api（CircularityCalculationService）server-to-server
呼叫，遵守 CLAUDE.md：計算邏輯集中在 esgchain-ai，esgchain-api 僅蒐集輸入並存快照。
"""
from fastapi import APIRouter

from app.schemas.composition import CompositionCalculateRequest, CompositionCalculateResponse
from app.services.composition_service import calculate_composition

router = APIRouter(prefix="/composition", tags=["composition"])


@router.post("/calculate", response_model=CompositionCalculateResponse)
async def calculate(payload: CompositionCalculateRequest) -> CompositionCalculateResponse:
    """依 BOM 品項＋物料循環經濟欄位，加權彙總出產品的成分佔比與回收材料比。純函式，不觸碰 DB。"""
    return calculate_composition(payload)
