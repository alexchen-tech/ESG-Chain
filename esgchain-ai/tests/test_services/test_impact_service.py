"""供應商 Impact 四因子加權計分單元測試。"""
from app.schemas.impact import ImpactScoreRequest
from app.services.impact_service import calculate_impact_score

THRESHOLDS = {"s5": 10_000_000, "s4": 3_000_000, "s3": 1_000_000, "s2": 300_000}


def _score(**kwargs) -> int:
    return calculate_impact_score(ImpactScoreRequest(**kwargs)).impact_score


def test_all_factors_present():
    # tier=1→5, spend=3.5M→4, single_source→5, CBAM→4
    # 加權和 = 5*.3 + 4*.3 + 5*.2 + 4*.2 = 4.5 → round → 5（clamp）
    resp = calculate_impact_score(ImpactScoreRequest(
        tier=1, spend_amount=3_500_000, spend_thresholds=THRESHOLDS,
        single_source=True, regulations=["CBAM"],
    ))
    assert resp.sub_scores.tier == 5
    assert resp.sub_scores.spend == 4
    assert resp.sub_scores.single_source == 5
    assert resp.sub_scores.criticality == 4
    assert resp.impact_score == 5


def test_all_missing_yields_neutral_3():
    # 四因子皆缺 → 皆 3 → round(3) = 3
    resp = calculate_impact_score(ImpactScoreRequest(
        tier=None, spend_amount=None, single_source=None, regulations=None,
    ))
    assert resp.sub_scores.tier == 3
    assert resp.sub_scores.spend == 3
    assert resp.sub_scores.single_source == 3
    assert resp.sub_scores.criticality == 3
    assert resp.impact_score == 3


def test_tier_mapping():
    assert calculate_impact_score(ImpactScoreRequest(tier=1)).sub_scores.tier == 5
    assert calculate_impact_score(ImpactScoreRequest(tier=2)).sub_scores.tier == 3
    assert calculate_impact_score(ImpactScoreRequest(tier=3)).sub_scores.tier == 1
    assert calculate_impact_score(ImpactScoreRequest(tier=4)).sub_scores.tier == 1
    assert calculate_impact_score(ImpactScoreRequest(tier=9)).sub_scores.tier == 3


def test_spend_thresholds():
    def spend(v):
        return calculate_impact_score(
            ImpactScoreRequest(spend_amount=v, spend_thresholds=THRESHOLDS)
        ).sub_scores.spend
    assert spend(10_000_000) == 5
    assert spend(3_500_000) == 4
    assert spend(1_000_000) == 3
    assert spend(300_000) == 2
    assert spend(299_999) == 1


def test_single_source_factor():
    assert calculate_impact_score(ImpactScoreRequest(single_source=True)).sub_scores.single_source == 5
    assert calculate_impact_score(ImpactScoreRequest(single_source=False)).sub_scores.single_source == 2
    assert calculate_impact_score(ImpactScoreRequest(single_source=None)).sub_scores.single_source == 3


def test_criticality_factor():
    def crit(regs):
        return calculate_impact_score(ImpactScoreRequest(regulations=regs)).sub_scores.criticality
    assert crit(["UFLPA"]) == 5
    assert crit(["EUDR", "CBAM"]) == 5   # 高階優先
    assert crit(["CBAM"]) == 4
    assert crit(["REACH"]) == 2          # 有產品但無關鍵法規
    assert crit([]) == 2                 # 有產品、空法規
    assert crit(None) == 3               # 無產品/無資料


def test_clamp_bounds():
    # 全因子最高 → 5（不超過上限）
    high = _score(tier=1, spend_amount=99_000_000, spend_thresholds=THRESHOLDS,
                  single_source=True, regulations=["UFLPA"])
    assert high == 5
    # 全因子最低 → 1（不低於下限）
    low = _score(tier=3, spend_amount=0, spend_thresholds=THRESHOLDS,
                 single_source=False, regulations=[])
    # 加權和 = 1*.3 + 1*.3 + 2*.2 + 2*.2 = 1.4 → round → 1
    assert low == 1


def test_rounding_boundary():
    # tier=2(3), spend=s2(2), single=False(2), crit=CBAM(4): 3*.3+2*.3+2*.2+4*.2=2.7→3
    assert _score(tier=2, spend_amount=300_000, spend_thresholds=THRESHOLDS,
                  single_source=False, regulations=["CBAM"]) == 3


def test_half_up_rounding():
    # 加權和恰為 4.5（tier5,spend4,single5,crit4）→ half-up 進位為 5
    assert _score(tier=1, spend_amount=3_500_000, spend_thresholds=THRESHOLDS,
                  single_source=True, regulations=["CBAM"]) == 5
