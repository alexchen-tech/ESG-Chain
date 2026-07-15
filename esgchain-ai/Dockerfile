FROM python:3.12-slim

RUN pip install uv

WORKDIR /app

COPY pyproject.toml ./
RUN uv pip install --system --no-cache \
    fastapi uvicorn gunicorn "sqlalchemy[asyncio]" asyncpg alembic \
    "pydantic>=2.6.0" pydantic-settings \
    "celery[redis]" redis "PyJWT[crypto]" cryptography \
    httpx python-multipart slowapi requests

COPY . .

EXPOSE 8000
ENV PYTHONPATH=/app
CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8000"]
