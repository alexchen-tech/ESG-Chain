### Requirement: 原料溯源紀錄可附加運輸資訊

RawMaterialOrigin SHALL 新增選填的 `transport_mode`（值域 `sea`/`air`/`road`/`rail`/`multimodal`/`unknown`）與 `transport_distance_km`（數值），供批次原料溯源紀錄附加運輸方式與距離資訊。

#### Scenario: 原料溯源紀錄填寫運輸資訊

- **WHEN** 使用者為某批次的原料溯源紀錄填寫 `transport_mode = 'sea'` 與 `transport_distance_km = 8000`
- **THEN** 系統儲存並可於批次護照輸出中顯示該運輸資訊

#### Scenario: 原料溯源紀錄未填寫運輸資訊

- **WHEN** 既有原料溯源紀錄未填寫 `transport_mode`/`transport_distance_km`
- **THEN** 系統視為缺資料而非錯誤，相關欄位回傳 null
