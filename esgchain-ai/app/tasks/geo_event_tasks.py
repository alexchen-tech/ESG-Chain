"""
地緣事件 E4 批次重算 Celery task
"""
import logging
import os
import requests
from datetime import timezone, datetime

from app.tasks.celery_app import celery_app

logger = logging.getLogger(__name__)

_ALPHA = float(os.getenv("E4_OBJECTIVE_RATIO", "0.40"))


def _fetch_supplier_latest_saq(supplier_id: str) -> dict | None:
    """從 esgchain-api 取最新 SAQ 的 dim_e4_questionnaire。"""
    api_url = os.getenv("ESGCHAIN_API_URL", "http://esgchain-api:8081")
    try:
        resp = requests.get(
            f"{api_url}/api/v1/internal/suppliers/{supplier_id}/latest-saq-dims",
            timeout=10,
        )
        if resp.ok:
            return resp.json()
    except Exception as e:
        logger.warning("fetch supplier SAQ dims failed: supplier=%s err=%s", supplier_id, e)
    return None


def _compute_e4(dim_e4_questionnaire: float | None, country_defense_score: float | None) -> float:
    if country_defense_score is not None:
        if dim_e4_questionnaire is not None:
            return round(country_defense_score * _ALPHA + dim_e4_questionnaire * (1.0 - _ALPHA), 2)
        return round(float(country_defense_score), 2)
    if dim_e4_questionnaire is not None:
        return round(float(dim_e4_questionnaire), 2)
    return 50.0  # 無資料 fallback


@celery_app.task(bind=True, name="geo_event.recalculate_e4_batch", max_retries=3)
def recalculate_e4_batch(
    self,
    geo_event_id: str,
    supplier_ids: list[str],
    callback_url: str,
    country_defense_score_overrides: dict[str, float] | None = None,
) -> dict:
    """
    批次重算受地緣事件影響的供應商 E4 分數，完成後回調 Laravel。
    """
    try:
        results = []
        overrides = country_defense_score_overrides or {}

        for supplier_id in supplier_ids:
            saq_data = _fetch_supplier_latest_saq(supplier_id) or {}
            dim_e4_q = saq_data.get("dim_e4_questionnaire")
            country_defense = overrides.get(supplier_id)

            new_e4 = _compute_e4(dim_e4_q, country_defense)

            results.append({
                "supplier_id": supplier_id,
                "dim_e1": saq_data.get("dim_e1"),
                "dim_e2": saq_data.get("dim_e2"),
                "dim_e3": saq_data.get("dim_e3"),
                "dim_e4": new_e4,
                "dim_e5": saq_data.get("dim_e5"),
                "dim_e6": saq_data.get("dim_e6"),
            })

        # 回調 Laravel
        payload = {
            "geo_event_id": geo_event_id,
            "results": results,
            "completed_at": datetime.now(timezone.utc).isoformat(),
        }
        requests.post(callback_url, json=payload, timeout=30)

        return {"geo_event_id": geo_event_id, "processed": len(results)}

    except Exception as exc:
        logger.error("recalculate_e4_batch failed: geo_event=%s err=%s", geo_event_id, exc)
        raise self.retry(exc=exc, countdown=60)
