## Context

`SupplierDisclosure`/`SupplierDisclosureField` 機制（本工作階段稍早已補齊 `single_choice` 支援）本來就適合表達「這個供應商有沒有填某個 KPI 值」，稽核供應商碳盤查覆蓋度不需要新資料模型，只需要：(1) 補上缺的 `ghg.scope3_mt_co2e` 欄位，(2) 一個依供應商彙總「三個 scope 欄位各自填了沒」的查詢視圖。

## Goals / Non-Goals

**Goals：**
- 中心廠可以一眼看出「這家供應商盤查做到範疇幾」（未盤查/僅範疇一二/含範疇三）
- 沿用既有揭露欄位機制，供應商填報體驗不變（KPI 填報頁自動多一個範疇三欄位）

**Non-Goals：**
- 不做第三方查證機構、盤查方法論（ISO 14064/GHG Protocol）、邊界說明欄位——經使用者確認這次不做，需要時再開新 change 追加
- 不做 ESG-Chain 內部精算供應商排放量（那是活動資料推送外部引擎的範疇，不是這次功能）

## Decisions

**1. 「已填報」的判斷：`SupplierDisclosure` 該 slug 在該期間存在紀錄且 `numeric_value` 非 null**

不新增任何布林欄位，直接用既有資料存在性判斷，維持資料來源單一。

**2. 覆蓋度分級：未盤查 / 僅範疇一二 / 含範疇三**

依供應商在指定期間（或最近一次填報）是否填了 `ghg.scope1_mt_co2e`／`ghg.scope2_mt_co2e`／`ghg.scope3_mt_co2e` 三個欄位，分成三級標示，作為稽核視圖的主要狀態欄位。

## Migration Plan

1. Seeder/migration 新增 `ghg.scope3_mt_co2e` 揭露欄位
2. 新增覆蓋度彙總 Service/API
3. 前端新增稽核視圖頁面
4. 部署後以既有 306 筆 scope1/scope2 真實填報資料驗證覆蓋度分級正確

## Open Questions

（無，第三方查證/方法論欄位留待未來需要時另立 change）
