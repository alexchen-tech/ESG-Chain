import uuid
from datetime import datetime, timezone

from sqlalchemy import Boolean, Column, DateTime, Float, Integer, String, Text
from sqlalchemy.dialects.postgresql import JSON, UUID

from app.db.postgresql import Base


class ScoringModel(Base):
    """SAQ 計分模型：E/S/G 加權配置 + SGS 五級閾值"""
    __tablename__ = "scoring_models"

    id = Column(UUID(as_uuid=True), primary_key=True, default=uuid.uuid4)
    name = Column(String(100), nullable=False)
    version = Column(String(20), default="1.0")
    is_active = Column(Boolean, default=True)
    # E/S/G 加權（合計必須 = 1.0）
    weight_e = Column(Float, nullable=False, comment="環境加權比例")
    weight_s = Column(Float, nullable=False, comment="社會加權比例")
    weight_g = Column(Float, nullable=False, comment="治理加權比例")
    # SGS 五級閾值（分數 >= 即為該級別）
    grade_a_threshold = Column(Float, default=80.0, comment="A 級最低分")
    grade_b_threshold = Column(Float, default=60.0, comment="B 級最低分")
    grade_c_threshold = Column(Float, default=40.0, comment="C 級最低分")
    grade_d_threshold = Column(Float, default=20.0, comment="D 級最低分")
    # E 級：< grade_d_threshold
    sasb_industry_code = Column(String(20), nullable=True, comment="SASB Industry code, e.g. EM-IS; null = universal default")
    description = Column(Text, nullable=True)
    created_at = Column(DateTime(timezone=True), default=lambda: datetime.now(timezone.utc))

    def calculate_grade(self, score: float) -> str:
        if score >= self.grade_a_threshold:
            return "A"
        elif score >= self.grade_b_threshold:
            return "B"
        elif score >= self.grade_c_threshold:
            return "C"
        elif score >= self.grade_d_threshold:
            return "D"
        return "E"
