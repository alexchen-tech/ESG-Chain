from fastapi import APIRouter
from app.tasks.sync_chemical_tasks import sync_chemical_database

router = APIRouter()


@router.post("/sync-chemicals")
async def trigger_chemical_sync(source: str = "echa"):
    """
    觸發化學品資料庫同步（內部端點，不掛 JWT）。
    """
    task = sync_chemical_database.delay(source=source)
    return {"task_id": task.id, "status": "queued", "source": source}
