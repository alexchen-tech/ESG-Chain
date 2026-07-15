"""
Scope 3 Report — GHG Protocol 15 類別彙總
GET /ai/v1/reports/scope3?year=2024
"""
from fastapi import APIRouter, Depends, Query
from sqlalchemy import select, func
from sqlalchemy.ext.asyncio import AsyncSession

from app.core.security import get_current_user
from app.db.postgresql import get_db
from app.models.pcf_record import PCFRecord

router = APIRouter(prefix="/reports", tags=["reports"])

# GHG Protocol Scope 3 category 對照表
CATEGORY_MAP = {
    "purchased_goods":      {"number": 1,  "name": "採購商品和服務"},
    "capital_goods":        {"number": 2,  "name": "資本財"},
    "energy_activities":    {"number": 3,  "name": "能源相關活動"},
    "upstream_transport":   {"number": 4,  "name": "上游運輸和配送"},
    "waste":                {"number": 5,  "name": "營運產生的廢棄物"},
    "business_travel":      {"number": 6,  "name": "商務旅行"},
    "employee_commuting":   {"number": 7,  "name": "員工通勤"},
    "upstream_leased":      {"number": 8,  "name": "上游租賃資產"},
    "downstream_transport": {"number": 9,  "name": "下游運輸和配送"},
    "processing":           {"number": 10, "name": "已售商品加工"},
    "product_use":          {"number": 11, "name": "已售商品使用"},
    "end_of_life":          {"number": 12, "name": "已售商品報廢處理"},
    "downstream_leased":    {"number": 13, "name": "下游租賃資產"},
    "franchises":           {"number": 14, "name": "特許經營"},
    "investments":          {"number": 15, "name": "投資"},
    # emission factor categories → default to category 1 or 4
    "electricity_tw":       {"number": 3,  "name": "能源相關活動"},
    "electricity_global":   {"number": 3,  "name": "能源相關活動"},
    "sea_freight":          {"number": 4,  "name": "上游運輸和配送"},
    "road_freight":         {"number": 4,  "name": "上游運輸和配送"},
    "air_freight":          {"number": 6,  "name": "商務旅行"},
    "diesel":               {"number": 1,  "name": "採購商品和服務"},
    "steel_production":     {"number": 1,  "name": "採購商品和服務"},
}


@router.get("/scope3")
async def scope3_report(
    year: int = Query(..., ge=2000, le=2100),
    db: AsyncSession = Depends(get_db),
    current_user: dict = Depends(get_current_user),
):
    """GHG Protocol Scope 3 十五類別排放彙總"""
    result = await db.execute(
        select(PCFRecord.category, func.sum(PCFRecord.total_kg_co2e).label("co2e"))
        .where(PCFRecord.reference_year == year)
        .group_by(PCFRecord.category)
    )
    rows = result.all()

    # 按 GHG Protocol category 分組
    category_totals: dict[int, float] = {}
    for category, co2e in rows:
        mapping = CATEGORY_MAP.get(category or "diesel", {"number": 1})
        cat_num = mapping["number"]
        category_totals[cat_num] = category_totals.get(cat_num, 0.0) + (co2e or 0.0)

    # 建立 15 類別結果
    categories = []
    for num in range(1, 16):
        mapping_info = next(
            (v for v in CATEGORY_MAP.values() if v["number"] == num),
            {"number": num, "name": f"Category {num}"}
        )
        categories.append({
            "category_number": num,
            "category_name": mapping_info["name"],
            "co2e": round(category_totals.get(num, 0.0), 4),
        })

    total = sum(c["co2e"] for c in categories)

    return {
        "success": True,
        "data": {
            "reporting_year": year,
            "total_co2e": round(total, 4),
            "categories": categories,
        },
        "message": "",
    }
