"""
供應商 Impact（風險矩陣衝擊軸）四因子加權計分 Schema。

四因子子分數各 1–5，權重 tier .30 / spend .30 / 單一來源 .20 / 材料關鍵性 .20，
加權和四捨五入後 clamp 至 1–5。任一因子缺資料 → 該子分數以中性 3 代入。
"""
from typing import Optional

from pydantic import BaseModel, Field


class ImpactScoreRequest(BaseModel):
    # tier：ERP 供應鏈層級（1/2/3）；None 表未分級
    tier: Optional[int] = None
    # spend：年採購額；None 表無資料
    spend_amount: Optional[float] = None
    # spend 固定門檻 {"s5":..,"s4":..,"s3":..,"s2":..}（由 Laravel 從 system_settings 帶入）
    spend_thresholds: Optional[dict] = None
    # 單一來源依賴：True=存在僅此供應商的 BOM line；False=全多來源；None=無 BOM 資料
    single_source: Optional[bool] = None
    # 材料關鍵性：供應商涉及產品彙整的法規代碼清單；None=無產品/無資料，[]=有產品但無關鍵法規
    regulations: Optional[list[str]] = None


class ImpactSubScores(BaseModel):
    tier: int
    spend: int
    single_source: int = Field(..., alias="single_source")
    criticality: int

    model_config = {"populate_by_name": True}


class ImpactScoreResponse(BaseModel):
    impact_score: int
    sub_scores: ImpactSubScores
