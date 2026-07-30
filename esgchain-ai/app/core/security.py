from datetime import datetime, timezone
from pathlib import Path
from typing import Optional

import jwt
from fastapi import Depends, Header, HTTPException, status
from fastapi.security import HTTPAuthorizationCredentials, HTTPBearer

from app.core.config import settings

bearer_scheme = HTTPBearer()

_public_key: Optional[str] = None


def get_public_key() -> str:
    global _public_key
    if _public_key is None:
        key_path = Path(settings.JWT_PUBLIC_KEY_PATH)
        if key_path.exists():
            _public_key = key_path.read_text()
        else:
            raise RuntimeError(f"JWT 公鑰檔案不存在：{settings.JWT_PUBLIC_KEY_PATH}")
    return _public_key


def decode_token(token: str) -> dict:
    try:
        payload = jwt.decode(
            token,
            get_public_key(),
            algorithms=["RS256"],
            options={"verify_exp": True},
        )
        return payload
    except jwt.ExpiredSignatureError:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail={"error_code": "TOKEN_EXPIRED", "message": "Token 已過期"},
        )
    except jwt.InvalidTokenError:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail={"error_code": "UNAUTHORIZED", "message": "Token 無效"},
        )


def get_current_user(
    credentials: HTTPAuthorizationCredentials = Depends(bearer_scheme),
) -> dict:
    return decode_token(credentials.credentials)


def verify_internal_service(
    x_internal_token: Optional[str] = Header(default=None, alias="X-Internal-Token"),
) -> None:
    """
    純 server-to-server 內部端點守衛（無使用者 JWT，例如 esgchain-api 服務對服務呼叫、
    Celery 內部觸發）。比對 esgchain-api 與 esgchain-ai 共用的 INTERNAL_SERVICE_TOKEN。
    """
    expected = settings.INTERNAL_SERVICE_TOKEN
    if not expected:
        raise HTTPException(
            status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
            detail={"error_code": "INTERNAL_TOKEN_NOT_CONFIGURED", "message": "內部服務金鑰未設定"},
        )
    if not x_internal_token or x_internal_token != expected:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail={"error_code": "UNAUTHORIZED", "message": "內部服務驗證失敗"},
        )


def require_roles(*roles: str):
    """角色守衛 Dependency"""
    def checker(current_user: dict = Depends(get_current_user)) -> dict:
        user_roles = current_user.get("roles", [])
        if not any(role in user_roles for role in roles):
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail={"error_code": "FORBIDDEN", "message": "權限不足"},
            )
        return current_user
    return checker
