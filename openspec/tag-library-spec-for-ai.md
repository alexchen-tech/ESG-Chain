# ESG·Chain 標籤庫規格書
> 供 Claude AI 產生 SAQ 問卷、計分規則、報告範本使用的標準參考文件
> 版本：v1.0 · 匯出日期：2026-06-05 · 總計 87 個 L3 調查主題

---

## 一、系統架構說明

ESG·Chain 標籤庫採用三層分類結構，用於標記 SAQ（供應商自評問卷）題目：

```
L1 領域（Domain）
└── L2 調查分類（Pillar）
    └── L3 調查主題（Topic）
        ├── slug：全系統唯一識別碼（建立後不可更改）
        ├── label_zh：中文名稱
        ├── label_en：英文名稱（含業界縮寫）
        └── scoring_engine_key：對應 esgchain-ai 計分引擎（選填）
```

**五個 L1 領域的定位：**

| L1 領域 | 定位 | 主要用途 |
|---------|------|---------|
| `ISO26000` | 社會責任哲學框架（ISO 26000:2010） | SAQ 頂層設計、哲學依據 |
| `ESG` | 供應商自身 ESG 表現 | 一般 ESG 問卷題目 |
| `ISO20400` | 品牌方永續採購管理 | 採購商自評、買方能力 |
| `Geo-Risk` | 外部地緣/天災風險評估 | 風險矩陣、國家風險 |
| `Product-Compliance` | 產品法規合規（EU/US 法規） | 出口合規文件清單 |

---

## 二、完整標籤清單

### L1：ISO26000（30 個主題）
> ISO 26000:2010 七大核心主題，作為整個 ESG·Chain 問卷設計的哲學根基

#### L2：組織治理（Organizational Governance · Clause 6.2）
| Slug | 中文名稱 | 英文名稱（含縮寫） |
|------|---------|-----------------|
| `iso26k.gov.accountability` | 課責機制與決策流程 | Accountability & Decision-making |
| `iso26k.gov.stakeholder` | 利害關係人識別與參與 | Stakeholder Identification & Engagement |
| `iso26k.gov.disclosure` | ESG 治理資訊揭露 | ESG Governance & Transparency Disclosure |

#### L2：人權（Human Rights · Clause 6.3）
| Slug | 中文名稱 | 英文名稱（含縮寫） |
|------|---------|-----------------|
| `iso26k.hr.due_diligence` | 人權盡職調查 | Human Rights Due Diligence (HRDD) |
| `iso26k.hr.grievance` | 申訴與救濟機制 | Grievance & Remedy Mechanisms (UNGPs) |
| `iso26k.hr.complicity` | 迴避共謀責任 | Avoidance of Complicity |
| `iso26k.hr.vulnerable` | 脆弱群體保護 | Protection of Vulnerable Groups |
| `iso26k.hr.sc_risk` | 供應鏈人權風險 | Human Rights Risks in Supply Chain |

#### L2：勞工實踐（Labour Practices · Clause 6.4）
| Slug | 中文名稱 | 英文名稱（含縮寫） |
|------|---------|-----------------|
| `iso26k.labor.employment_rel` | 就業關係正規化 | Employment Relationships (ILO) |
| `iso26k.labor.conditions` | 勞動條件與社會保障 | Labour Conditions & Social Protection |
| `iso26k.labor.social_dialogue` | 社會對話與集體談判 | Social Dialogue & Collective Bargaining (SD/CB) |
| `iso26k.labor.ohs` | 職場健康安全管理 | Occupational Health & Safety (OHS / ISO 45001) |
| `iso26k.labor.development` | 人力培訓與職涯發展 | Training, Development & Career Growth |

#### L2：環境（The Environment · Clause 6.5）
| Slug | 中文名稱 | 英文名稱（含縮寫） |
|------|---------|-----------------|
| `iso26k.env.precaution` | 預防原則應用 | Precautionary Approach (Rio Principle 15) |
| `iso26k.env.pollution` | 污染防制與廢棄物管理 | Pollution Prevention & Waste Management |
| `iso26k.env.resource` | 永續資源使用 | Sustainable Resource Use (Circular Economy) |
| `iso26k.env.climate` | 氣候變遷減緩與調適 | Climate Change Mitigation & Adaptation (TCFD) |
| `iso26k.env.biodiversity` | 生物多樣性與生態系服務 | Biodiversity & Ecosystem Services (TNFD) |

#### L2：公平營運實踐（Fair Operating Practices · Clause 6.6）
| Slug | 中文名稱 | 英文名稱（含縮寫） |
|------|---------|-----------------|
| `iso26k.fair.anti_corruption` | 反腐敗與反賄賂 | Anti-Corruption & Bribery (FCPA / UK Bribery Act) |
| `iso26k.fair.competition` | 公平競爭與反壟斷 | Fair Competition & Anti-Trust |
| `iso26k.fair.sc_promotion` | 推動供應鏈社會責任 | Promoting SR in Supply Chain (CoC) |
| `iso26k.fair.property_rights` | 財產權與智慧財產尊重 | Respect for Property Rights (IP) |

#### L2：消費者議題（Consumer Issues · Clause 6.7）
| Slug | 中文名稱 | 英文名稱（含縮寫） |
|------|---------|-----------------|
| `iso26k.consumer.marketing` | 公平行銷與真實揭露 | Fair Marketing & Truthful Disclosure |
| `iso26k.consumer.safety` | 產品安全與消費者健康 | Product Safety & Consumer Health Protection |
| `iso26k.consumer.sustainable` | 永續消費促進 | Promoting Sustainable Consumption |
| `iso26k.consumer.data_privacy` | 個資保護與數位權利 | Data Privacy & Digital Rights (GDPR) |

#### L2：社區參與與發展（Community Involvement & Development · Clause 6.8）
| Slug | 中文名稱 | 英文名稱（含縮寫） |
|------|---------|-----------------|
| `iso26k.comm.involvement` | 社區參與與在地投資 | Community Involvement & Local Investment |
| `iso26k.comm.employment` | 就業創造與技能提升 | Employment Creation & Skills Development |
| `iso26k.comm.culture` | 在地文化與傳統保護 | Local Culture & Heritage Respect |
| `iso26k.comm.sia` | 社會影響評估 | Social Impact Assessment (SIA) |

---

### L1：ESG（30 個主題）
> 供應商自身 ESG 表現，用於一般性 SAQ 問卷題目設計

#### L2：環境管理（7 個）
| Slug | 中文名稱 | 英文名稱 | 計分引擎 |
|------|---------|---------|---------|
| `esg.env.ghg_emission` | 溫室氣體排放 | GHG Emissions | `ghg_scoring_v1` |
| `esg.env.energy_consumption` | 能源消耗管理 | Energy Consumption | `energy_scoring_v1` |
| `esg.env.water_usage` | 用水管理 | Water Usage | — |
| `esg.env.waste_management` | 廢棄物管理 | Waste Management | — |
| `esg.env.biodiversity` | 生物多樣性 | Biodiversity | — |
| `esg.env.chemical_mgmt` | 化學品管理 | Chemical Management | — |
| `esg.env.carbon_neutrality` | 碳中和目標與路徑 | Carbon Neutrality Target | `decarb_scoring_v1` |

#### L2：供應鏈環境（4 個）
| Slug | 中文名稱 | 英文名稱 | 計分引擎 |
|------|---------|---------|---------|
| `esg.sc_env.scope3` | 範疇三排放（供應鏈） | Scope 3 Emissions | `scope3_scoring_v1` |
| `esg.sc_env.raw_material` | 原料溯源與永續採購 | Raw Material Sourcing | — |
| `esg.sc_env.packaging` | 包裝與減塑 | Packaging & Plastic Reduction | — |
| `esg.sc_env.eudr` | EUDR 森林砍伐合規 | EUDR Deforestation Compliance | — |

#### L2：勞工人權（6 個）
| Slug | 中文名稱 | 英文名稱 | 計分引擎 |
|------|---------|---------|---------|
| `esg.labor.forced_labor` | 強迫勞動防制 | Forced Labor Prevention | `labor_scoring_v1` |
| `esg.labor.child_labor` | 童工禁止 | Child Labor Prohibition | — |
| `esg.labor.working_hours` | 工時與休息管理 | Working Hours Management | — |
| `esg.labor.wages` | 工資與薪酬公平 | Fair Wages & Compensation | — |
| `esg.labor.freedom_assoc` | 結社自由與集體談判 | Freedom of Association | — |
| `esg.labor.discrimination` | 平等與反歧視 | Non-Discrimination | — |

#### L2：職場安全（4 個）
| Slug | 中文名稱 | 英文名稱 |
|------|---------|---------|
| `esg.ohs.safety_mgmt` | 職安管理系統 | OHS Management System |
| `esg.ohs.incident_rate` | 事故率與統計 | Incident Rate & Statistics |
| `esg.ohs.ppe` | 個人防護裝備（PPE） | Personal Protective Equipment |
| `esg.ohs.training` | 安全教育訓練 | Safety Training |

#### L2：社區與消費者（3 個）
| Slug | 中文名稱 | 英文名稱 |
|------|---------|---------|
| `esg.comm.community_invest` | 社區投資與參與 | Community Investment |
| `esg.comm.product_safety` | 產品安全與責任 | Product Safety & Liability |
| `esg.comm.data_privacy` | 資料隱私保護 | Data Privacy |

#### L2：公司治理（6 個）
| Slug | 中文名稱 | 英文名稱 |
|------|---------|---------|
| `esg.gov.code_of_conduct` | 行為準則與倫理 | Code of Conduct & Ethics |
| `esg.gov.anti_corruption` | 反腐敗與反賄賂 | Anti-Corruption & Bribery |
| `esg.gov.whistleblower` | 吹哨人保護機制 | Whistleblower Protection |
| `esg.gov.transparency` | 資訊透明度與揭露 | Transparency & Disclosure |
| `esg.gov.board` | 董事會組成與獨立性 | Board Composition |
| `esg.gov.supplier_code` | 供應商行為準則推廣 | Supplier Code of Conduct |

---

### L1：ISO20400（9 個主題）
> ISO 20400:2017 永續採購，用於品牌採購方的自評問卷

#### L2：採購政策（Clause 5/6）
| Slug | 中文名稱 | 英文名稱 |
|------|---------|---------|
| `iso20400.policy.commitment` | 永續採購承諾聲明 | Sustainable Procurement Commitment |
| `iso20400.policy.criteria` | 採購評選標準整合 | Procurement Criteria Integration |
| `iso20400.policy.supplier_dev` | 供應商能力建構計畫 | Supplier Development Program |

#### L2：風險管理（Clause 8）
| Slug | 中文名稱 | 英文名稱 | 計分引擎 |
|------|---------|---------|---------|
| `iso20400.risk.assessment` | 永續風險評估流程 | Sustainability Risk Assessment | `risk_scoring_v1` |
| `iso20400.risk.due_diligence` | 人權與環境盡職調查 | Human Rights Due Diligence | — |
| `iso20400.risk.country` | 國家/地區風險評估 | Country Risk Assessment | — |

#### L2：績效評估（Clause 9）
| Slug | 中文名稱 | 英文名稱 |
|------|---------|---------|
| `iso20400.perf.kpi` | 永續採購 KPI 設定 | Sustainable Procurement KPIs |
| `iso20400.perf.audit` | 定期稽核與查核 | Regular Audit & Inspection |
| `iso20400.perf.report` | 永續採購報告揭露 | Sustainable Procurement Reporting |

---

### L1：Geo-Risk（6 個主題）
> 外部地緣政治與供應鏈韌性風險

#### L2：地緣政治風險
| Slug | 中文名稱 | 英文名稱 |
|------|---------|---------|
| `geo_risk.geopo.sanctions` | 制裁與出口管制 | Sanctions & Export Controls |
| `geo_risk.geopo.conflict` | 衝突礦物與風險區域 | Conflict Minerals & High-Risk Areas |
| `geo_risk.geopo.tariff` | 關稅與貿易壁壘 | Tariff & Trade Barriers |

#### L2：物流/天災
| Slug | 中文名稱 | 英文名稱 |
|------|---------|---------|
| `geo_risk.logistics.disaster` | 自然災害韌性 | Natural Disaster Resilience |
| `geo_risk.logistics.bcp` | 業務持續計畫（BCP） | Business Continuity Plan (BCP) |
| `geo_risk.logistics.single_src` | 單一來源依賴風險 | Single-Source Dependency Risk |

---

### L1：Product-Compliance（12 個主題）
> EU/US 出口法規合規，用於貿易合規問卷與文件清單

#### L2：CBAM合規（Carbon Border Adjustment Mechanism）
| Slug | 中文名稱 | 英文名稱 | 計分引擎 |
|------|---------|---------|---------|
| `prod_comp.cbam.embedded_emission` | 內含碳排計算方法 | Embedded Carbon Calculation (ECA/CBAM) | `cbam_scoring_v1` |
| `prod_comp.cbam.reporting` | CBAM 申報文件準備 | CBAM Reporting & Declaration (CBAM Art.6) | — |
| `prod_comp.cbam.product_coverage` | CBAM 適用產品確認 | CBAM Product Coverage Check (HS Code) | — |

#### L2：EUDR合規（EU Deforestation Regulation）
| Slug | 中文名稱 | 英文名稱 |
|------|---------|---------|
| `prod_comp.eudr.dds` | EUDR 盡職調查聲明（DDS） | Due Diligence Statement (DDS / EUDR) |
| `prod_comp.eudr.traceability` | 原料溯源至農場/地塊 | Farm-level Traceability (Geo-polygon) |
| `prod_comp.eudr.certification` | 森林認證（FSC/PEFC等） | Forest Certification (FSC / PEFC / RSPO) |

#### L2：化學法規（Chemical Regulations）
| Slug | 中文名稱 | 英文名稱 |
|------|---------|---------|
| `prod_comp.chem.reach` | REACH 有害物質管制 | REACH SVHC & Restriction (EU 1907/2006) |
| `prod_comp.chem.rohs` | RoHS 限制物質 | RoHS Restricted Substances (EU 2011/65) |
| `prod_comp.chem.sds` | 安全資料表（SDS）管理 | Safety Data Sheet (SDS / GHS) |

#### L2：溯源與認證（Traceability & Certification）
| Slug | 中文名稱 | 英文名稱 |
|------|---------|---------|
| `prod_comp.trace.uflpa` | UFLPA 強迫勞動防制（棉花） | Forced Labor — Cotton (UFLPA / Xinjiang) |
| `prod_comp.trace.cmrt` | 衝突礦物報告（CMRT） | Conflict Mineral Report (CMRT / 3TG) |
| `prod_comp.trace.origin_cert` | 原產地證明 | Certificate of Origin (CoO / EUR.1) |

---

## 三、計分引擎索引

目前已定義的計分引擎 key（對應 esgchain-ai FastAPI 服務）：

| scoring_engine_key | 用途 | 關聯標籤 |
|-------------------|------|---------|
| `ghg_scoring_v1` | GHG 排放量計算與評分 | `esg.env.ghg_emission` |
| `energy_scoring_v1` | 能源強度評分 | `esg.env.energy_consumption` |
| `decarb_scoring_v1` | 減碳路徑（SBTi）達成評估 | `esg.env.carbon_neutrality` |
| `scope3_scoring_v1` | 範疇三排放加權計算 | `esg.sc_env.scope3` |
| `labor_scoring_v1` | 勞工人權風險評分 | `esg.labor.forced_labor` |
| `risk_scoring_v1` | 綜合永續風險評分 | `iso20400.risk.assessment` |
| `cbam_scoring_v1` | 內含碳計算合規評分 | `prod_comp.cbam.embedded_emission` |

---

## 四、給 Claude AI 的使用說明

### 使用本文件可以要求 Claude 執行以下任務：

#### A. 產生 SAQ 問卷題目
```
根據以下標籤庫規格，為「[L2 分類名稱]」設計一份 SAQ 問卷區塊。
每個 L3 主題產生 3–5 道問題，包含：
- 問題類型（是非題 / 選擇題 / 數值填入 / 文件上傳）
- 中英文雙語題目
- 選項或驗收條件
- 對應的計分權重建議（E/S/G 分類）
```

#### B. 設計 E/S/G 加權結構
```
根據標籤庫的 L1/L2/L3 結構，為一份針對「[產業/情境]」的 SAQ 問卷
設計 E/S/G 三類加權比例，合計須為 100%。
標籤 slug 作為題目識別鍵，請說明每個 L2 分類的建議權重與理由。
```

#### C. 生成合規文件需求清單
```
根據 Product-Compliance 標籤庫，為出口到「[目標國家/市場]」的
「[HS Code 或產品類別]」供應商，列出所需提供的合規文件清單。
每項文件對應一個 L3 slug，說明文件名稱、格式要求、有效期限。
```

#### D. 產生 SAQ 問卷範本 JSON 結構
```
根據以下選定的標籤 slug 清單，產生符合 ESG·Chain SAQTemplate
資料格式的 JSON 結構。包含 sections（對應 L2）和 questions（對應 L3）。
選定標籤：[slug 列表]
```

#### E. 風險矩陣設計
```
根據 Geo-Risk 和 ESG 標籤庫，為「[供應商國家]」的
「[產業類別]」供應商設計一份風險評估矩陣。
每個 L3 主題評估：可能性（1–5）× 影響程度（1–5）= 風險值。
```

---

## 五、標籤使用規則（給 AI 的約束條件）

1. **slug 不可修改**：所有 slug 在系統中唯一且永久，不得重新命名
2. **跨域標記**：同一道題可同時掛多個來自不同 L1 的標籤（如 `esg.labor.forced_labor` + `iso26k.hr.sc_risk`）
3. **計分引擎**：有 `scoring_engine_key` 的標籤，其對應題目的計分邏輯由 esgchain-ai 計算，問卷設計時須預留數值填入欄位
4. **E/S/G 分類對應**：
   - **E（環境）**：`esg.env.*`、`esg.sc_env.*`、`iso26k.env.*`、`prod_comp.cbam.*`、`prod_comp.eudr.*`
   - **S（社會）**：`esg.labor.*`、`esg.ohs.*`、`esg.comm.*`、`iso26k.hr.*`、`iso26k.labor.*`、`iso26k.consumer.*`、`iso26k.comm.*`
   - **G（治理）**：`esg.gov.*`、`iso26k.gov.*`、`iso26k.fair.*`、`iso20400.*`
5. **法規合規（Product-Compliance）**：獨立於 E/S/G 計分之外，作為門檻條件（pass/fail）而非加權分數
