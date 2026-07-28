from pydantic import BaseModel
from typing import Optional


class CompositionLineRequest(BaseModel):
    material_item_id: Optional[str] = None
    fiber_type: Optional[str] = None
    net_weight: Optional[float] = None  # 每 1 個 BOM 計量單位所含公斤數
    quantity: float
    pcr_percentage: Optional[float] = None
    pir_percentage: Optional[float] = None
    bio_based_percentage: Optional[float] = None
    recyclability_rating: Optional[str] = None  # high/medium/low/not_rated


class CompositionCalculateRequest(BaseModel):
    sales_product_id: str
    lines: list[CompositionLineRequest]


class CompositionBreakdownItem(BaseModel):
    fiber_type: str
    weight_kg: float
    percentage: float


class CompositionCalculateResponse(BaseModel):
    total_weight_kg: Optional[float]
    recycled_content_ratio: Optional[float]
    pcr_ratio: Optional[float]
    pir_ratio: Optional[float]
    bio_based_ratio: Optional[float]
    composition_breakdown: list[CompositionBreakdownItem]
    recyclability_summary: dict[str, float]
    incomplete_lines_count: int
    data_ready: bool
