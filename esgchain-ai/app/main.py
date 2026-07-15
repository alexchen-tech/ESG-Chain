from contextlib import asynccontextmanager

from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware

from app.api.routes import health, scoring, pcf, pcf_batch, risk, reports, material_emission, scope3, chemicals, regulations, path_risk, risk_suggestion, geo_event, impact
from app.core.config import settings
from app.db.postgresql import engine, Base


@asynccontextmanager
async def lifespan(app: FastAPI):
    # 啟動時自動建立資料表（開發環境用；正式環境用 alembic migrate）
    async with engine.begin() as conn:
        await conn.run_sync(Base.metadata.create_all)
    yield


app = FastAPI(
    title="ESG·Chain AI Service",
    description="計算引擎後端：SAQ 計分、PCF 碳足跡、風險評分",
    version="1.0.0",
    lifespan=lifespan,
    docs_url="/ai/docs" if settings.APP_DEBUG else None,
    redoc_url="/ai/redoc" if settings.APP_DEBUG else None,
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=settings.CORS_ALLOWED_ORIGINS,
    allow_credentials=True,
    allow_methods=["GET", "POST", "PUT", "PATCH", "DELETE", "OPTIONS"],
    allow_headers=["Authorization", "Content-Type", "Accept", "Cache-Control"],
)

app.include_router(health.router, prefix="/ai/v1")
app.include_router(scoring.router, prefix="/ai/v1")
app.include_router(pcf.router, prefix="/ai/v1")
app.include_router(pcf_batch.router, prefix="/ai/v1")
app.include_router(risk.router, prefix="/ai/v1")
app.include_router(reports.router, prefix="/ai/v1")
app.include_router(material_emission.router, prefix="/ai/v1")
app.include_router(scope3.router, prefix="/ai/v1/celery", tags=["celery-internal"])
app.include_router(chemicals.router, prefix="/ai/v1/celery", tags=["celery-internal"])
app.include_router(regulations.router, prefix="/ai/v1")
app.include_router(path_risk.router, prefix="/ai/v1")
app.include_router(risk_suggestion.router, prefix="/ai/v1")
app.include_router(geo_event.router, prefix="/ai/v1")
app.include_router(impact.router, prefix="/ai/v1")


@app.get("/")
async def root():
    return {"service": "esgchain-ai", "status": "running"}
