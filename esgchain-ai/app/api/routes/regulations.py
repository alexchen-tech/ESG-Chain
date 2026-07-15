"""
法規推論端點

依 HS 碼推斷銷售產品適用的貿易合規法規。
使用 HS 章節（2 碼前綴）對照法規矩陣，並考慮供應商來源國風險。
"""
from fastapi import APIRouter, Depends
from pydantic import BaseModel

from app.core.security import get_current_user

router = APIRouter(tags=["regulations"])


# HS 章節 → 候選法規
# key: HS 2碼前綴，value: list of regulation codes
HS_REGULATION_MAP: dict[str, list[str]] = {
    # 棉花與棉製品（高強迫勞動風險）
    "52": ["UFLPA", "REACH"],
    # 合成纖維（化學處理 REACH；循環材料 ESPR）
    "54": ["REACH", "ESPR"],
    "55": ["REACH", "ESPR"],
    # 針織布料
    "60": ["REACH"],
    # 技術紡織品（塗層布、防水布、複合材料）
    "59": ["REACH", "ESPR"],
    # 針織成衣（ESPR 數位產品護照試點類別）
    "61": ["REACH", "ESPR"],
    # 梭織成衣
    "62": ["REACH", "ESPR"],
    # 天然皮革（EUDR：牛隻放牧濫墾）
    "41": ["EUDR", "REACH"],
    # 木材、軟木（EUDR 核心類別）
    "44": ["EUDR"],
    # 橡膠（EUDR：天然橡膠林地轉換）
    "40": ["EUDR", "REACH"],
    # 可可、咖啡、大豆（EUDR）
    "18": ["EUDR"],
    "09": ["EUDR"],
    "12": ["EUDR"],
    # 棕油（EUDR）
    "15": ["EUDR"],
    # 金屬：鋼鐵（CBAM 境碳調整；衝突礦產 CMRT）
    "72": ["CMRT", "REACH"],
    "73": ["CMRT", "REACH"],
    # 鋁（CBAM）
    "76": ["REACH"],
    # 銅、錫（CMRT：衝突礦產 Sn）
    "74": ["CMRT", "REACH"],
    # 金屬雜件（拉鍊、扣件 → CMRT、REACH）
    "83": ["CMRT", "REACH"],
    # 滑件、拉鍊
    "96": ["CMRT", "REACH"],
    # 化學品、塑料（REACH SVHCs）
    "28": ["REACH"],
    "29": ["REACH"],
    "32": ["REACH"],
    "38": ["REACH"],
    "39": ["REACH"],
    # 電子元件（RoHS/REACH/CE）
    "85": ["REACH"],
    "84": ["REACH"],
}

# 高強迫勞動風險來源國（棉花類 → 加強 UFLPA 旗標）
UFLPA_RISK_COUNTRIES = {"CN", "MM", "PK", "UZ"}

# 最終衣物類型：成衣章節一律加 ESPR（EU 數位產品護照）
APPAREL_HS_PREFIXES = {"61", "62", "63"}


class RegulationInferRequest(BaseModel):
    hs_code: str
    product_name: str | None = None
    supplier_country: str | None = None  # ISO 3166-1 alpha-2


class RegulationInferResponse(BaseModel):
    hs_code: str
    inferred_regulations: list[str]
    confidence: str  # high / medium / low
    reasons: dict[str, str]  # regulation → 推論理由


def _infer(
    hs_code: str,
    product_name: str | None,
    supplier_country: str | None,
) -> RegulationInferResponse:
    hs2 = hs_code[:2] if hs_code else ""
    hs4 = hs_code[:4] if len(hs_code) >= 4 else hs2

    regulations: set[str] = set()
    reasons: dict[str, str] = {}

    # 1. HS 章節對照
    base_regs = HS_REGULATION_MAP.get(hs2, [])
    for reg in base_regs:
        regulations.add(reg)
        reasons[reg] = f"HS {hs2}xx 章節基本適用"

    # 2. 來源國風險加強（棉製品 + 高風險國 → 明確標記 UFLPA）
    if hs2 in ("52", "61", "62") and supplier_country in UFLPA_RISK_COUNTRIES:
        regulations.add("UFLPA")
        reasons["UFLPA"] = f"HS {hs2}xx + 來源國 {supplier_country}（強迫勞動高風險）"

    # 3. 成衣類一律標記 ESPR（EU 數位產品護照試點）
    if hs2 in APPAREL_HS_PREFIXES:
        regulations.add("ESPR")
        reasons["ESPR"] = f"HS {hs2}xx 成衣類，ESPR 數位產品護照試點範圍"

    # 4. 天然橡膠（4001）明確觸發 EUDR
    if hs4 == "4001":
        regulations.add("EUDR")
        reasons["EUDR"] = "HS 4001 天然橡膠，EUDR 林地轉換高風險原料"

    # 5. 產品名稱關鍵字輔助（模糊比對）
    if product_name:
        name_lower = product_name.lower()
        if any(k in name_lower for k in ["cotton", "棉", "有機棉"]):
            regulations.add("UFLPA")
            reasons.setdefault("UFLPA", "產品名稱含棉料關鍵字")
        if any(k in name_lower for k in ["wood", "木", "leather", "皮革", "rubber", "橡膠"]):
            regulations.add("EUDR")
            reasons.setdefault("EUDR", "產品名稱含 EUDR 高風險原料關鍵字")
        if any(k in name_lower for k in ["gold", "tin", "tantalum", "tungsten", "金屬", "拉鍊", "扣件"]):
            regulations.add("CMRT")
            reasons.setdefault("CMRT", "產品名稱含衝突礦產相關關鍵字")

    result_list = sorted(regulations)
    confidence = "high" if base_regs else ("medium" if result_list else "low")

    return RegulationInferResponse(
        hs_code=hs_code,
        inferred_regulations=result_list,
        confidence=confidence,
        reasons=reasons,
    )


@router.post("/regulations/infer", response_model=RegulationInferResponse)
async def infer_regulations(
    request: RegulationInferRequest,
    current_user: dict = Depends(get_current_user),
) -> RegulationInferResponse:
    """依 HS 碼 + 來源國推論適用法規（EUDR / UFLPA / REACH / ESPR / CMRT）"""
    return _infer(request.hs_code, request.product_name, request.supplier_country)


@router.post("/celery/regulations-infer", response_model=RegulationInferResponse)
async def celery_infer_regulations(
    request: RegulationInferRequest,
) -> RegulationInferResponse:
    """由 Laravel 內部呼叫（不驗 JWT），批量推論用"""
    return _infer(request.hs_code, request.product_name, request.supplier_country)
