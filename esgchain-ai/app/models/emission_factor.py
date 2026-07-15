import uuid
from datetime import datetime, timezone

from sqlalchemy import Column, DateTime, Float, Index, Integer, String, Text
from sqlalchemy.dialects.postgresql import UUID

from app.db.postgresql import Base


class EmissionFactor(Base):
    __tablename__ = "emission_factors"

    id = Column(UUID(as_uuid=True), primary_key=True, default=uuid.uuid4)
    source = Column(String(50), nullable=False, comment="IPCC, DEFRA, EPA, ECOINVENT")
    category = Column(String(100), nullable=False, comment="electricity, fuel, transport...")
    subcategory = Column(String(100), nullable=True)
    activity = Column(String(200), nullable=False)
    unit = Column(String(50), nullable=False, comment="kgCO2e/kWh, kgCO2e/L...")
    co2_factor = Column(Float, nullable=False, comment="kgCO2 per unit")
    ch4_factor = Column(Float, nullable=True, comment="kgCH4 per unit")
    n2o_factor = Column(Float, nullable=True, comment="kgN2O per unit")
    gwp_co2 = Column(Float, default=1.0)
    gwp_ch4 = Column(Float, default=28.0)
    gwp_n2o = Column(Float, default=265.0)
    country = Column(String(2), nullable=True, comment="ISO 3166-1 alpha-2, NULL = global")
    year = Column(Integer, nullable=True, comment="reference year")
    description = Column(Text, nullable=True)
    created_at = Column(DateTime(timezone=True), default=lambda: datetime.now(timezone.utc))
    updated_at = Column(DateTime(timezone=True), default=lambda: datetime.now(timezone.utc), onupdate=lambda: datetime.now(timezone.utc))

    __table_args__ = (
        Index("idx_ef_category_country", "category", "country"),
        Index("idx_ef_source", "source"),
    )

    @property
    def total_co2e_per_unit(self) -> float:
        """計算 CO2e 換算值（包含 CH4 和 N2O 的 GWP）"""
        co2e = self.co2_factor * self.gwp_co2
        if self.ch4_factor:
            co2e += self.ch4_factor * self.gwp_ch4
        if self.n2o_factor:
            co2e += self.n2o_factor * self.gwp_n2o
        return co2e
