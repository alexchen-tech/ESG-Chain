from fastapi import APIRouter, Depends
from app.core.security import verify_internal_service
from app.tasks.sync_chemical_tasks import sync_chemical_database

router = APIRouter()


@router.post("/sync-chemicals", dependencies=[Depends(verify_internal_service)])
async def trigger_chemical_sync(source: str = "echa"):
    """
    觸發化學品資料庫同步（內部端點，以 X-Internal-Token 驗證，不掛使用者 JWT）。
    """
    task = sync_chemical_database.delay(source=source)
    return {"task_id": task.id, "status": "queued", "source": source}
