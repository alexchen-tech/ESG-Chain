"""
路徑風險計算服務

公式：
  Chain_Risk = Σ(axis1_score_i/100 × carbon_share_i)
  Amplifier  = 1 + (missing_mandatory / total_mandatory)   # total=0 時 amplifier=1
  Path_Risk  = Chain_Risk × Amplifier

軸1分缺失（null）→ fallback 0.8（高暴露）並標記 data_gap
碳排缺失 → fallback 行業均值，仍標記 data_gap
"""
from typing import Optional

from app.schemas.path_risk import (
    ContributorDetail,
    PathRiskRequest,
    PathRiskResponse,
    ReplacementCandidateResult,
    SupplierReplacementRequest,
    SupplierReplacementResponse,
)

_DEFAULT_AXIS1_FALLBACK = 80.0  # 無 SAQ 資料時預設高暴露

_RISK_LEVEL_THRESHOLDS = [
    (1.6, "extreme"),
    (1.2, "high"),
    (0.8, "medium"),
    (0.4, "low"),
    (0.0, "very_low"),
]


def _score_to_level(score: float) -> str:
    for threshold, level in _RISK_LEVEL_THRESHOLDS:
        if score >= threshold:
            return level
    return "very_low"


def calculate_path_risk(req: PathRiskRequest) -> PathRiskResponse:
    suppliers = req.supplier_emissions
    if not suppliers:
        return PathRiskResponse(
            trade_good_id=req.trade_good_id,
            market=req.market,
            path_risk_score=0.0,
            risk_level="very_low",
            amplifier=1.0,
            chain_risk=0.0,
            has_data_gap=False,
            contributors=[],
        )

    # 計算總碳排（用於計算各供應商占比）
    effective_emissions: list[tuple[float, bool]] = []  # (co2_kg, is_fallback)
    for sup in suppliers:
        if sup.co2_kg is not None and sup.co2_kg > 0:
            effective_emissions.append((sup.co2_kg, False))
        elif sup.industry_emission_factor is not None and sup.industry_emission_factor > 0:
            effective_emissions.append((sup.industry_emission_factor, True))
        else:
            effective_emissions.append((1.0, True))  # 最終 fallback，確保不歸零

    total_co2 = sum(e for e, _ in effective_emissions)

    contributors: list[ContributorDetail] = []
    has_data_gap = False
    chain_risk = 0.0

    for i, sup in enumerate(suppliers):
        emission, is_co2_fallback = effective_emissions[i]
        carbon_share = emission / total_co2 if total_co2 > 0 else 0.0

        axis1 = sup.axis1_score
        is_axis1_fallback = axis1 is None
        effective_axis1 = axis1 if axis1 is not None else _DEFAULT_AXIS1_FALLBACK

        data_gap = is_co2_fallback or is_axis1_fallback
        if data_gap:
            has_data_gap = True

        contribution = (effective_axis1 / 100.0) * carbon_share
        chain_risk += contribution

        contributors.append(ContributorDetail(
            supplier_id=sup.supplier_id,
            axis1_score=effective_axis1,
            carbon_share=round(carbon_share, 4),
            contribution=round(contribution, 4),
            data_gap=data_gap,
        ))

    chain_risk = round(chain_risk, 4)

    # 市場法規放大係數
    if req.total_mandatory_obligations > 0:
        amplifier = round(1.0 + req.missing_mandatory_obligations / req.total_mandatory_obligations, 4)
    else:
        amplifier = 1.0

    path_risk_score = round(chain_risk * amplifier, 4)
    risk_level = _score_to_level(path_risk_score)

    return PathRiskResponse(
        trade_good_id=req.trade_good_id,
        market=req.market,
        path_risk_score=path_risk_score,
        risk_level=risk_level,
        amplifier=amplifier,
        chain_risk=chain_risk,
        has_data_gap=has_data_gap,
        contributors=contributors,
    )


def calculate_replacement_candidates(req: SupplierReplacementRequest) -> SupplierReplacementResponse:
    if not req.candidates:
        return SupplierReplacementResponse(
            candidates=[],
            message="系統內無符合條件的替換候選供應商",
        )

    results: list[ReplacementCandidateResult] = []
    w = req.replace_supplier_carbon_share
    old_axis1 = req.replace_supplier_axis1_score / 100.0

    for candidate in req.candidates:
        new_axis1 = candidate.axis1_score / 100.0

        # 模擬替換：將舊供應商的 contribution 替換為候選的 contribution
        simulated_chain_risk = req.current_chain_risk - (old_axis1 * w) + (new_axis1 * w)
        simulated_chain_risk = max(0.0, round(simulated_chain_risk, 4))

        # 使用相同 amplifier（路徑風險 = chain × amplifier，amplifier 不變）
        original_amplifier = (req.current_path_risk_score / req.current_chain_risk) if req.current_chain_risk > 0 else 1.0
        simulated_path_risk = round(simulated_chain_risk * original_amplifier, 4)

        improvement = req.current_path_risk_score - simulated_path_risk
        improvement_pct = round((improvement / req.current_path_risk_score) * 100.0, 1) if req.current_path_risk_score > 0 else 0.0

        results.append(ReplacementCandidateResult(
            supplier_id=candidate.supplier_id,
            name=candidate.name,
            country_code=candidate.country_code,
            axis1_score=candidate.axis1_score,
            simulated_chain_risk=simulated_chain_risk,
            simulated_path_risk_score=simulated_path_risk,
            improvement_pct=improvement_pct,
            already_in_supply_chain=candidate.supplier_id in req.bom_supplier_ids,
        ))

    # 依改善幅度降序，already_in_supply_chain 置後
    results.sort(key=lambda r: (-r.improvement_pct if not r.already_in_supply_chain else -r.improvement_pct - 1000))
    results = results[:10]

    return SupplierReplacementResponse(candidates=results)
