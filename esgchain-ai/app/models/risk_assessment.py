import uuid
from datetime import datetime, timezone

from sqlalchemy import Column, DateTime, Float, Integer, String, Text
from sqlalchemy.dialects.postgresql import UUID

from app.db.postgresql import Base


class RiskAssessment(Base):
    __tablename__ = "risk_assessments"

    id = Column(UUID(as_uuid=True), primary_key=True, default=uuid.uuid4)
    supplier_id = Column(String(36), nullable=False, index=True)
    assessment_year = Column(Integer, nullable=False)
    # 各維度分數（0–100）
    score_environment = Column(Float, nullable=True)
    score_social = Column(Float, nullable=True)
    score_governance = Column(Float, nullable=True)
    score_country = Column(Float, nullable=True, comment="地緣政治/法規風險")
    score_supply_chain = Column(Float, nullable=True, comment="供應鏈集中度風險")
    # 加權總分與風險等級
    total_score = Column(Float, nullable=True)
    risk_level = Column(String(20), nullable=True, comment="low, medium, high, critical")
    celery_job_id = Column(String(100), nullable=True)
    notes = Column(Text, nullable=True)
    created_at = Column(DateTime(timezone=True), default=lambda: datetime.now(timezone.utc))


class RiskFactor(Base):
    """風險評估的各項細部指標"""
    __tablename__ = "risk_factors"

    id = Column(UUID(as_uuid=True), primary_key=True, default=uuid.uuid4)
    assessment_id = Column(UUID(as_uuid=True), nullable=False, index=True)
    dimension = Column(String(50), nullable=False, comment="environment/social/governance/country/supply_chain")
    factor_name = Column(String(100), nullable=False)
    score = Column(Float, nullable=False)
    weight = Column(Float, default=1.0)
    evidence = Column(Text, nullable=True)
    created_at = Column(DateTime(timezone=True), default=lambda: datetime.now(timezone.utc))
