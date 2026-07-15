"""LLM 文字題品質評分 Celery task（saq-scoring-quality）"""
import json
import logging
import os
from pathlib import Path

import httpx
from celery import shared_task

logger = logging.getLogger(__name__)

PROMPT_PATH = Path(__file__).parent.parent / "prompts" / "llm_text_scoring_v1.txt"

# 支援的 LLM provider（可透過環境變數切換）
LLM_PROVIDER = os.getenv("LLM_SCORING_PROVIDER", "anthropic")
LLM_MODEL = os.getenv("LLM_SCORING_MODEL", "claude-haiku-4-5-20251001")
ANTHROPIC_API_KEY = os.getenv("ANTHROPIC_API_KEY", "")


def _load_system_prompt() -> str:
    return PROMPT_PATH.read_text(encoding="utf-8")


def _call_llm(question_text: str, answer: str) -> dict:
    """呼叫 LLM，回傳 {"score": int, "reason": str}"""
    system_prompt = _load_system_prompt()
    user_message = f"題目：{question_text}\n\n供應商回答：{answer}"

    if LLM_PROVIDER == "anthropic":
        headers = {
            "x-api-key": ANTHROPIC_API_KEY,
            "anthropic-version": "2023-06-01",
            "content-type": "application/json",
        }
        payload = {
            "model": LLM_MODEL,
            "max_tokens": 200,
            "system": system_prompt,
            "messages": [{"role": "user", "content": user_message}],
        }
        resp = httpx.post(
            "https://api.anthropic.com/v1/messages",
            headers=headers,
            json=payload,
            timeout=30,
        )
        resp.raise_for_status()
        text = resp.json()["content"][0]["text"].strip()
    else:
        raise ValueError(f"Unsupported LLM provider: {LLM_PROVIDER}")

    result = json.loads(text)
    score = max(0, min(100, int(result["score"])))
    reason = str(result.get("reason", ""))[:200]
    return {"score": score, "reason": reason}


@shared_task(
    bind=True,
    max_retries=3,
    default_retry_delay=10,
    name="llm_scoring_tasks.score_text_response",
)
def score_text_response(
    self,
    saq_id: str,
    project_question_id: str,
    question_text: str,
    answer: str,
    callback_url: str,
) -> None:
    """對單一文字回答進行 LLM 品質評分，完成後 callback 至 Laravel。"""
    try:
        result = _call_llm(question_text, answer)
        llm_score = result["score"]
        llm_reason = result["reason"]
    except Exception as exc:
        logger.warning("LLM scoring failed for %s/%s: %s", saq_id, project_question_id, exc)
        try:
            raise self.retry(exc=exc)
        except self.MaxRetriesExceededError:
            logger.error("LLM scoring exhausted retries for %s/%s", saq_id, project_question_id)
            # 不 callback，讓 confidence 維持 low
            return

    try:
        resp = httpx.post(
            callback_url,
            json={
                "project_question_id": project_question_id,
                "llm_score": llm_score,
                "llm_score_reason": llm_reason,
            },
            timeout=15,
        )
        resp.raise_for_status()
        logger.info("LLM score callback success for %s/%s → %d", saq_id, project_question_id, llm_score)
    except Exception as exc:
        logger.error("LLM score callback failed for %s/%s: %s", saq_id, project_question_id, exc)
