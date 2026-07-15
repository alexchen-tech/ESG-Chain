"""
供應商風險評分模型

維度：E（環境）、S（社會）、G（治理）、Country（地緣）、SupplyChain（集中度）
加權公式：total = E×0.25 + S×0.20 + G×0.25 + Country×0.15 + SupplyChain×0.15
風險等級：critical(<20), high(<40), medium(<60), low(>=60)
"""
import uuid
from datetime import datetime, timezone

from sqlalchemy.ext.asyncio import AsyncSession

from app.models.risk_assessment import RiskAssessment, RiskFactor
from app.schemas.risk import RiskAssessmentRequest, RiskAssessmentResponse

DIMENSION_WEIGHTS = {
    "environment": 0.25,
    "social": 0.20,
    "governance": 0.25,
    "country": 0.15,
    "supply_chain": 0.15,
}

RISK_THRESHOLDS = [
    (60.0, "low"),
    (40.0, "medium"),
    (20.0, "high"),
    (0.0, "critical"),
]


def _group_dimension_scores(factors: list) -> dict[str, float]:
    """依維度分組，計算加權平均"""
    groups: dict[str, list[tuple[float, float]]] = {}
    for f in factors:
        dim = f.dimension.lower()
        if dim not in groups:
            groups[dim] = []
        groups[dim].append((f.score, f.weight))

    result = {}
    for dim, items in groups.items():
        total_w = sum(w for _, w in items)
        if total_w > 0:
            result[dim] = sum(s * w for s, w in items) / total_w
        else:
            result[dim] = 0.0
    return result


def _calculate_total(dim_scores: dict[str, float]) -> float:
    total = 0.0
    for dim, weight in DIMENSION_WEIGHTS.items():
        total += dim_scores.get(dim, 0.0) * weight
    return round(total, 2)


def _get_risk_level(score: float) -> str:
    for threshold, level in RISK_THRESHOLDS:
        if score >= threshold:
            return level
    return "critical"


async def assess_risk(
    db: AsyncSession,
    request: RiskAssessmentRequest,
) -> RiskAssessmentResponse:
    """建立風險評估記錄並計算總分"""
    assessment_id = uuid.uuid4()

    # 寫入細項指標
    factor_records = []
    for f in request.factors:
        factor_records.append(RiskFactor(
            id=uuid.uuid4(),
            assessment_id=assessment_id,
            dimension=f.dimension,
            factor_name=f.factor_name,
            score=f.score,
            weight=f.weight,
            evidence=f.evidence,
            created_at=datetime.now(timezone.utc),
        ))
    db.add_all(factor_records)

    # 計算各維度平均分
    dim_scores = _group_dimension_scores(request.factors)
    total = _calculate_total(dim_scores)
    risk_level = _get_risk_level(total)

    assessment = RiskAssessment(
        id=assessment_id,
        supplier_id=request.supplier_id,
        assessment_year=request.assessment_year,
        score_environment=dim_scores.get("environment"),
        score_social=dim_scores.get("social"),
        score_governance=dim_scores.get("governance"),
        score_country=dim_scores.get("country"),
        score_supply_chain=dim_scores.get("supply_chain"),
        total_score=total,
        risk_level=risk_level,
        notes=request.notes,
        created_at=datetime.now(timezone.utc),
    )
    db.add(assessment)
    await db.commit()

    return RiskAssessmentResponse(
        id=str(assessment_id),
        supplier_id=request.supplier_id,
        assessment_year=request.assessment_year,
        score_environment=dim_scores.get("environment"),
        score_social=dim_scores.get("social"),
        score_governance=dim_scores.get("governance"),
        score_country=dim_scores.get("country"),
        score_supply_chain=dim_scores.get("supply_chain"),
        total_score=total,
        risk_level=risk_level,
        job_id=None,
    )
