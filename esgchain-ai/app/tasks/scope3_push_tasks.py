import os
import httpx
from datetime import datetime, timezone

from app.tasks.celery_app import celery_app
from app.core.config import settings


@celery_app.task(bind=True, name="scope3.push", max_retries=3)
def scope3_push(self, report_id: str):
    """
    核實後將活動資料推送至外部 Scope 3 計算服務。
    推送結果回寫至 Laravel /api/v1/internal/activity-reports/{id}/push-result。
    """
    push_log = {}
    try:
        # 外部 Scope 3 API（URL 由環境變數設定，預設為 mock）
        external_url = os.getenv("SCOPE3_API_URL", "")
        if not external_url:
            raise ValueError("SCOPE3_API_URL 未設定，跳過實際推送")

        resp = httpx.post(
            external_url,
            json={"report_id": report_id},
            timeout=30,
        )
        resp.raise_for_status()
        data = resp.json()
        push_log = {
            "status": "success",
            "external_record_id": data.get("id"),
            "pushed_at": datetime.now(timezone.utc).isoformat(),
        }
    except Exception as exc:
        push_log = {
            "status": "failed",
            "error": str(exc),
            "attempted_at": datetime.now(timezone.utc).isoformat(),
        }

    # 回寫 Laravel
    try:
        api_url = f"{settings.LARAVEL_INTERNAL_URL}/api/v1/internal/activity-reports/{report_id}/push-result"
        httpx.patch(
            api_url,
            json={"push_log": push_log},
            timeout=10,
            headers={"X-Internal-Token": settings.INTERNAL_SERVICE_TOKEN},
        )
    except Exception:
        pass

    return push_log
