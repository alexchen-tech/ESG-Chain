"""產品 PCF（碳足跡）計算：從 esgchain-api PcfCalculationService::calcForProduct() /
PcrCalculationService::calcForProduct() 逐行搬移過來的純函式，不觸碰 DB。
CLAUDE.md：計算邏輯不可寫在 esgchain-api，一律 call esgchain-ai。
"""
from app.schemas.product_pcf import (
    ProductPcfCalculateRequest,
    ProductPcfCalculateResponse,
    ProductPcfLineRequest,
    ProductPcfLineResult,
)


def _resolve_data_quality(line: ProductPcfLineRequest) -> str:
    # 型態 B（子銷售產品 PCF 快照帶入）：只要有值即視為 primary，沒有就是 missing
    if line.emission_source == "child-pcf":
        return "primary" if line.emission_per_unit is not None else "missing"

    return {
        "portal-self": "primary",
        "buyer-input": "secondary",
        "ai-estimated": "estimated",
    }.get(line.emission_source or "", "missing")


def calculate_product_pcf(
    payload: ProductPcfCalculateRequest,
) -> ProductPcfCalculateResponse:
    lines = payload.lines

    # ── total_pcf / iso14067_ready（原 PcfCalculationService::calcForProduct）──
    total_pcf = 0.0
    iso14067_ready = True
    result_lines: list[ProductPcfLineResult] = []

    for line in lines:
        subtotal = None
        if line.emission_per_unit is not None:
            subtotal = line.emission_per_unit * line.quantity
            total_pcf += subtotal
        else:
            iso14067_ready = False

        if line.is_estimated or line.emission_source == "ai-estimated":
            iso14067_ready = False

        result_lines.append(
            ProductPcfLineResult(
                **line.model_dump(),
                subtotal=subtotal,
                data_quality=_resolve_data_quality(line),
            )
        )

    total_pcf_result = total_pcf if total_pcf > 0 else None
    iso14067_ready = iso14067_ready and len(lines) > 0

    # ── pcr_ratio（原 PcrCalculationService::calcForProduct）──────────────
    weighted_sum = 0.0
    total_weight = 0.0
    pcr_incomplete_lines = 0

    for line in lines:
        if line.line_type != "material":
            continue
        if line.net_weight is None or line.net_weight <= 0 or line.pcr_percentage is None:
            pcr_incomplete_lines += 1
            continue
        total_weight += line.net_weight
        weighted_sum += line.net_weight * (line.pcr_percentage / 100.0)

    pcr_ratio = round(weighted_sum / total_weight, 4) if total_weight > 0 else None

    return ProductPcfCalculateResponse(
        sales_product_id=payload.sales_product_id,
        functional_unit=payload.functional_unit,
        total_pcf=total_pcf_result,
        iso14067_ready=iso14067_ready,
        lines=result_lines,
        pcr_ratio=pcr_ratio,
        pcr_incomplete_lines=pcr_incomplete_lines,
    )
