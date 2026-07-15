import uuid
from datetime import datetime, timezone

from sqlalchemy import Column, DateTime, Float, ForeignKey, Integer, String, Text
from sqlalchemy.dialects.postgresql import UUID

from app.db.postgresql import Base


class PCFRecord(Base):
    """PCF 碳足跡記錄（Scope 1/2/3 計算結果）"""
    __tablename__ = "pcf_records"

    id = Column(UUID(as_uuid=True), primary_key=True, default=uuid.uuid4)
    supplier_id = Column(String(36), nullable=False, index=True)
    reference_year = Column(Integer, nullable=False)
    scope = Column(Integer, nullable=False, comment="1, 2, or 3")
    category = Column(String(100), nullable=True, comment="Scope 3 subcategory")
    activity_description = Column(Text, nullable=True)
    quantity = Column(Float, nullable=False)
    unit = Column(String(50), nullable=False)
    emission_factor_id = Column(UUID(as_uuid=True), nullable=True)
    emission_factor_value = Column(Float, nullable=True, comment="kgCO2e per unit (snapshot)")
    total_kg_co2e = Column(Float, nullable=False, comment="計算結果：quantity × emission_factor")
    data_quality = Column(String(20), default="estimated", comment="measured, estimated, default")
    notes = Column(Text, nullable=True)
    # 業務側關聯（來自 esgchain-api）
    pcf_request_line_id = Column(String(36), nullable=True, index=True)
    bom_line_id = Column(String(36), nullable=True, index=True)
    quantity_unit = Column(String(20), nullable=True, comment="計量單位（kg / pcs / m²）")

    celery_job_id = Column(String(100), nullable=True)
    created_at = Column(DateTime(timezone=True), default=lambda: datetime.now(timezone.utc))
    updated_at = Column(DateTime(timezone=True), default=lambda: datetime.now(timezone.utc), onupdate=lambda: datetime.now(timezone.utc))
