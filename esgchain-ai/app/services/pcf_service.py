"""
PCF 碳足跡計算服務

公式：totalKgCO2e = quantity × emission_factor（kgCO2e/unit）

支援 Scope 1（直接排放）、Scope 2（用電）、Scope 3（供應鏈）
"""
import uuid
from datetime import datetime, timezone
from typing import Optional

from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from app.models.pcf_record import PCFRecord
from app.schemas.pcf import PCFEntryRequest, PCFEntryResult, PCFSummaryResponse
from app.services.emission_factor_service import resolve_emission_factor


async def calculate_and_save(
    db: AsyncSession,
    entry: PCFEntryRequest,
    supplier_country: Optional[str] = None,
) -> PCFRecord:
    """計算單筆碳足跡並寫入 DB"""
    ef_id, ef_value = await resolve_emission_factor(
        db,
        category=entry.category or _infer_category(entry.scope, entry.unit),
        country=supplier_country,
        factor_id=entry.emission_factor_id,
    )

    total_kg_co2e = entry.quantity * ef_value

    record = PCFRecord(
        id=uuid.uuid4(),
        supplier_id=entry.supplier_id,
        reference_year=entry.reference_year,
        scope=entry.scope,
        category=entry.category,
        activity_description=entry.activity_description,
        quantity=entry.quantity,
        unit=entry.unit,
        emission_factor_id=uuid.UUID(ef_id) if ef_id else None,
        emission_factor_value=ef_value,
        total_kg_co2e=total_kg_co2e,
        data_quality=entry.data_quality,
        notes=entry.notes,
        created_at=datetime.now(timezone.utc),
        updated_at=datetime.now(timezone.utc),
    )
    db.add(record)
    await db.commit()
    await db.refresh(record)
    return record


async def get_summary(
    db: AsyncSession, supplier_id: str, reference_year: int
) -> PCFSummaryResponse:
    """取得供應商某年度 PCF 總計（Scope 1/2/3 各別合計）"""
    result = await db.execute(
        select(PCFRecord).where(
            PCFRecord.supplier_id == supplier_id,
            PCFRecord.reference_year == reference_year,
        )
    )
    records = result.scalars().all()

    scope_totals = {1: 0.0, 2: 0.0, 3: 0.0}
    for r in records:
        scope_totals[r.scope] = scope_totals.get(r.scope, 0.0) + r.total_kg_co2e

    entries = [
        PCFEntryResult(
            id=str(r.id),
            scope=r.scope,
            category=r.category,
            activity_description=r.activity_description,
            quantity=r.quantity,
            unit=r.unit,
            emission_factor_value=r.emission_factor_value,
            total_kg_co2e=r.total_kg_co2e,
            data_quality=r.data_quality,
        )
        for r in records
    ]

    return PCFSummaryResponse(
        supplier_id=supplier_id,
        reference_year=reference_year,
        scope1_total=round(scope_totals[1], 4),
        scope2_total=round(scope_totals[2], 4),
        scope3_total=round(scope_totals[3], 4),
        grand_total=round(sum(scope_totals.values()), 4),
        entries=entries,
    )


def _infer_category(scope: int, unit: str) -> str:
    """依 scope 和單位推測排放類別"""
    unit_lower = unit.lower()
    if scope == 2:
        return "electricity_global"
    if "l" in unit_lower or "litre" in unit_lower:
        return "diesel"
    if "kwh" in unit_lower:
        return "electricity_global"
    if "m3" in unit_lower or "m³" in unit_lower:
        return "natural_gas"
    if "km" in unit_lower:
        return "road_freight"
    return "diesel"  # 預設


async def upsert_pcf_record_from_portal(db: AsyncSession, payload) -> PCFRecord:
    """供應商從 Portal 填寫逐料碳足跡時，建立或更新 PCFRecord（依 pcf_request_line_id upsert）"""
    from datetime import datetime, timezone

    stmt = select(PCFRecord).where(
        PCFRecord.pcf_request_line_id == payload.pcf_request_line_id
    )
    result = await db.execute(stmt)
    record = result.scalar_one_or_none()

    if record:
        record.total_kg_co2e = payload.declared_value
        record.quantity_unit = payload.quantity_unit
        record.data_quality = payload.data_quality
        record.updated_at = datetime.now(timezone.utc)
    else:
        record = PCFRecord(
            supplier_id=payload.supplier_id,
            pcf_request_line_id=payload.pcf_request_line_id,
            bom_line_id=payload.bom_line_id,
            reference_year=datetime.now(timezone.utc).year,
            scope=3,
            category="purchased_goods_services",
            quantity=1.0,
            unit=payload.quantity_unit,
            quantity_unit=payload.quantity_unit,
            total_kg_co2e=payload.declared_value,
            data_quality=payload.data_quality,
        )
        db.add(record)

    await db.commit()
    await db.refresh(record)
    return record
