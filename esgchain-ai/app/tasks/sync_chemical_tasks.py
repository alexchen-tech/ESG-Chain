import httpx
import os
from datetime import datetime, timezone
from app.tasks.celery_app import celery_app

LARAVEL_INTERNAL_URL = os.getenv("LARAVEL_INTERNAL_URL", "http://localhost:8081")
ECHA_API_URL = os.getenv("ECHA_API_URL", "")


@celery_app.task(name="chemical.sync_database", bind=True, max_retries=3)
def sync_chemical_database(self, source: str = "echa"):
    """
    從外部化學品資料庫（ECHA SVHC 清單）同步至 ESG-Chain。
    同步完成後呼叫 Laravel 內部端點 upsert chemicals。
    """
    try:
        chemicals = _fetch_echa_svhc()

        if not chemicals:
            return {"synced": 0, "source": source}

        result = _push_to_laravel(chemicals)
        return {"synced": len(chemicals), "source": source, "result": result}

    except Exception as exc:
        raise self.retry(exc=exc, countdown=60)


def _fetch_echa_svhc() -> list[dict]:
    """
    從 ECHA 拉取 SVHC 候選清單。
    若 ECHA_API_URL 未設定，回傳空清單（stub）。
    """
    if not ECHA_API_URL:
        return []

    with httpx.Client(timeout=60) as client:
        resp = client.get(f"{ECHA_API_URL}/svhc-list")
        resp.raise_for_status()
        return resp.json().get("substances", [])


def _push_to_laravel(chemicals: list[dict]) -> dict:
    synced_at = datetime.now(timezone.utc).isoformat()
    payload = {
        "chemicals": [
            {
                "cas_no": c.get("cas_no"),
                "substance_name": c.get("substance_name", ""),
                "iupac_name": c.get("iupac_name"),
                "regulated_lists": c.get("regulated_lists", {}),
                "restriction_notes": c.get("restriction_notes"),
                "svhc_date": c.get("svhc_date"),
                "synced_at": synced_at,
            }
            for c in chemicals
            if c.get("cas_no")
        ]
    }

    with httpx.Client(timeout=30) as client:
        resp = client.post(
            f"{LARAVEL_INTERNAL_URL}/api/v1/internal/chemicals/sync",
            json=payload,
        )
        resp.raise_for_status()
        return resp.json()
