"""
供應商 Impact 四因子加權計分（純函式，無 DB / Celery）。

公式：Impact = clamp(1..5, round(Σ wᵢ·sᵢ))
權重：tier 0.30、spend 0.30、單一來源 0.20、材料關鍵性 0.20
缺值規則：任一因子無資料 → 該子分數 = 3（中性）

語意分塊：業務衝擊 0.6（tier+spend）+ 後果嚴重度 0.4（單一來源+材料關鍵性）

四捨五入採 half-up（0.5 進位），非 Python 內建的 banker's rounding，
確保加權和 4.5 進位為 5（風險寧高勿低）。
"""
import math

from app.schemas.impact import (
    ImpactScoreRequest,
    ImpactScoreResponse,
    ImpactSubScores,
)

NEUTRAL = 3

WEIGHTS = {
    "tier": 0.30,
    "spend": 0.30,
    "single_source": 0.20,
    "criticality": 0.20,
}

# spend 門檻預設值（Laravel 未帶入時的 fallback；正式門檻存 system_settings）
DEFAULT_SPEND_THRESHOLDS = {"s5": 10_000_000, "s4": 3_000_000, "s3": 1_000_000, "s2": 300_000}

# 關鍵性子分數：命中 UFLPA/EUDR → 5、CBAM → 4
HIGH_CRITICALITY_REGS = {"UFLPA", "EUDR"}


def _tier_sub_score(tier: int | None) -> int:
    # T4（最上游基礎原料）與 T3 同為最低衝擊 1
    return {1: 5, 2: 3, 3: 1, 4: 1}.get(tier, NEUTRAL)


def _spend_sub_score(spend: float | None, thresholds: dict | None) -> int:
    if spend is None:
        return NEUTRAL
    t = thresholds or DEFAULT_SPEND_THRESHOLDS
    if spend >= t.get("s5", DEFAULT_SPEND_THRESHOLDS["s5"]):
        return 5
    if spend >= t.get("s4", DEFAULT_SPEND_THRESHOLDS["s4"]):
        return 4
    if spend >= t.get("s3", DEFAULT_SPEND_THRESHOLDS["s3"]):
        return 3
    if spend >= t.get("s2", DEFAULT_SPEND_THRESHOLDS["s2"]):
        return 2
    return 1


def _single_source_sub_score(single_source: bool | None) -> int:
    if single_source is None:
        return NEUTRAL  # 無 BOM 資料
    return 5 if single_source else 2


def _criticality_sub_score(regulations: list[str] | None) -> int:
    if regulations is None:
        return NEUTRAL  # 無產品/無資料
    regs = {str(r).upper() for r in regulations}
    if regs & HIGH_CRITICALITY_REGS:
        return 5
    if "CBAM" in regs:
        return 4
    return 2  # 有產品但無關鍵法規


def calculate_impact_score(request: ImpactScoreRequest) -> ImpactScoreResponse:
    sub = {
        "tier": _tier_sub_score(request.tier),
        "spend": _spend_sub_score(request.spend_amount, request.spend_thresholds),
        "single_source": _single_source_sub_score(request.single_source),
        "criticality": _criticality_sub_score(request.regulations),
    }

    weighted = sum(sub[k] * WEIGHTS[k] for k in WEIGHTS)
    impact = max(1, min(5, math.floor(weighted + 0.5)))

    return ImpactScoreResponse(
        impact_score=impact,
        sub_scores=ImpactSubScores(
            tier=sub["tier"],
            spend=sub["spend"],
            single_source=sub["single_source"],
            criticality=sub["criticality"],
        ),
    )
