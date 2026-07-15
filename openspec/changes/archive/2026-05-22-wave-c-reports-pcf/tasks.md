# Tasks: Wave C — Reports & PCF Async

## C1 M5 PCF 非同步化

- [x] C1.1 Migration：建立 pcf_tasks 表（id/celery_task_id/supplier_ids/status/progress/result_count）
- [x] C1.2 PcfTaskController：POST /pcf/calculate（建 task，dispatch Celery，回 202 + task_id）
- [x] C1.3 PcfTaskController：GET /pcf/status/{task_id}（查 pcf_tasks + Celery 狀態同步）
- [x] C1.4 PcfTaskController：GET /pcf/results/{id}（取計算結果含 scope3_breakdown）
- [x] C1.5 更新 FastAPI scoring_tasks.py：calculate_pcf_batch 支援 supplier_ids + product_ids，結果寫回 pcf_records 並 callback Laravel 更新 pcf_tasks 狀態
- [x] C1.6 更新 routes/api.php：新增 /pcf/* 路由

## C2 M7 Scope 3 Report

- [x] C2.1 FastAPI：新增 GET /ai/v1/reports/scope3?year= 彙總端點（讀 pcf_records，對應 GHG Protocol 15 類別）
- [x] C2.2 ReportController：GET /reports/scope3（代理 FastAPI + 整合回應格式）
- [x] C2.3 安裝 maatwebsite/excel（Laravel）
- [x] C2.4 ReportController：GET /reports/scope3/export?format=xlsx（生成 Excel 三工作表）
- [x] C2.5 安裝 barryvdh/laravel-dompdf 或 knplabs/snappy（PDF 匯出）
- [x] C2.6 ReportController：GET /reports/scope3/export?format=pdf（生成 PDF）
- [x] C2.7 更新 routes/api.php：新增 /reports/* 路由
