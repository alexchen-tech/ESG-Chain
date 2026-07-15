from datetime import datetime, timezone
from pathlib import Path
from typing import Optional

import jwt
from fastapi import Depends, HTTPException, status
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
