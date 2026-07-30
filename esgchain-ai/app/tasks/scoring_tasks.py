import logging
import os
import requests

_INTERNAL_TOKEN_HEADER = {"X-Internal-Token": os.getenv("INTERNAL_SERVICE_TOKEN", "")}

from app.tasks.celery_app import celery_app

logger = logging.getLogger(__name__)


def _dispatch_llm_scoring(saq_id: str, responses: list[dict], callback_url: str | None) -> None:
    """對 scoring_type=llm 的題目批次派發 LLM 評分任務。"""
    if not callback_url:
        return
    llm_responses = [r for r in responses if r.get("scoring_type") == "llm"]
    if not llm_responses:
        return

    from app.tasks.llm_scoring_tasks import score_text_response

    # callback_url 形如 .../saqs/{saq_id}/score-callback，替換為 llm-score-callback
    llm_callback_base = callback_url.replace("/score-callback", "/llm-score-callback")

    for r in llm_responses:
        answer = r.get("answer") or ""
        if not answer.strip():
            continue
        score_text_response.delay(
            saq_id=saq_id,
            project_question_id=r["question_id"],
            question_text=r.get("question_text", ""),
            answer=answer,
            callback_url=llm_callback_base,
        )
        logger.info("Dispatched LLM scoring task for %s/%s", saq_id, r["question_id"])


@celery_app.task(bind=True, name="scoring.calculate_saq_score", max_retries=3)
def calculate_saq_score(
    self,
    saq_id: str,
    responses: list[dict],
    scoring_framework: str | None = None,
    sasb_industry_code: str | None = None,
    callback_url: str | None = None,
    series_pillar_weights: dict | None = None,
    series_grade_thresholds: dict | None = None,
    series_dim_weights: dict | None = None,
    series_e4_objective_ratio: float | None = None,
    country_defense_score: float | None = None,
    e4_objective_ratio: float = 0.40,
    saq_completed: bool = False,
) -> dict:
    """SAQ 計分（framework-native pillar scoring + SGS 五級評核）+ callback Laravel"""
    try:
        from app.schemas.scoring import SAQResponseItemRequest
        from app.services.scoring_service import calculate_saq_score as _score

        response_objs = [SAQResponseItemRequest(**r) for r in responses]
        result = _score(
            saq_id=saq_id,
            responses=response_objs,
            scoring_framework=scoring_framework,
            sasb_industry_code=sasb_industry_code,
            series_pillar_weights=series_pillar_weights,
            series_grade_thresholds=series_grade_thresholds,
        )
        result.job_id = self.request.id

        # 計分完成後 callback Laravel（含逐題 raw_score）
        if callback_url:
            payload = {
                "score": result.total_score,
                "grade": result.grade,
                "job_id": self.request.id,
                "score_e": result.score_e,
                "score_s": result.score_s,
                "score_g": result.score_g,
                "scoring_model_id": result.scoring_model_id,
            }
            if result.question_scores:
                payload["question_scores"] = [
                    {
                        "project_question_id": qs.project_question_id,
                        "raw_score": qs.raw_score,
                        "confidence": qs.confidence,
                    }
                    for qs in result.question_scores
                ]
            requests.post(
                callback_url,
                json=payload,
                timeout=10,
                headers={"Content-Type": "application/json", **_INTERNAL_TOKEN_HEADER},
            )

        # E4 三路徑混合計分（有 country_defense_score 時覆寫 dim_e4）
        if country_defense_score is not None or saq_completed:
            from app.tasks.six_dim_scoring_tasks import compute_e4_score
            compute_e4_score.delay(
                saq_id=saq_id,
                dim_e4_questionnaire=result.geo_risk_total,
                geo_risk=3.0,
                callback_url=callback_url,
                country_defense_score=country_defense_score,
                e4_objective_ratio=e4_objective_ratio,
                saq_completed=saq_completed,
            )

        # saq-scoring-quality: 對 llm 類型的題目派發 LLM 評分任務
        _dispatch_llm_scoring(saq_id, responses, callback_url)

        return result.model_dump()

    except Exception as exc:
        raise self.retry(exc=exc, countdown=30)


@celery_app.task(bind=True, name="scoring.calculate_pcf", max_retries=3)
def calculate_pcf_batch(
    self,
    supplier_id: str,
    reference_year: int,
    entries: list[dict],
    supplier_ids: list[str] | None = None,
    task_id: str | None = None,
    callback_url: str | None = None,
) -> dict:
    """PCF 批量計算（Scope 1/2/3），支援 supplier_ids + callback"""
    try:
        from app.services.emission_factor_service import get_default_factor

        results = []
        totals = {1: 0.0, 2: 0.0, 3: 0.0}

        for entry in entries:
            category = entry.get("category") or "diesel"
            ef_value = get_default_factor(category)
            total = entry["quantity"] * ef_value
            totals[entry["scope"]] = totals.get(entry["scope"], 0.0) + total
            results.append({
                "scope": entry["scope"],
                "category": category,
                "total_kg_co2e": round(total, 4),
            })

        result = {
            "supplier_id": supplier_id,
            "reference_year": reference_year,
            "scope1_total": round(totals[1], 4),
            "scope2_total": round(totals[2], 4),
            "scope3_total": round(totals[3], 4),
            "grand_total": round(sum(totals.values()), 4),
            "entries": results,
            "job_id": self.request.id,
            "result_count": len(results),
        }

        # Callback Laravel 更新 pcf_tasks 狀態
        if callback_url:
            try:
                requests.post(
                    callback_url,
                    json={"status": "completed", "result_count": len(results)},
                    timeout=10,
                )
            except Exception:
                pass

        return result

    except Exception as exc:
        if callback_url:
            try:
                requests.post(
                    callback_url,
                    json={"status": "failed", "error_message": str(exc)},
                    timeout=10,
                )
            except Exception:
                pass
        raise self.retry(exc=exc, countdown=30)
