import uuid
from typing import Optional

from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel
from sqlalchemy import select, update, delete
from sqlalchemy.ext.asyncio import AsyncSession

from app.core.security import get_current_user
from app.db.postgresql import get_db
from app.models.scoring_model import ScoringModel
from app.schemas.scoring import SAQScoringRequest, SAQScoringResultResponse, ScoringJobResponse
from app.services.scoring_service import calculate_saq_score
from app.tasks.scoring_tasks import calculate_saq_score as async_score_task

router = APIRouter(prefix="/scoring", tags=["scoring"])


# ── Scoring Model CRUD ──────────────────────────────────────────────────────


class ScoringModelCreate(BaseModel):
    name: str
    sasb_industry_code: Optional[str] = None
    weight_e: float
    weight_s: float
    weight_g: float
    grade_a_threshold: float = 80.0
    grade_b_threshold: float = 60.0
    grade_c_threshold: float = 40.0
    grade_d_threshold: float = 20.0
    description: Optional[str] = None
    is_active: bool = True


class ScoringModelUpdate(BaseModel):
    name: Optional[str] = None
    weight_e: Optional[float] = None
    weight_s: Optional[float] = None
    weight_g: Optional[float] = None
    grade_a_threshold: Optional[float] = None
    grade_b_threshold: Optional[float] = None
    grade_c_threshold: Optional[float] = None
    grade_d_threshold: Optional[float] = None
    description: Optional[str] = None
    is_active: Optional[bool] = None


@router.get("/scoring-models")
async def list_scoring_models(
    industry_code: Optional[str] = None,
    is_active: Optional[bool] = None,
    db: AsyncSession = Depends(get_db),
    current_user: dict = Depends(get_current_user),
):
    stmt = select(ScoringModel)
    if industry_code is not None:
        stmt = stmt.where(ScoringModel.sasb_industry_code == industry_code)
    if is_active is not None:
        stmt = stmt.where(ScoringModel.is_active == is_active)
    result = await db.execute(stmt)
    models = result.scalars().all()
    return {"success": True, "data": [
        {
            "id": str(m.id), "name": m.name, "sasb_industry_code": m.sasb_industry_code,
            "weight_e": m.weight_e, "weight_s": m.weight_s, "weight_g": m.weight_g,
            "grade_a_threshold": m.grade_a_threshold, "grade_b_threshold": m.grade_b_threshold,
            "grade_c_threshold": m.grade_c_threshold, "grade_d_threshold": m.grade_d_threshold,
            "is_active": m.is_active, "description": m.description,
        }
        for m in models
    ]}


@router.post("/scoring-models", status_code=201)
async def create_scoring_model(
    payload: ScoringModelCreate,
    db: AsyncSession = Depends(get_db),
    current_user: dict = Depends(get_current_user),
):
    model = ScoringModel(
        id=uuid.uuid4(),
        name=payload.name,
        sasb_industry_code=payload.sasb_industry_code,
        weight_e=payload.weight_e,
        weight_s=payload.weight_s,
        weight_g=payload.weight_g,
        grade_a_threshold=payload.grade_a_threshold,
        grade_b_threshold=payload.grade_b_threshold,
        grade_c_threshold=payload.grade_c_threshold,
        grade_d_threshold=payload.grade_d_threshold,
        description=payload.description,
        is_active=payload.is_active,
    )
    db.add(model)
    await db.commit()
    await db.refresh(model)
    return {"success": True, "data": {"id": str(model.id), "name": model.name}}


@router.put("/scoring-models/{model_id}")
async def update_scoring_model(
    model_id: str,
    payload: ScoringModelUpdate,
    db: AsyncSession = Depends(get_db),
    current_user: dict = Depends(get_current_user),
):
    result = await db.execute(select(ScoringModel).where(ScoringModel.id == uuid.UUID(model_id)))
    model = result.scalar_one_or_none()
    if not model:
        raise HTTPException(status_code=404, detail="ScoringModel not found")

    update_data = payload.model_dump(exclude_none=True)
    for key, val in update_data.items():
        setattr(model, key, val)
    await db.commit()
    return {"success": True, "data": {"id": str(model.id)}}


@router.delete("/scoring-models/{model_id}")
async def delete_scoring_model(
    model_id: str,
    db: AsyncSession = Depends(get_db),
    current_user: dict = Depends(get_current_user),
):
    result = await db.execute(select(ScoringModel).where(ScoringModel.id == uuid.UUID(model_id)))
    model = result.scalar_one_or_none()
    if not model:
        raise HTTPException(status_code=404, detail="ScoringModel not found")
    await db.delete(model)
    await db.commit()
    return {"success": True, "message": "計分模型已刪除"}


# ── SAQ Scoring ─────────────────────────────────────────────────────────────


@router.post("/saq", response_model=SAQScoringResultResponse)
async def score_saq(
    request: SAQScoringRequest,
    current_user: dict = Depends(get_current_user),
) -> SAQScoringResultResponse:
    """SAQ 同步計分（domain-aware + industry-aware）"""
    return calculate_saq_score(
        saq_id=request.saq_id,
        responses=request.responses,
        scoring_framework=request.scoring_framework,
        sasb_industry_code=request.sasb_industry_code,
    )


@router.post("/saq/async", response_model=ScoringJobResponse)
async def score_saq_async(
    request: SAQScoringRequest,
    current_user: dict = Depends(get_current_user),
) -> ScoringJobResponse:
    """SAQ 非同步計分（Celery）— 計分完成後 callback Laravel"""
    task = async_score_task.delay(
        saq_id=request.saq_id,
        responses=[r.model_dump() for r in request.responses],
        sasb_industry_code=request.sasb_industry_code,
        callback_url=request.callback_url,
    )
    return ScoringJobResponse(
        job_id=task.id,
        status="queued",
        message="計分任務已提交，結果將透過 callback_url 回傳",
    )


class CelerySaqScoringRequest(BaseModel):
    saq_id: str
    responses: list
    scoring_framework: str | None = None
    sasb_industry_code: str | None = None
    callback_url: str | None = None
    series_pillar_weights: dict | None = None
    series_grade_thresholds: dict | None = None
    series_dim_weights: dict | None = None
    series_e4_objective_ratio: float | None = None
    # E4 混合計分欄位
    country_defense_score: float | None = None
    e4_objective_ratio: float = 0.40
    saq_completed: bool = False


@router.post("/celery/saq-scoring")
async def celery_trigger_saq_scoring(request: CelerySaqScoringRequest) -> dict:
    """由 Laravel Job 呼叫，將 SAQ 計分任務送入 Celery queue（不驗證 JWT）"""
    task = async_score_task.delay(
        saq_id=request.saq_id,
        responses=request.responses,
        scoring_framework=request.scoring_framework,
        sasb_industry_code=request.sasb_industry_code,
        callback_url=request.callback_url,
        series_pillar_weights=request.series_pillar_weights,
        series_grade_thresholds=request.series_grade_thresholds,
        series_dim_weights=request.series_dim_weights,
        series_e4_objective_ratio=request.series_e4_objective_ratio,
        country_defense_score=request.country_defense_score,
        e4_objective_ratio=request.e4_objective_ratio,
        saq_completed=request.saq_completed,
    )
    return {"task_id": task.id, "status": "queued"}


class CelerySaqScoringV2Request(BaseModel):
    saq_id: str
    responses: list
    active_modules: list[str] | None = None
    geo_risk: float = 3.0
    regulations: list[str] | None = None
    callback_url: str | None = None
    # 同時也觸發舊版計分（維持向下相容）
    scoring_framework: str | None = None
    sasb_industry_code: str | None = None
    series_pillar_weights: dict | None = None
    series_grade_thresholds: dict | None = None


@router.post("/celery/saq-scoring-v2")
async def celery_trigger_saq_scoring_v2(request: CelerySaqScoringV2Request) -> dict:
    """
    六維計分入口（T12 feature flag: SIX_DIM_SCORING=true 時啟用）。
    同時發送舊版計分任務（維持 score/grade/score_e/score_s/score_g 不斷線）。
    """
    import os
    from app.tasks.six_dim_scoring_tasks import score_saq_v2

    # 舊版計分永遠執行（保持向下相容）
    legacy_task = async_score_task.delay(
        saq_id=request.saq_id,
        responses=request.responses,
        scoring_framework=request.scoring_framework,
        sasb_industry_code=request.sasb_industry_code,
        callback_url=request.callback_url,
        series_pillar_weights=request.series_pillar_weights,
        series_grade_thresholds=request.series_grade_thresholds,
    )

    v2_task_id = None
    if os.getenv("SIX_DIM_SCORING", "false").lower() == "true":
        v2_task = score_saq_v2.delay(
            saq_id=request.saq_id,
            responses=request.responses,
            active_modules=request.active_modules,
            geo_risk=request.geo_risk,
            regulations=request.regulations,
            callback_url=request.callback_url,
        )
        v2_task_id = v2_task.id

    return {
        "task_id":       legacy_task.id,
        "v2_task_id":    v2_task_id,
        "six_dim_enabled": v2_task_id is not None,
        "status":        "queued",
    }
