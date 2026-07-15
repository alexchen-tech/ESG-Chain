from datetime import datetime, timezone
from typing import Optional

import httpx
from fastapi import APIRouter, HTTPException
from pydantic import BaseModel

from app.core.config import settings
from app.core.security import get_current_user
from fastapi import Depends

router = APIRouter(prefix="/risk-suggestion", tags=["risk-suggestion"])

ANTHROPIC_API_URL = "https://api.anthropic.com/v1/messages"


# ── Request / Response Schemas ───────────────────────────────────────────────

class SixDimItemRequest(BaseModel):
    key: str
    label: str
    score: Optional[float] = None


class LatestRaRequest(BaseModel):
    assessed_at: Optional[str] = None
    source_type: Optional[str] = None
    assessment_version: Optional[str] = None
    six_dims: Optional[list[SixDimItemRequest]] = None


class LatestSaqRequest(BaseModel):
    grade: Optional[str] = None
    score: Optional[float] = None
    submitted_at: Optional[str] = None


class SupplierContextRequest(BaseModel):
    name: str
    country_code: Optional[str] = None
    tier: Optional[int] = None
    industry_name: Optional[str] = None


class RiskSuggestionRequest(BaseModel):
    supplier: SupplierContextRequest
    latest_ra: LatestRaRequest
    latest_saq: Optional[LatestSaqRequest] = None
    open_cap_count: int = 0


class RecommendationResponse(BaseModel):
    axis: str
    label: str
    action: str


class RiskSuggestionResponse(BaseModel):
    summary: str
    recommendations: list[RecommendationResponse]
    generated_at: str


# ── Helpers ───────────────────────────────────────────────────────────────────

def _score_level(score: Optional[float]) -> str:
    if score is None:
        return "未評估"
    if score >= 70:
        return "良好"
    if score >= 40:
        return "需關注"
    return "高風險"


def _build_prompt(req: RiskSuggestionRequest) -> str:
    s = req.supplier
    ra = req.latest_ra
    saq = req.latest_saq

    lines = [
        f"供應商：{s.name}（{s.country_code or '未知'}，Tier {s.tier or '未知'}，產業：{s.industry_name or '未知'}）",
        "",
        "最新六維風險評估（滿分100，<40為高風險，40-69需關注，≥70良好）：",
    ]

    scored_dims = []
    if ra.six_dims:
        for dim in ra.six_dims:
            score_str = f"{dim.score:.1f}" if dim.score is not None else "未評估"
            level = _score_level(dim.score)
            lines.append(f"  {dim.key}（{dim.label}）：{score_str} / {level}")
            if dim.score is not None:
                scored_dims.append(dim)

    if saq:
        score_str = f"{saq.score:.1f}" if saq.score is not None else "—"
        lines += [
            "",
            f"最新問卷：{saq.grade or '—'} 級，{score_str}分",
        ]

    if req.open_cap_count > 0:
        lines.append(f"目前有 {req.open_cap_count} 筆未關閉的矯正行動（CAP）")

    # 找出最弱的 3 個維度作為建議重點
    weak_dims = sorted(scored_dims, key=lambda d: d.score) if scored_dims else []
    focus_keys = [d.key for d in weak_dims[:3]] if weak_dims else ["E1", "E2", "E3"]

    lines += [
        "",
        "請以繁體中文輸出以下 JSON（不要有任何其他文字）：",
        '{',
        '  "summary": "不超過60字的整體風險摘要，需提及最弱維度",',
        '  "recommendations": [',
        f'    {{"axis": "{focus_keys[0] if len(focus_keys) > 0 else "E1"}", "label": "對應中文標籤", "action": "針對該維度的具體改善建議"}},',
        f'    {{"axis": "{focus_keys[1] if len(focus_keys) > 1 else "E2"}", "label": "對應中文標籤", "action": "針對該維度的具體改善建議"}},',
        f'    {{"axis": "{focus_keys[2] if len(focus_keys) > 2 else "E3"}", "label": "對應中文標籤", "action": "針對該維度的具體改善建議"}}',
        '  ]',
        '}',
        '僅輸出有評估資料的維度，無資料則省略。label 請使用中文（如「ESG整體」、「永續採購」等）。',
    ]
    return "\n".join(lines)


async def _call_claude(prompt: str) -> dict:
    if not settings.ANTHROPIC_API_KEY:
        raise HTTPException(status_code=503, detail="ANTHROPIC_API_KEY 未設定")

    async with httpx.AsyncClient(timeout=30) as client:
        resp = await client.post(
            ANTHROPIC_API_URL,
            headers={
                "x-api-key": settings.ANTHROPIC_API_KEY,
                "anthropic-version": "2023-06-01",
                "content-type": "application/json",
            },
            json={
                "model": "claude-haiku-4-5-20251001",
                "max_tokens": 512,
                "messages": [{"role": "user", "content": prompt}],
            },
        )

    if resp.status_code != 200:
        raise HTTPException(status_code=502, detail=f"Anthropic API 錯誤：{resp.text[:200]}")

    import json
    text = resp.json()["content"][0]["text"].strip()
    # 只取第一個 { ... } JSON block
    start = text.find("{")
    end = text.rfind("}") + 1
    return json.loads(text[start:end])


# ── Endpoint ─────────────────────────────────────────────────────────────────

@router.post("", response_model=RiskSuggestionResponse)
async def generate_risk_suggestion(
    request: RiskSuggestionRequest,
    current_user: dict = Depends(get_current_user),
) -> RiskSuggestionResponse:
    """
    根據供應商最新 RA 資料呼叫 Claude 產生繁體中文風險改善建議。
    結果由 esgchain-api 負責快取；此 endpoint 每次呼叫均觸發 AI。
    """
    prompt = _build_prompt(request)
    data = await _call_claude(prompt)

    return RiskSuggestionResponse(
        summary=data.get("summary", ""),
        recommendations=[
            RecommendationResponse(**r) for r in data.get("recommendations", [])
        ],
        generated_at=datetime.now(timezone.utc).isoformat(),
    )
