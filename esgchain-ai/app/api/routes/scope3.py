from fastapi import APIRouter, Depends
from pydantic import BaseModel

from app.core.security import verify_internal_service
from app.tasks.scope3_push_tasks import scope3_push

router = APIRouter()


class Scope3PushRequest(BaseModel):
    report_id: str


@router.post("/scope3-push", dependencies=[Depends(verify_internal_service)])
async def trigger_scope3_push(request: Scope3PushRequest):
    """
    內部端點（以 X-Internal-Token 驗證，不掛使用者 JWT）：接收 report_id，
    dispatch Scope3 推送 Celery Task。
    """
    task = scope3_push.delay(request.report_id)
    return {"task_id": task.id, "report_id": request.report_id}
