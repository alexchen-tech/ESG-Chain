from fastapi import APIRouter, Depends, Query
from sqlalchemy.ext.asyncio import AsyncSession

from app.core.security import get_current_user
from app.db.postgresql import get_db
from app.schemas.pcf import (
    EmissionFactorResponse,
    PCFBatchRequest,
    PCFEntryRequest,
    PCFEntryResult,
    PCFSummaryResponse,
    PcfRecordCreateRequest,
    PcfRecordCreateResponse,
)
from app.services.emission_factor_service import lookup_factor, get_default_factor
from app.services.pcf_service import calculate_and_save, get_summary, upsert_pcf_record_from_portal

router = APIRouter(prefix="/pcf", tags=["pcf"])


@router.post("/calculate", response_model=PCFEntryResult)
async def calculate_pcf(
    entry: PCFEntryRequest,
    db: AsyncSession = Depends(get_db),
    current_user: dict = Depends(get_current_user),
) -> PCFEntryResult:
    """計算單筆碳足跡並儲存"""
    record = await calculate_and_save(db, entry)
    return PCFEntryResult(
        id=str(record.id),
        scope=record.scope,
        category=record.category,
        activity_description=record.activity_description,
        quantity=record.quantity,
        unit=record.unit,
        emission_factor_value=record.emission_factor_value,
        total_kg_co2e=record.total_kg_co2e,
        data_quality=record.data_quality,
    )


@router.get("/summary/{supplier_id}", response_model=PCFSummaryResponse)
async def get_pcf_summary(
    supplier_id: str,
    year: int = Query(..., ge=2000, le=2100),
    db: AsyncSession = Depends(get_db),
    current_user: dict = Depends(get_current_user),
) -> PCFSummaryResponse:
    """取得供應商 PCF 年度總計（Scope 1/2/3）"""
    return await get_summary(db, supplier_id, year)


@router.post("/records", response_model=PcfRecordCreateResponse)
async def create_pcf_record(
    payload: PcfRecordCreateRequest,
    db: AsyncSession = Depends(get_db),
    current_user: dict = Depends(get_current_user),
) -> PcfRecordCreateResponse:
    """接受 esgchain-api Portal 呼叫，建立或更新 PCFRecord（供應商填寫逐料碳足跡）"""
    record = await upsert_pcf_record_from_portal(db, payload)
    return PcfRecordCreateResponse(
        id=str(record.id),
        pcf_request_line_id=record.pcf_request_line_id or "",
        bom_line_id=record.bom_line_id or "",
        supplier_id=record.supplier_id,
        total_kg_co2e=record.total_kg_co2e,
        quantity_unit=record.quantity_unit or "",
        data_quality=record.data_quality,
    )


@router.get("/emission-factors/lookup")
async def lookup_emission_factor(
    category: str,
    country: str = Query(None),
    db: AsyncSession = Depends(get_db),
    current_user: dict = Depends(get_current_user),
):
    """查找排放係數"""
    ef = await lookup_factor(db, category, country)
    if ef:
        return {
            "success": True,
            "data": {
                "id": str(ef.id),
                "source": ef.source,
                "category": ef.category,
                "unit": ef.unit,
                "co2_factor": ef.co2_factor,
                "total_co2e_per_unit": ef.total_co2e_per_unit,
                "country": ef.country,
                "year": ef.year,
            },
        }
    # 回傳內建預設值
    default_val = get_default_factor(category, country)
    return {
        "success": True,
        "data": {
            "id": None,
            "source": "built-in default",
            "category": category,
            "unit": "varies",
            "co2_factor": default_val,
            "total_co2e_per_unit": default_val,
            "country": country,
            "year": None,
        },
    }
