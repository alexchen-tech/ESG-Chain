import os

from fastapi import APIRouter, Depends
from pydantic import BaseModel

from app.core.security import get_current_user

router = APIRouter(tags=["material-emission"])


class MaterialEmissionEstimateRequest(BaseModel):
    hs_code: str
    supplier_id: str
    material_item_id: str
    material_name: str | None = None


class MaterialEmissionEstimateResponse(BaseModel):
    emissions_value: float
    unit: str = "kgCO2e/件"
    factor_source: str
    confidence: str  # high / medium / low


# 行業排放因子對照（依紡織/鋼鐵/電子等主要類別）
INDUSTRY_FACTORS: dict[str, tuple[float, str]] = {
    "52": (2.5, "cotton_industry_avg"),    # 棉
    "54": (3.8, "synthetic_fiber_avg"),    # 合成纖維
    "55": (4.2, "synthetic_staple_avg"),   # 合成短纖
    "60": (5.1, "knit_fabric_avg"),        # 針織布
    "61": (8.5, "knit_apparel_avg"),       # 針織衣物
    "62": (9.2, "woven_apparel_avg"),      # 梭織衣物
    "72": (2100.0, "steel_industry_avg"),  # 鋼鐵
    "73": (1800.0, "steel_product_avg"),   # 鋼鐵製品
    "76": (8900.0, "aluminum_avg"),        # 鋁
    "85": (15.0, "electronics_avg"),       # 電子元件
    "39": (3.2, "plastics_avg"),           # 塑料
    "40": (3.5, "rubber_avg"),             # 橡膠
}


def _estimate(hs_code: str) -> MaterialEmissionEstimateResponse:
    hs_prefix = hs_code[:2] if hs_code else ""
    factor_value, factor_source = INDUSTRY_FACTORS.get(hs_prefix, (5.0, "global_manufacturing_avg"))
    confidence = "high" if hs_prefix in INDUSTRY_FACTORS else "low"
    return MaterialEmissionEstimateResponse(
        emissions_value=factor_value,
        factor_source=factor_source,
        confidence=confidence,
    )


@router.post("/material-emission-estimate", response_model=MaterialEmissionEstimateResponse)
async def estimate_material_emission(
    request: MaterialEmissionEstimateRequest,
    current_user: dict = Depends(get_current_user),
) -> MaterialEmissionEstimateResponse:
    """根據 HS Code 查詢排放因子，估算物料碳排（kgCO₂e/件）"""
    return _estimate(request.hs_code)


@router.post("/celery/material-emission-estimate")
async def celery_trigger_estimate(request: MaterialEmissionEstimateRequest) -> dict:
    """
    由 Laravel Job 呼叫，將估算任務送入 Celery queue
    不驗證 JWT（僅限內網呼叫）
    """
    from app.tasks.material_emission_tasks import estimate_material_emission as celery_task

    task = celery_task.delay(
        material_item_id=request.material_item_id,
        supplier_id=request.supplier_id,
        hs_code=request.hs_code,
        material_name=request.material_name,
    )
    return {"task_id": task.id, "status": "queued"}
