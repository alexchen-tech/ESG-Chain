"""
六維評估計分 Celery tasks（T9/T10/T11/T12）

feature flag: SIX_DIM_SCORING=true 時啟用 v2 計分流程
"""
import logging
import os
import requests
from datetime import timezone, datetime

from app.tasks.celery_app import celery_app

logger = logging.getLogger(__name__)

_MAX_REGULATIONS = 6  # E6 壓力指數分母（已知最大法規數量）


def _is_six_dim_enabled() -> bool:
    return os.getenv("SIX_DIM_SCORING", "false").lower() == "true"


# ---------------------------------------------------------------------------
# T9: 問卷六維純計分
# ---------------------------------------------------------------------------

@celery_app.task(bind=True, name="scoring.score_saq_v2", max_retries=3)
def score_saq_v2(
    self,
    saq_id: str,
    responses: list[dict],
    active_modules: list[str] | None = None,
    geo_risk: float = 3.0,
    regulations: list[str] | None = None,
    callback_url: str | None = None,
) -> dict:
    """
    依 project_questions 快照（responses 中含 tags 欄位）計算六維純問卷分。
    每道題的 tags 格式：[{framework, pillar, weight}]
    """
    try:
        active = set(active_modules or ["E1", "E4"])

        # 累積每個維度的 (加權得分, 滿分基準)
        dim_totals: dict[str, float] = {f"E{i}": 0.0 for i in range(1, 7)}
        dim_bases:  dict[str, float] = {f"E{i}": 0.0 for i in range(1, 7)}

        for r in responses:
            tags = r.get("tags") or []
            if not isinstance(tags, list):
                continue

            raw_score = float(r.get("raw_score") or r.get("answer_score") or 0.0)

            for tag in tags:
                fw = tag.get("framework", "")
                w  = float(tag.get("weight") or 0.0)
                if fw not in active or w <= 0:
                    continue
                dim_totals[fw] += raw_score * w
                dim_bases[fw]  += w

        # 轉換為 0-100 分；未啟用或無題目的維度設為 None
        dim_scores: dict[str, float | None] = {}
        for fw in ["E1", "E2", "E3", "E4", "E5", "E6"]:
            if fw not in active or dim_bases[fw] == 0:
                dim_scores[fw] = None
            else:
                # raw_score 假設已是 0-1 比例；dim_totals / dim_bases 得到 0-1，×100
                dim_scores[fw] = round((dim_totals[fw] / dim_bases[fw]) * 100, 2)

        result = {
            "saq_id":    saq_id,
            "dim_e1":    dim_scores["E1"],
            "dim_e2":    dim_scores["E2"],
            "dim_e3":    dim_scores["E3"],
            "dim_e4":    dim_scores["E4"],
            "dim_e5":    dim_scores["E5"],
            "dim_e6":    dim_scores["E6"],
            "scored_at": datetime.now(timezone.utc).isoformat(),
        }

        # callback Laravel（在 E4/E6 混合計分完成前先傳一次初始值）
        if callback_url:
            _post_callback(callback_url, {**result, "assessment_version": "v2"})

        # 串接 E4/E6 混合計分
        if "E4" in active:
            compute_e4_score.delay(
                saq_id=saq_id,
                dim_e4_questionnaire=dim_scores["E4"],
                geo_risk=geo_risk,
                callback_url=callback_url,
            )
        if "E6" in active and dim_scores["E6"] is not None:
            compute_e6_score.delay(
                saq_id=saq_id,
                dim_e6_questionnaire=dim_scores["E6"],
                regulations=regulations or [],
                callback_url=callback_url,
            )

        return result

    except Exception as exc:
        raise self.retry(exc=exc, countdown=30)


# ---------------------------------------------------------------------------
# T10: E4 地緣風險混合計分
# ---------------------------------------------------------------------------

@celery_app.task(bind=True, name="scoring.compute_e4_score", max_retries=3)
def compute_e4_score(
    self,
    saq_id: str,
    dim_e4_questionnaire: float | None,
    geo_risk: float = 3.0,
    callback_url: str | None = None,
    country_defense_score: float | None = None,
    e4_objective_ratio: float = 0.40,
    saq_completed: bool = False,
) -> dict:
    """
    E4 三狀態機計分：
    1. 混合路徑：country_defense_score 存在 且 SAQ 已完成
       dim_e4 = country_defense_score × α + saq_geo_risk_score × (1−α)
    2. 純客觀快照：country_defense_score 存在 且 SAQ 未完成
       dim_e4 = country_defense_score
    3. 純 SAQ 路徑：country_defense_score 不存在（舊行為）
       dim_e4 = geo_risk_score × 0.4 + questionnaire_maturity × 0.6
    """
    try:
        if country_defense_score is not None:
            # 路徑 1 或 2
            if saq_completed and dim_e4_questionnaire is not None:
                alpha = float(e4_objective_ratio)
                dim_e4_mixed = round(
                    country_defense_score * alpha + dim_e4_questionnaire * (1.0 - alpha), 2
                )
            else:
                # 純客觀快照
                dim_e4_mixed = round(float(country_defense_score), 2)
        else:
            # 路徑 3：舊版 geo_risk 計算
            exposure_score = (float(geo_risk) / 5.0) * 100.0
            maturity_score = dim_e4_questionnaire if dim_e4_questionnaire is not None else 50.0
            dim_e4_mixed   = round(exposure_score * 0.4 + maturity_score * 0.6, 2)

        result = {"saq_id": saq_id, "dim_e4": dim_e4_mixed}
        if callback_url:
            _post_callback(callback_url, result)
        return result

    except Exception as exc:
        raise self.retry(exc=exc, countdown=30)


# ---------------------------------------------------------------------------
# T11: E6 產品合規混合計分
# ---------------------------------------------------------------------------

@celery_app.task(bind=True, name="scoring.compute_e6_score", max_retries=3)
def compute_e6_score(
    self,
    saq_id: str,
    dim_e6_questionnaire: float | None,
    regulations: list[str] | None = None,
    callback_url: str | None = None,
) -> dict:
    """
    E6 = readiness_score / max(regulation_pressure, 0.1)
    regulation_pressure = len(regulations) / _MAX_REGULATIONS
    """
    try:
        if not regulations:
            # 無適用法規 → E6 設為 None
            result = {"saq_id": saq_id, "dim_e6": None}
            if callback_url:
                _post_callback(callback_url, result)
            return result

        regulation_pressure = len(set(regulations)) / _MAX_REGULATIONS
        readiness           = dim_e6_questionnaire if dim_e6_questionnaire is not None else 0.0
        # clamp 至 0-100
        dim_e6_mixed = round(min(max(readiness / max(regulation_pressure, 0.1), 0), 100), 2)

        result = {"saq_id": saq_id, "dim_e6": dim_e6_mixed}
        if callback_url:
            _post_callback(callback_url, result)
        return result

    except Exception as exc:
        raise self.retry(exc=exc, countdown=30)


# ---------------------------------------------------------------------------
# 共用 callback helper
# ---------------------------------------------------------------------------

def _post_callback(callback_url: str, payload: dict) -> None:
    try:
        requests.post(callback_url, json=payload, timeout=10,
                      headers={"Content-Type": "application/json"})
    except Exception as e:
        logger.warning("six_dim callback failed: %s", e)
