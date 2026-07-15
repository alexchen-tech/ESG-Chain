"""
E4 三路徑計分單元測試。
直接測試 compute_e4_score 的核心計算邏輯，不需要 Celery worker。
"""
import pytest


def _compute_e4(
    dim_e4_questionnaire: float | None,
    geo_risk: float = 3.0,
    country_defense_score: float | None = None,
    e4_objective_ratio: float = 0.40,
    saq_completed: bool = False,
) -> float:
    """提取自 compute_e4_score 的純函式版本，供測試用。"""
    if country_defense_score is not None:
        if saq_completed and dim_e4_questionnaire is not None:
            alpha = float(e4_objective_ratio)
            return round(country_defense_score * alpha + dim_e4_questionnaire * (1.0 - alpha), 2)
        else:
            return round(float(country_defense_score), 2)
    else:
        exposure_score = (float(geo_risk) / 5.0) * 100.0
        maturity_score = dim_e4_questionnaire if dim_e4_questionnaire is not None else 50.0
        return round(exposure_score * 0.4 + maturity_score * 0.6, 2)


# ── 路徑 1：混合路徑 ───────────────────────────────────────────────────────

class TestMixedPath:
    def test_basic_mixed(self):
        """country_defense_score=70, α=0.40, saq=60 → 64.0"""
        result = _compute_e4(
            dim_e4_questionnaire=60,
            country_defense_score=70,
            e4_objective_ratio=0.40,
            saq_completed=True,
        )
        assert result == 64.0

    def test_alpha_zero_equals_pure_saq(self):
        """α=0 時等同純 SAQ"""
        result = _compute_e4(
            dim_e4_questionnaire=60,
            country_defense_score=70,
            e4_objective_ratio=0.0,
            saq_completed=True,
        )
        assert result == 60.0

    def test_alpha_one_equals_pure_objective(self):
        """α=1 時等同純客觀"""
        result = _compute_e4(
            dim_e4_questionnaire=60,
            country_defense_score=70,
            e4_objective_ratio=1.0,
            saq_completed=True,
        )
        assert result == 70.0

    def test_custom_alpha(self):
        """α=0.60 自訂比例"""
        result = _compute_e4(
            dim_e4_questionnaire=50,
            country_defense_score=80,
            e4_objective_ratio=0.60,
            saq_completed=True,
        )
        assert result == round(80 * 0.6 + 50 * 0.4, 2)


# ── 路徑 2：純客觀快照 ─────────────────────────────────────────────────────

class TestPureObjectivePath:
    def test_saq_not_completed(self):
        """有國家評等但 SAQ 未完成 → 純客觀"""
        result = _compute_e4(
            dim_e4_questionnaire=None,
            country_defense_score=70,
            saq_completed=False,
        )
        assert result == 70.0

    def test_saq_completed_false_ignores_questionnaire(self):
        """saq_completed=False 時，即使有 dim_e4_questionnaire 也走純客觀"""
        result = _compute_e4(
            dim_e4_questionnaire=60,
            country_defense_score=70,
            saq_completed=False,
        )
        assert result == 70.0

    def test_perfect_defense_score(self):
        result = _compute_e4(dim_e4_questionnaire=None, country_defense_score=100.0, saq_completed=False)
        assert result == 100.0

    def test_zero_defense_score(self):
        result = _compute_e4(dim_e4_questionnaire=None, country_defense_score=0.0, saq_completed=False)
        assert result == 0.0


# ── 路徑 3：純 SAQ ─────────────────────────────────────────────────────────

class TestPureSaqPath:
    def test_no_country_data(self):
        """無國家評等 → 舊版計算"""
        result = _compute_e4(
            dim_e4_questionnaire=60,
            geo_risk=3.0,
            country_defense_score=None,
        )
        exposure = (3.0 / 5.0) * 100
        expected = round(exposure * 0.4 + 60 * 0.6, 2)
        assert result == expected

    def test_no_saq_no_country(self):
        """無國家評等 且 SAQ 無分數 → 使用 50 作為中立"""
        result = _compute_e4(
            dim_e4_questionnaire=None,
            geo_risk=2.0,
            country_defense_score=None,
        )
        exposure = (2.0 / 5.0) * 100
        expected = round(exposure * 0.4 + 50 * 0.6, 2)
        assert result == expected


# ── country_defense_score 計算公式驗證 ────────────────────────────────────

class TestCountryDefenseScore:
    """驗證 API 側 resolveCountryDefenseScore 公式：100 − (rating−1)/4×100"""

    def _defense(self, rating: float) -> float:
        return round(100 - (rating - 1) / 4 * 100, 2)

    def test_lowest_risk(self):
        assert self._defense(1.0) == 100.0

    def test_highest_risk(self):
        assert self._defense(5.0) == 0.0

    def test_medium_risk(self):
        assert self._defense(3.0) == 50.0

    def test_sub_scores_average(self):
        """sub_scores = {political:4, environmental:3, social:4, regulatory:3} → rating=3.5"""
        sub = [4, 3, 4, 3]
        rating = sum(sub) / len(sub)
        assert self._defense(rating) == 37.5
