from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    model_config = SettingsConfigDict(
        env_file=".env",
        case_sensitive=True,
        extra="ignore",
    )

    APP_ENV: str = "development"
    APP_DEBUG: bool = True
    APP_HOST: str = "0.0.0.0"
    APP_PORT: int = 8000

    DATABASE_URL: str = "postgresql+asyncpg://esgchain:esgchain_secret@postgres:5432/esgchain_ai"
    DATABASE_POOL_SIZE: int = 10
    DATABASE_MAX_OVERFLOW: int = 20

    REDIS_URL: str = "redis://redis:6379/0"
    CELERY_BROKER_URL: str = "redis://redis:6379/1"
    CELERY_RESULT_BACKEND: str = "redis://redis:6379/2"

    JWT_PUBLIC_KEY_PATH: str = "/app/keys/jwt-public.pem"
    JWT_ACCESS_TOKEN_TTL: int = 3600

    CORS_ALLOWED_ORIGINS: list[str] = ["http://localhost:5173"]

    ANTHROPIC_API_KEY: str = ""

    # esgchain-api（Laravel）內部回呼位址，供 Celery 任務回寫結果使用
    LARAVEL_INTERNAL_URL: str = "http://esgchain-api:8080"

    # esgchain-api → esgchain-ai server-to-server 呼叫共用密鑰（純內部呼叫、無使用者 JWT 的端點用此驗證）
    INTERNAL_SERVICE_TOKEN: str = ""


settings = Settings()
