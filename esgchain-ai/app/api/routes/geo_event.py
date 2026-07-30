"""
地緣事件 E4 批次重算路由
"""
from datetime import timezone, datetime

from fastapi import APIRouter, Depends
from pydantic import BaseModel

from app.core.security import verify_internal_service
from app.tasks.geo_event_tasks import recalculate_e4_batch

router = APIRouter(prefix="/geo-event", tags=["geo-event"])


class RecalculateE4Request(BaseModel):
    geo_event_id: str
    supplier_ids: list[str]
    callback_url: str
    country_defense_score_overrides: dict[str, float] | None = None


@router.post("/recalculate-e4", status_code=202, dependencies=[Depends(verify_internal_service)])
async def dispatch_recalculate_e4(request: RecalculateE4Request):
    """
    接收地緣事件批次 E4 重算請求，立即 enqueue Celery task，回傳 202。
    僅供 esgchain-api 內部 server-to-server 呼叫（X-Internal-Token 驗證），
    避免外部呼叫者任意指定 callback_url 造成 SSRF。
    """
    recalculate_e4_batch.delay(
        geo_event_id=request.geo_event_id,
        supplier_ids=request.supplier_ids,
        callback_url=request.callback_url,
        country_defense_score_overrides=request.country_defense_score_overrides or {},
    )
    return {
        "message": "recalculation queued",
        "geo_event_id": request.geo_event_id,
        "supplier_count": len(request.supplier_ids),
        "queued_at": datetime.now(timezone.utc).isoformat(),
    }
