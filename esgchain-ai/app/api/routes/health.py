from fastapi import APIRouter, Depends

from app.core.security import get_current_user

router = APIRouter(prefix="/health", tags=["health"])


@router.get("")
async def health_check():
    """公開健康檢查端點"""
    return {"status": "ok", "service": "esgchain-ai"}


@router.get("/auth")
async def auth_health_check(current_user: dict = Depends(get_current_user)):
    """需要 JWT 認證的健康檢查（用於驗證跨服務 Token）"""
    return {
        "status": "ok",
        "service": "esgchain-ai",
        "user_id": current_user.get("sub"),
        "roles": current_user.get("roles", []),
    }
