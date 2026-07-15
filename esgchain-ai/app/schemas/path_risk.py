from pydantic import BaseModel
from typing import Optional


class SupplierEmissionInput(BaseModel):
    supplier_id: str
    axis1_score: Optional[float] = None   # ESG 暴露分（0~100），null 時視為高暴露
    co2_kg: Optional[float] = None        # 供應商碳排（kgCO₂e），null 時 fallback
    industry_emission_factor: Optional[float] = None  # fallback 行業均值


class PathRiskRequest(BaseModel):
    trade_good_id: str
    market: str
    supplier_emissions: list[SupplierEmissionInput]
    missing_mandatory_obligations: int = 0   # 缺失強制義務數
    total_mandatory_obligations: int = 0     # 總強制義務數


class ContributorDetail(BaseModel):
    supplier_id: str
    axis1_score: float
    carbon_share: float   # 占比 0~1
    contribution: float   # axis1_score/100 × carbon_share
    data_gap: bool = False


class PathRiskResponse(BaseModel):
    trade_good_id: str
    market: str
    path_risk_score: float
    risk_level: str   # very_low / low / medium / high / extreme
    amplifier: float
    chain_risk: float
    has_data_gap: bool
    contributors: list[ContributorDetail]


class ReplacementCandidateInput(BaseModel):
    supplier_id: str
    name: str
    country_code: str
    axis1_score: float
    co2_kg: Optional[float] = None


class SupplierReplacementRequest(BaseModel):
    trade_good_id: str
    market: str
    replace_supplier_id: str
    current_path_risk_score: float
    current_chain_risk: float
    replace_supplier_carbon_share: float
    replace_supplier_axis1_score: float
    candidates: list[ReplacementCandidateInput]
    bom_supplier_ids: list[str] = []   # 已在 BOM 中的供應商 ids


class ReplacementCandidateResult(BaseModel):
    supplier_id: str
    name: str
    country_code: str
    axis1_score: float
    simulated_chain_risk: float
    simulated_path_risk_score: float
    improvement_pct: float
    already_in_supply_chain: bool


class SupplierReplacementResponse(BaseModel):
    candidates: list[ReplacementCandidateResult]
    message: Optional[str] = None
