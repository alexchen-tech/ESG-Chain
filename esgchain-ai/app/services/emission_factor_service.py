"""
Emission Factor 查找服務

優先順序：
1. 指定 emission_factor_id → 直接從 DB 取
2. 指定 category + country → 最新版本
3. 指定 category → 全球預設
4. 找不到 → 使用內建預設值
"""
from typing import Optional
from uuid import UUID

from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from app.models.emission_factor import EmissionFactor

# 內建預設排放係數（IPCC AR6 / DEFRA 2023 近似值）
_DEFAULT_FACTORS: dict[str, float] = {
    "electricity_tw": 0.494,      # kgCO2e/kWh 台灣電網
    "electricity_cn": 0.570,      # kgCO2e/kWh 中國電網
    "electricity_vn": 0.617,      # kgCO2e/kWh 越南電網
    "electricity_global": 0.460,  # kgCO2e/kWh 全球平均
    "diesel": 2.688,              # kgCO2e/L
    "gasoline": 2.392,            # kgCO2e/L
    "natural_gas": 2.202,         # kgCO2e/m³
    "coal": 2.419,                # kgCO2e/kg
    "air_freight": 0.602,         # kgCO2e/tonne-km
    "sea_freight": 0.016,         # kgCO2e/tonne-km
    "road_freight": 0.096,        # kgCO2e/tonne-km
    "steel_production": 1.850,    # kgCO2e/kg
    "cement_production": 0.820,   # kgCO2e/kg
    "aluminium_production": 11.50, # kgCO2e/kg
}


async def get_factor_by_id(
    db: AsyncSession, factor_id: str
) -> Optional[EmissionFactor]:
    result = await db.execute(
        select(EmissionFactor).where(EmissionFactor.id == UUID(factor_id))
    )
    return result.scalar_one_or_none()


async def lookup_factor(
    db: AsyncSession,
    category: str,
    country: Optional[str] = None,
    year: Optional[int] = None,
) -> Optional[EmissionFactor]:
    """查找最適合的排放係數"""
    query = select(EmissionFactor).where(EmissionFactor.category == category)

    if country:
        # 優先國家特定，其次全球
        result = await db.execute(
            query.where(EmissionFactor.country == country).order_by(
                EmissionFactor.year.desc().nullslast()
            ).limit(1)
        )
        ef = result.scalar_one_or_none()
        if ef:
            return ef

    # 全球預設
    result = await db.execute(
        query.where(EmissionFactor.country.is_(None)).order_by(
            EmissionFactor.year.desc().nullslast()
        ).limit(1)
    )
    return result.scalar_one_or_none()


def get_default_factor(category: str, country: Optional[str] = None) -> float:
    """使用內建預設值（DB 查不到時備用）"""
    if country:
        key = f"{category}_{country.lower()}"
        if key in _DEFAULT_FACTORS:
            return _DEFAULT_FACTORS[key]
    return _DEFAULT_FACTORS.get(category, 0.0)


async def resolve_emission_factor(
    db: AsyncSession,
    category: str,
    country: Optional[str] = None,
    factor_id: Optional[str] = None,
) -> tuple[Optional[str], float]:
    """
    解析排放係數，回傳 (factor_id, co2e_per_unit)
    """
    if factor_id:
        ef = await get_factor_by_id(db, factor_id)
        if ef:
            return str(ef.id), ef.total_co2e_per_unit

    ef = await lookup_factor(db, category, country)
    if ef:
        return str(ef.id), ef.total_co2e_per_unit

    # 最後備援：使用內建預設
    return None, get_default_factor(category, country)
