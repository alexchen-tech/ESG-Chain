from typing import Optional

from pydantic import BaseModel


class ProductPcfLineRequest(BaseModel):
    """單一 BOM 行的原始資料（由 esgchain-api 蒐集），對應型態 A（material_item_id）
    或型態 B（child_sales_product_id）。欄位需與 PcfSnapshot.lines JSON 結構一致，
    才能讓 esgchain-api 把 AI 回傳的 line 直接落庫。"""

    bom_line_id: str
    line_type: str  # 'material'（型態 A）| 'component'（型態 B，子銷售產品）
    material_item_id: Optional[str] = None
    child_sales_product_id: Optional[str] = None
    material_name: Optional[str] = None
    hs_code: Optional[str] = None
    supplier_id: Optional[str] = None
    supplier_name: Optional[str] = None
    quantity: float
    unit: str = "件"
    emission_per_unit: Optional[float] = None
    emission_source: Optional[str] = None
    is_estimated: bool = False
    is_flagged: bool = False
    reported_period: Optional[str] = None
    # PCR 計算用（僅型態 A 且來自 MaterialItem 時有值）
    net_weight: Optional[float] = None
    pcr_percentage: Optional[float] = None


class ProductPcfCalculateRequest(BaseModel):
    sales_product_id: str
    functional_unit: str = "件"
    lines: list[ProductPcfLineRequest]


class ProductPcfLineResult(ProductPcfLineRequest):
    """回傳 line：原樣回顯 request 全部欄位 + 新增計算欄位，供 esgchain-api 直接寫入
    PcfSnapshot.lines。"""

    subtotal: Optional[float] = None
    data_quality: str = "missing"


class ProductPcfCalculateResponse(BaseModel):
    sales_product_id: str
    functional_unit: str
    total_pcf: Optional[float]
    iso14067_ready: bool
    lines: list[ProductPcfLineResult]
    pcr_ratio: Optional[float]
    pcr_incomplete_lines: int
