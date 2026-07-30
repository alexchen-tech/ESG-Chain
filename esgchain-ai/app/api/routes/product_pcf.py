"""產品 PCF 計算端點。由 esgchain-api（PcfCalculationService）server-to-server 呼叫，
遵守 CLAUDE.md：計算邏輯集中在 esgchain-ai，esgchain-api 僅蒐集 BOM 輸入並存快照。
同步端點，不走 Celery（範圍刻意限縮）。
"""
from fastapi import APIRouter, Depends

from app.core.security import verify_internal_service
from app.schemas.product_pcf import ProductPcfCalculateRequest, ProductPcfCalculateResponse
from app.services.product_pcf_service import calculate_product_pcf

router = APIRouter(prefix="/product-pcf", tags=["product-pcf"])


@router.post(
    "/calculate",
    response_model=ProductPcfCalculateResponse,
    dependencies=[Depends(verify_internal_service)],
)
async def calculate(payload: ProductPcfCalculateRequest) -> ProductPcfCalculateResponse:
    """依 BOM 行（含最佳供應商碳排）計算 total_pcf、iso14067_ready、pcr_ratio。純函式，不觸碰 DB。"""
    return calculate_product_pcf(payload)
