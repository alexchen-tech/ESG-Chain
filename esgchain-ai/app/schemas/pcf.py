from pydantic import BaseModel
from typing import Optional
import uuid


class PCFEntryRequest(BaseModel):
    supplier_id: str
    reference_year: int
    scope: int  # 1, 2, 3
    category: Optional[str] = None
    activity_description: Optional[str] = None
    quantity: float
    unit: str
    emission_factor_id: Optional[str] = None
    data_quality: str = "estimated"
    notes: Optional[str] = None


class PCFBatchRequest(BaseModel):
    supplier_id: str
    reference_year: int
    entries: list[PCFEntryRequest]


class PCFEntryResult(BaseModel):
    id: str
    scope: int
    category: Optional[str]
    activity_description: Optional[str]
    quantity: float
    unit: str
    emission_factor_value: Optional[float]
    total_kg_co2e: float
    data_quality: str


class PCFSummaryResponse(BaseModel):
    supplier_id: str
    reference_year: int
    scope1_total: float
    scope2_total: float
    scope3_total: float
    grand_total: float
    entries: list[PCFEntryResult]
    job_id: Optional[str] = None


class PcfRecordCreateRequest(BaseModel):
    """供應商填寫 PCF 物料碳足跡後，由 esgchain-api 呼叫建立或更新 PCFRecord"""
    pcf_request_line_id: str
    bom_line_id: str
    supplier_id: str
    declared_value: float       # kgCO2e/unit
    quantity_unit: str          # kg / pcs / m²
    data_quality: str = "primary"


class PcfRecordCreateResponse(BaseModel):
    id: str
    pcf_request_line_id: str
    bom_line_id: str
    supplier_id: str
    total_kg_co2e: float
    quantity_unit: str
    data_quality: str


class EmissionFactorResponse(BaseModel):
    id: str
    source: str
    category: str
    subcategory: Optional[str]
    activity: str
    unit: str
    co2_factor: float
    total_co2e_per_unit: float
    country: Optional[str]
    year: Optional[int]
