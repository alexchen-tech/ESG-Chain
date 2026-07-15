"""
SAQ 計分引擎 — Framework-native pillar scoring

計分流程：
1. 依 scoring_framework（由 template 決定）過濾 tag_slugs，只保留對應 L1 domain 的 slugs
2. 依 sasb_industry_code 從 DB 查詢 ScoringModel
3. 依 slug 的 L2 pillar 分組計算加權平均，輸出 category_scores
4. ESG 框架：額外填入 score_e/s/g（向後相容）
5. 對照 ScoringModel 閾值判定 SGS 等級 A/B/C/D/E
"""
from typing import Optional

from app.schemas.scoring import SAQResponseItemRequest, SAQScoringResultResponse

DEFAULT_WEIGHTS = {"E": 0.40, "S": 0.35, "G": 0.25}
DEFAULT_THRESHOLDS = {"A": 80.0, "B": 60.0, "C": 40.0, "D": 20.0}

# 框架 → L1 slug prefix（過濾用）
FRAMEWORK_TO_SLUG_PREFIX: dict[str, str] = {
    "esg":                 "esg.",
    "iso20400":            "iso20400.",
    "iso26000":            "iso26000.",
    "iso28000":            "iso28k.",
    "product-compliance":  "prod_comp.",
    "product_compliance":  "prod_comp.",
    "geo-risk":            "georisk.",
    "geo_risk":            "georisk.",
    # 舊格式相容
    "iso26k":              "iso26k.",
    "geo_risk_old":        "geo_risk.",
}

# slug prefix → L2 pillar 名稱（計分維度分組）
SLUG_PREFIX_TO_PILLAR: dict[str, str] = {
    # ESG 框架（新 L2 節點：esg.env / esg.soc / esg.gov）
    "esg.env.": "環境",
    "esg.soc.": "社會",
    "esg.gov.": "治理",
    # ISO20400 框架
    "iso20400.policy.":        "採購政策",
    "iso20400.due_diligence.": "盡職調查",
    "iso20400.action.":        "行動計畫",
    "iso20400.reporting.":     "報告揭露",
    "iso20400.capacity.":      "能力建構",
    "iso20400.stakeholder.":   "利害關係人",
    # ISO26000 框架
    "iso26000.governance.": "組織治理",
    "iso26000.hr.":         "人權",
    "iso26000.labor.":      "勞工實踐",
    "iso26000.environment.":"環境",
    "iso26000.fairop.":     "公平運營",
    "iso26000.consumer.":   "消費者議題",
    "iso26000.community.":  "社區參與",
    # Geo-Risk 框架
    "georisk.political.":    "政治風險",
    "georisk.environmental.":"環境風險",
    "georisk.social.":       "社會風險",
    "georisk.regulatory.":   "法規風險",
    # ISO28000 框架
    "iso28k.physical.": "實體與人員安全",
    "iso28k.cert.":     "認證與管理體系",
    "iso28k.cargo.":    "貨物與物流安全",
    "iso28k.infosec.":  "資訊安全與韌性",
    # Product-Compliance 框架
    "prod_comp.cbam.":  "CBAM 碳邊境調整",
    "prod_comp.eudr.":  "EUDR 去森林化",
    "prod_comp.chem.":  "化學法規",
    "prod_comp.trace.": "溯源與認證",
    # ESG 舊格式相容（slug prefix 尚未遷移）
    "esg.sc_env.":  "環境",
    "esg.labor.":   "社會",
    "esg.ohs.":     "社會",
    "esg.comm.":    "社會",
    "esg.e.":       "環境",
    "esg.s.":       "社會",
    "esg.g.":       "治理",
    # ISO26000 舊格式相容
    "iso26k.gov.":      "組織治理",
    "iso26k.hr.":       "人權",
    "iso26k.labor.":    "勞工實踐",
    "iso26k.env.":      "環境",
    "iso26k.fair.":     "公平運營",
    "iso26k.consumer.": "消費者議題",
    "iso26k.comm.":     "社區參與",
    # Geo-Risk 舊格式相容
    "geo_risk.geopo.":     "政治風險",
    "geo_risk.logistics.": "環境風險",
}

# ESG pillar → score_e/s/g 對映（向後相容）
ESG_PILLAR_TO_ESG_CAT: dict[str, str] = {
    "環境": "E",
    "社會": "S",
    "治理": "G",
    # 舊格式相容
    "環境管理":     "E",
    "供應鏈環境":   "E",
    "勞工人權":     "S",
    "職場安全":     "S",
    "社區與消費者": "S",
    "公司治理":     "G",
}

OPTION_SCORE_MAP = {
    "完全符合": 100.0, "大部分符合": 75.0, "部分符合": 50.0,
    "少部分符合": 25.0, "不符合": 0.0,
    "yes": 100.0, "no": 0.0, "是": 100.0, "否": 0.0, "有": 100.0, "無": 0.0,
}


def _get_scoring_model_sync(industry_code: Optional[str]) -> tuple[dict, dict, Optional[str]]:
    """同步查詢 ScoringModel（含 fallback）"""
    try:
        from sqlalchemy import create_engine, text
        from app.core.config import settings

        db_url = str(settings.DATABASE_URL).replace("+asyncpg", "")
        engine = create_engine(db_url)
        with engine.connect() as conn:
            if industry_code:
                row = conn.execute(
                    text("SELECT id, weight_e, weight_s, weight_g, grade_a_threshold, grade_b_threshold, grade_c_threshold, grade_d_threshold FROM scoring_models WHERE sasb_industry_code = :code AND is_active = true LIMIT 1"),
                    {"code": industry_code}
                ).fetchone()
                if row:
                    return (
                        {"E": row[1], "S": row[2], "G": row[3]},
                        {"A": row[4], "B": row[5], "C": row[6], "D": row[7]},
                        str(row[0]),
                    )
            row = conn.execute(
                text("SELECT id, weight_e, weight_s, weight_g, grade_a_threshold, grade_b_threshold, grade_c_threshold, grade_d_threshold FROM scoring_models WHERE sasb_industry_code IS NULL AND is_active = true LIMIT 1")
            ).fetchone()
            if row:
                return (
                    {"E": row[1], "S": row[2], "G": row[3]},
                    {"A": row[4], "B": row[5], "C": row[6], "D": row[7]},
                    str(row[0]),
                )
    except Exception:
        pass
    return DEFAULT_WEIGHTS, DEFAULT_THRESHOLDS, None


def _resolve_framework(scoring_framework: Optional[str]) -> Optional[str]:
    return scoring_framework


def _filter_slugs_by_framework(tag_slugs: list[str], framework: Optional[str]) -> list[str]:
    """依 framework 過濾 tag_slugs（NULL framework 不過濾）"""
    if not framework:
        return tag_slugs
    key = framework.lower()
    prefix = FRAMEWORK_TO_SLUG_PREFIX.get(key) or (key.replace("-", "_") + ".")
    return [s for s in tag_slugs if s.startswith(prefix)]


def _slug_to_pillar(slug: str) -> Optional[str]:
    """從 slug prefix 推導 L2 pillar 名稱，找不到回傳 None"""
    for prefix, pillar in SLUG_PREFIX_TO_PILLAR.items():
        if slug.startswith(prefix):
            return pillar
    return None


def _score_single_response(response: SAQResponseItemRequest) -> tuple[float | None, str | None]:
    """單題計分，回傳 (score_0_to_100, confidence)。
    evidence_only 回傳 (None, None)，表示不計入分母。
    """
    scoring_type = response.scoring_type
    direction = response.scoring_direction or "positive"

    if scoring_type == "evidence_only":
        return None, None

    if scoring_type == "llm":
        if response.answer and response.answer.strip():
            return 50.0, "low"
        return 0.0, "low"

    if scoring_type == "custom" and response.option_scores:
        answer_key = (response.answer or "").strip()
        if answer_key in response.option_scores:
            return response.option_scores[answer_key] * 100.0, "high"
        if response.answer_options:
            for opt in response.answer_options:
                if opt in response.option_scores:
                    return response.option_scores[opt] * 100.0, "high"
        return 0.0, "low"

    if scoring_type in ("ordered_asc", "ordered_desc") and response.options:
        options = response.options
        n = len(options)
        selected = response.answer or (response.answer_options[0] if response.answer_options else None)
        if selected and n > 1:
            try:
                idx = options.index(selected)
                ratio = idx / (n - 1)
                if scoring_type == "ordered_desc":
                    ratio = 1.0 - ratio
                return round(ratio * 100.0, 2), "high"
            except ValueError:
                pass
        return 0.0, "low"

    if response.answer is not None:
        answer_lower = response.answer.strip().lower()
        bool_map = {"是": True, "yes": True, "有": True, "否": False, "no": False, "無": False}
        if answer_lower in bool_map:
            is_positive_answer = bool_map[answer_lower]
            if direction == "negative":
                is_positive_answer = not is_positive_answer
            return (100.0 if is_positive_answer else 0.0), "high"

        for key, score in OPTION_SCORE_MAP.items():
            if key.lower() == answer_lower:
                return score, "high"

        try:
            val = float(response.answer)
            return max(0.0, min(100.0, val)), "low"
        except ValueError:
            pass

        if response.answer.strip():
            return 0.0, "low"

    if response.answer_options:
        return min(100.0, len(response.answer_options) / 3.0 * 100.0), "low"

    return 0.0, "low"


def _compute_topic_scores(responses: list[SAQResponseItemRequest]) -> dict[str, float]:
    """依 sasb_topic 分組計算加權平均"""
    topic_items: dict[str, list[tuple[float, float]]] = {}
    for resp in responses:
        if not resp.sasb_topic:
            continue
        score, _ = _score_single_response(resp)
        if score is None:
            continue
        topic_items.setdefault(resp.sasb_topic, []).append((score, resp.weight))
    result = {}
    for topic, items in topic_items.items():
        total_w = sum(w for _, w in items)
        if total_w > 0:
            result[topic] = round(sum(s * w for s, w in items) / total_w, 2)
    return result


def _weighted_avg(items: list[tuple[float, float]]) -> float:
    if not items:
        return 0.0
    total_weight = sum(w for _, w in items)
    if total_weight == 0:
        return 0.0
    return sum(s * w for s, w in items) / total_weight


def calculate_saq_score(
    saq_id: str,
    responses: list[SAQResponseItemRequest],
    scoring_framework: Optional[str] = None,
    weights: Optional[dict] = None,
    thresholds: Optional[dict] = None,
    sasb_industry_code: Optional[str] = None,
    series_pillar_weights: Optional[dict] = None,
    series_grade_thresholds: Optional[dict] = None,
) -> SAQScoringResultResponse:
    """主計分函式（framework-native pillar scoring）"""
    model_id: Optional[str] = None

    # grade 閾值：series 設定 > 傳入 thresholds > ScoringModel > DEFAULT
    if series_grade_thresholds is not None:
        w_raw, _, model_id = _get_scoring_model_sync(sasb_industry_code)
        w = weights or w_raw
        t = series_grade_thresholds
        model_id = None  # series 自訂時不記錄 scoring_model_id
    elif weights is None or thresholds is None:
        db_weights, db_thresholds, model_id = _get_scoring_model_sync(sasb_industry_code)
        w = weights or db_weights
        t = thresholds or db_thresholds
    else:
        w, t = weights, thresholds

    framework = _resolve_framework(scoring_framework)

    # multi-framework：執行三組獨立計分，各自輸出 total 與 category_scores
    if (framework or "").lower() == "multi-framework":
        return _calculate_multi_framework(
            saq_id=saq_id,
            responses=responses,
            w=w, t=t,
            sasb_industry_code=sasb_industry_code,
            model_id=model_id,
            series_pillar_weights=series_pillar_weights,
        )

    # pillar_name → [(score, weight), ...]
    pillar_buckets: dict[str, list[tuple[float, float]]] = {}
    total_effective_slugs = 0

    for resp in responses:
        effective_slugs = _filter_slugs_by_framework(resp.tag_slugs, framework)
        total_effective_slugs += len(effective_slugs)

        if not effective_slugs:
            continue

        score, _confidence = _score_single_response(resp)
        if score is None:
            continue

        pillar = _slug_to_pillar(effective_slugs[0]) or "其他"
        pillar_buckets.setdefault(pillar, []).append((score, resp.weight))

    # 各 pillar 加權平均
    category_scores = {
        pillar: round(_weighted_avg(items), 2)
        for pillar, items in pillar_buckets.items()
    }

    # 總分：各 pillar 等權平均（ESG 框架用 E×40%+S×35%+G×25% 向後相容）
    score_e: Optional[float] = None
    score_s: Optional[float] = None
    score_g: Optional[float] = None

    framework_key = (framework or "").lower()
    if framework_key in ("esg", ""):
        # ESG 框架：合併 pillar 至 E/S/G，維持舊加權公式
        esg_buckets: dict[str, list[tuple[float, float]]] = {"E": [], "S": [], "G": []}
        for pillar, items in pillar_buckets.items():
            cat = ESG_PILLAR_TO_ESG_CAT.get(pillar, "G")
            esg_buckets[cat].extend(items)
        score_e = round(_weighted_avg(esg_buckets["E"]), 2)
        score_s = round(_weighted_avg(esg_buckets["S"]), 2)
        score_g = round(_weighted_avg(esg_buckets["G"]), 2)
        total = round(
            score_e * w.get("E", DEFAULT_WEIGHTS["E"])
            + score_s * w.get("S", DEFAULT_WEIGHTS["S"])
            + score_g * w.get("G", DEFAULT_WEIGHTS["G"]),
            2
        )
    else:
        # 非 ESG 框架：series 自訂 pillar 加權 > 等權平均
        if series_pillar_weights and category_scores:
            # slug prefix（無結尾點）→ pillar name 對照
            slug_to_pillar = {
                prefix.rstrip("."): name
                for prefix, name in SLUG_PREFIX_TO_PILLAR.items()
            }
            weighted_sum = 0.0
            weight_total = 0.0
            for slug, wt in series_pillar_weights.items():
                pillar_name = slug_to_pillar.get(slug)
                if pillar_name and pillar_name in category_scores:
                    weighted_sum += category_scores[pillar_name] * wt
                    weight_total += wt
            total = round(weighted_sum / weight_total, 2) if weight_total > 0 else 0.0
        elif category_scores:
            total = round(sum(category_scores.values()) / len(category_scores), 2)
        else:
            total = 0.0

    if total >= t.get("A", 80.0):
        grade = "A"
    elif total >= t.get("B", 60.0):
        grade = "B"
    elif total >= t.get("C", 40.0):
        grade = "C"
    elif total >= t.get("D", 20.0):
        grade = "D"
    else:
        grade = "E"

    from app.schemas.scoring import QuestionScoreItem
    question_scores = []
    for r in responses:
        score, confidence = _score_single_response(r)
        question_scores.append(QuestionScoreItem(
            project_question_id=r.question_id,
            raw_score=round(score * r.weight, 4) if score is not None else 0.0,
            confidence=confidence,
        ))

    return SAQScoringResultResponse(
        saq_id=saq_id,
        scoring_framework=framework,
        score_e=score_e,
        score_s=score_s,
        score_g=score_g,
        category_scores=category_scores,
        total_score=total,
        grade=grade,
        job_id="sync",
        topic_scores=_compute_topic_scores(responses),
        effective_slugs_count=total_effective_slugs,
        industry_code=sasb_industry_code,
        scoring_model_id=model_id,
        question_scores=question_scores,
    )


def _score_framework_pillar(
    responses: list[SAQResponseItemRequest],
    framework: str,
) -> tuple[Optional[float], dict[str, float]]:
    """單一框架計分輔助：回傳 (total, category_scores)。無匹配 slug 時回傳 (None, {})。"""
    pillar_buckets: dict[str, list[tuple[float, float]]] = {}
    for resp in responses:
        effective_slugs = _filter_slugs_by_framework(resp.tag_slugs, framework)
        if not effective_slugs:
            continue
        score, _ = _score_single_response(resp)
        if score is None:
            continue
        pillar = _slug_to_pillar(effective_slugs[0]) or "其他"
        pillar_buckets.setdefault(pillar, []).append((score, resp.weight))

    if not pillar_buckets:
        return None, {}

    category_scores = {
        pillar: round(_weighted_avg(items), 2)
        for pillar, items in pillar_buckets.items()
    }
    total = round(sum(category_scores.values()) / len(category_scores), 2)
    return total, category_scores


def _calculate_multi_framework(
    saq_id: str,
    responses: list[SAQResponseItemRequest],
    w: dict,
    t: dict,
    sasb_industry_code: Optional[str],
    model_id: Optional[str],
    series_pillar_weights: Optional[dict],
) -> "SAQScoringResultResponse":
    """multi-framework 計分：對同一份答題執行三組 filter，各自輸出分數。"""
    from app.schemas.scoring import QuestionScoreItem

    iso26000_total, iso26000_cat = _score_framework_pillar(responses, "iso26000")
    iso20400_total, iso20400_cat = _score_framework_pillar(responses, "iso20400")
    geo_risk_total, _ = _score_framework_pillar(responses, "geo_risk")

    # 三軸換算：暴露/風險 = 100 - 正向分
    axis1_score = round(100.0 - iso26000_total, 2) if iso26000_total is not None else None
    axis2_score = round(100.0 - iso20400_total, 2) if iso20400_total is not None else None

    # 向後相容 score_e/s/g：從 iso26k.* esg 對映填入
    esg_buckets: dict[str, list[tuple[float, float]]] = {"E": [], "S": [], "G": []}
    for resp in responses:
        effective_slugs = _filter_slugs_by_framework(resp.tag_slugs, "iso26000")
        if not effective_slugs:
            continue
        score, _ = _score_single_response(resp)
        if score is None:
            continue
        pillar = _slug_to_pillar(effective_slugs[0]) or "其他"
        cat = ESG_PILLAR_TO_ESG_CAT.get(pillar, "G")
        esg_buckets[cat].append((score, resp.weight))

    score_e = round(_weighted_avg(esg_buckets["E"]), 2) if esg_buckets["E"] else None
    score_s = round(_weighted_avg(esg_buckets["S"]), 2) if esg_buckets["S"] else None
    score_g = round(_weighted_avg(esg_buckets["G"]), 2) if esg_buckets["G"] else None

    # 整體分：以 iso26000_total 為主（ESG 暴露框架代表性最強），無則以 iso20400
    representative = iso26000_total if iso26000_total is not None else (iso20400_total or 0.0)
    if representative >= t.get("A", 80.0):
        grade = "A"
    elif representative >= t.get("B", 60.0):
        grade = "B"
    elif representative >= t.get("C", 40.0):
        grade = "C"
    elif representative >= t.get("D", 20.0):
        grade = "D"
    else:
        grade = "E"

    question_scores = []
    for r in responses:
        score, confidence = _score_single_response(r)
        question_scores.append(QuestionScoreItem(
            project_question_id=r.question_id,
            raw_score=round(score * r.weight, 4) if score is not None else 0.0,
            confidence=confidence,
        ))

    return SAQScoringResultResponse(
        saq_id=saq_id,
        scoring_framework="multi-framework",
        score_e=score_e,
        score_s=score_s,
        score_g=score_g,
        category_scores={**iso26000_cat, **iso20400_cat},
        total_score=representative,
        grade=grade,
        job_id="sync",
        topic_scores=_compute_topic_scores(responses),
        effective_slugs_count=sum(
            len(_filter_slugs_by_framework(r.tag_slugs, "iso26000")) +
            len(_filter_slugs_by_framework(r.tag_slugs, "iso20400"))
            for r in responses
        ),
        industry_code=sasb_industry_code,
        scoring_model_id=model_id,
        question_scores=question_scores,
        iso26000_total=iso26000_total,
        iso26000_category_scores=iso26000_cat,
        iso20400_total=iso20400_total,
        iso20400_category_scores=iso20400_cat,
        geo_risk_total=geo_risk_total,
        axis1_score=axis1_score,
        axis2_score=axis2_score,
    )
