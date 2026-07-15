from pydantic import BaseModel
from typing import Optional


class SAQResponseItemRequest(BaseModel):
    question_id: str
    source_question_id: Optional[str] = None
    weight: float = 1.0
    answer: Optional[str] = None
    answer_options: Optional[list[str]] = None
    sasb_topic: Optional[str] = None
    tag_slugs: list[str] = []
    # saq-scoring-quality: 計分 metadata
    scoring_direction: str = "positive"   # positive | negative
    scoring_type: Optional[str] = None    # ordered_asc | ordered_desc | custom | evidence_only | llm | None
    option_scores: Optional[dict[str, float]] = None  # custom 用，key=選項文字 value=0.0–1.0
    options: Optional[list[str]] = None   # 題目選項清單（ordered_* 自動線性化時使用）


class SAQScoringRequest(BaseModel):
    saq_id: str
    scoring_framework: Optional[str] = None  # ESG | ISO20400 | ISO26000 | Geo-Risk | Product-Compliance | None
    responses: list[SAQResponseItemRequest]
    sasb_industry_code: Optional[str] = None
    callback_url: Optional[str] = None


class QuestionScoreItem(BaseModel):
    project_question_id: str
    raw_score: float
    confidence: Optional[str] = None   # high | medium | low | None(evidence_only)


class SAQScoringResultResponse(BaseModel):
    saq_id: str
    scoring_framework: Optional[str] = None
    score_e: Optional[float] = None        # 僅 ESG 框架填入
    score_s: Optional[float] = None
    score_g: Optional[float] = None
    category_scores: dict[str, float] = {}  # 框架原生 L2 pillar 分數
    total_score: float
    grade: str        # A/B/C/D/E（SGS 五級）
    job_id: str
    topic_scores: dict[str, float] = {}
    effective_slugs_count: int = 0
    industry_code: Optional[str] = None
    scoring_model_id: Optional[str] = None
    question_scores: Optional[list[QuestionScoreItem]] = None
    # multi-framework 三軸輸出
    iso26000_total: Optional[float] = None
    iso26000_category_scores: dict[str, float] = {}
    iso20400_total: Optional[float] = None
    iso20400_category_scores: dict[str, float] = {}
    geo_risk_total: Optional[float] = None
    axis1_score: Optional[float] = None   # ESG 暴露 = 100 - iso26000_total
    axis2_score: Optional[float] = None   # 治理成熟度風險 = 100 - iso20400_total


class ScoringJobResponse(BaseModel):
    job_id: str
    status: str
    message: str


# 向後相容別名（舊 code 仍使用 SAQResponseItem / SAQScoringResult）
SAQResponseItem = SAQResponseItemRequest
SAQScoringResult = SAQScoringResultResponse
