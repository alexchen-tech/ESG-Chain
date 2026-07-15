import os

import requests

from app.tasks.celery_app import celery_app

LARAVEL_API_URL = os.environ.get("LARAVEL_API_URL", "http://esgchain-api/api/v1")
LARAVEL_API_TOKEN = os.environ.get("LARAVEL_API_TOKEN", "")


def _api_headers() -> dict:
    return {
        "Content-Type": "application/json",
        "Authorization": f"Bearer {LARAVEL_API_TOKEN}",
    }


@celery_app.task(bind=True, name="material_emission.estimate", max_retries=3)
def estimate_material_emission(
    self,
    material_item_id: str,
    supplier_id: str,
    hs_code: str,
    material_name: str | None = None,
) -> dict:
    """
    呼叫 FastAPI 估算端點，將結果寫回 Laravel（source=ai-estimated）
    在 BomLineSupplier 加入後若無碳排記錄時觸發
    """
    try:
        # 使用內網免驗證路由（/celery/ prefix），避免 Celery worker 持有 JWT
        ai_port = os.environ.get("APP_PORT", "8000")
        ai_url  = f"http://127.0.0.1:{ai_port}/ai/v1/celery/material-emission-estimate"

        estimate_resp = requests.post(
            ai_url,
            json={
                "hs_code":          hs_code,
                "supplier_id":      supplier_id,
                "material_item_id": material_item_id,
                "material_name":    material_name,
            },
            timeout=30,
        )
        estimate_resp.raise_for_status()
        estimate_data = estimate_resp.json()

        # 寫回 Laravel API
        write_resp = requests.post(
            f"{LARAVEL_API_URL}/material-items/{material_item_id}/emissions",
            headers=_api_headers(),
            json={
                "supplier_id":     supplier_id,
                "emissions_value": estimate_data["emissions_value"],
                "source":          "ai-estimated",
            },
            timeout=30,
        )
        write_resp.raise_for_status()
        emission_data = write_resp.json().get("data", {})

        return {
            "status":          "success",
            "emission_id":     emission_data.get("id"),
            "emissions_value": estimate_data["emissions_value"],
            "factor_source":   estimate_data.get("factor_source"),
        }

    except Exception as exc:
        raise self.retry(exc=exc, countdown=60)


@celery_app.task(bind=True, name="material_emission.recalc_pcf", max_retries=3)
def recalc_pcf_for_product(self, sales_product_id: str) -> dict:
    """
    觸發 Laravel PcfCalculationService 重算並寫入 pcf_snapshots
    在碳排記錄新增或 primary supplier 切換後觸發
    """
    try:
        resp = requests.post(
            f"{LARAVEL_API_URL}/sales-products/{sales_product_id}/pcf-recalc",
            headers=_api_headers(),
            timeout=60,
        )
        resp.raise_for_status()
        snapshot = resp.json().get("data", {})

        return {
            "status":          "success",
            "snapshot_id":     snapshot.get("id"),
            "total_pcf":       snapshot.get("total_pcf"),
            "iso14067_ready":  snapshot.get("iso14067_ready"),
        }

    except Exception as exc:
        raise self.retry(exc=exc, countdown=60)
