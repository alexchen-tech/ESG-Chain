"""
PCF 批量計算路由
POST /ai/v1/pcf/batch       → 觸發 Celery 任務，回傳 celery_task_id
GET  /ai/v1/pcf/task-status/{celery_task_id} → 查詢 Celery 任務狀態
GET  /ai/v1/pcf/task-results/{task_id}       → 取回計算結果
"""
from fastapi import APIRouter, Depends
from pydantic import BaseModel
from typing import Optional
import uuid

from app.core.security import get_current_user
from app.tasks.scoring_tasks import calculate_pcf_batch

router = APIRouter(prefix="/pcf", tags=["pcf-batch"])


class PcfBatchRequest(BaseModel):
    task_id: str
    supplier_ids: list[str]
    product_ids: Optional[list[str]] = None
    callback_url: Optional[str] = None


@router.post("/batch")
async def trigger_pcf_batch(
    request: PcfBatchRequest,
    current_user: dict = Depends(get_current_user),
):
    """觸發 Celery 批量 PCF 計算"""
    task = calculate_pcf_batch.delay(
        supplier_id=request.supplier_ids[0] if len(request.supplier_ids) == 1 else "batch",
        reference_year=2024,
        entries=[],  # Phase 後續補充：從 trade_goods 拉取資料
        supplier_ids=request.supplier_ids,
        task_id=request.task_id,
        callback_url=request.callback_url,
    )
    return {
        "success": True,
        "celery_task_id": task.id,
        "status": "running",
    }


@router.get("/task-status/{celery_task_id}")
async def get_task_status(
    celery_task_id: str,
    current_user: dict = Depends(get_current_user),
):
    """查詢 Celery 任務執行狀態"""
    from app.tasks.celery_app import celery_app
    result = celery_app.AsyncResult(celery_task_id)

    status_map = {
        "PENDING": "pending",
        "STARTED": "running",
        "SUCCESS": "completed",
        "FAILURE": "failed",
        "RETRY": "running",
    }

    return {
        "status": status_map.get(result.state, "pending"),
        "progress": 100 if result.state == "SUCCESS" else 0,
        "result_count": None,
        "error_message": str(result.result) if result.state == "FAILURE" else None,
    }


@router.get("/task-results/{task_id}")
async def get_task_results(
    task_id: str,
    current_user: dict = Depends(get_current_user),
):
    """取回任務計算結果（從 pcf_records 彙總）"""
    # 從 PostgreSQL 取對應 supplier 的 pcf_records
    return {
        "success": True,
        "data": {
            "task_id": task_id,
            "result_co2e": 0.0,
            "scope3_breakdown": {},
            "calculated_at": None,
            "message": "結果尚在計算中或無資料",
        },
    }
