from app.schemas.composition import (
    CompositionCalculateRequest,
    CompositionCalculateResponse,
    CompositionBreakdownItem,
    CompositionLineRequest,
)


def _line_weight(line: CompositionLineRequest) -> float:
    return line.net_weight * line.quantity


def _weighted_percentage_avg(
    lines: list[CompositionLineRequest], get_value
) -> float | None:
    """對有值的行做加權平均，分母只用這些有值行自己的重量，缺值行不拉低分母"""
    numerator = 0.0
    denominator = 0.0
    for line in lines:
        if line.net_weight is None:
            continue
        value = get_value(line)
        if value is None:
            continue
        weight = _line_weight(line)
        numerator += weight * (value / 100.0)
        denominator += weight
    if denominator <= 0:
        return None
    return round(numerator / denominator, 4)


def calculate_composition(
    payload: CompositionCalculateRequest,
) -> CompositionCalculateResponse:
    lines = payload.lines
    weighted_lines = [l for l in lines if l.net_weight is not None]
    incomplete_lines_count = len(lines) - len(weighted_lines)

    total_weight_kg = round(sum(_line_weight(l) for l in weighted_lines), 4) if weighted_lines else None

    pcr_ratio = _weighted_percentage_avg(lines, lambda l: l.pcr_percentage)
    pir_ratio = _weighted_percentage_avg(lines, lambda l: l.pir_percentage)
    bio_based_ratio = _weighted_percentage_avg(lines, lambda l: l.bio_based_percentage)
    recycled_content_ratio = _weighted_percentage_avg(
        lines,
        lambda l: (
            (l.pcr_percentage or 0) + (l.pir_percentage or 0)
            if l.pcr_percentage is not None or l.pir_percentage is not None
            else None
        ),
    )

    # 成分佔比：依 fiber_type 分組，fiber_type 為 None 的行（化學品/服務）不納入拆解，
    # 但重量仍計入 total_weight_kg，所以拆解百分比加總可能小於 100%（代表未分類的重量佔比）
    fiber_weights: dict[str, float] = {}
    for line in weighted_lines:
        if not line.fiber_type:
            continue
        fiber_weights[line.fiber_type] = fiber_weights.get(line.fiber_type, 0.0) + _line_weight(line)

    composition_breakdown = []
    if total_weight_kg:
        composition_breakdown = sorted(
            [
                CompositionBreakdownItem(
                    fiber_type=fiber_type,
                    weight_kg=round(weight, 4),
                    percentage=round(weight / total_weight_kg * 100, 2),
                )
                for fiber_type, weight in fiber_weights.items()
            ],
            key=lambda item: item.percentage,
            reverse=True,
        )

    # 可回收性分布：每個有重量的行都算一份（沒評級歸類到 not_rated），讓百分比加總為 100%
    rating_weights: dict[str, float] = {}
    for line in weighted_lines:
        rating = line.recyclability_rating or "not_rated"
        rating_weights[rating] = rating_weights.get(rating, 0.0) + _line_weight(line)

    recyclability_summary = {}
    if total_weight_kg:
        recyclability_summary = {
            rating: round(weight / total_weight_kg * 100, 2)
            for rating, weight in rating_weights.items()
        }

    return CompositionCalculateResponse(
        total_weight_kg=total_weight_kg,
        recycled_content_ratio=recycled_content_ratio,
        pcr_ratio=pcr_ratio,
        pir_ratio=pir_ratio,
        bio_based_ratio=bio_based_ratio,
        composition_breakdown=composition_breakdown,
        recyclability_summary=recyclability_summary,
        incomplete_lines_count=incomplete_lines_count,
        data_ready=incomplete_lines_count == 0,
    )
