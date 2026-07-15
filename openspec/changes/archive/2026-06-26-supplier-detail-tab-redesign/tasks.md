## 1. 準備與結構重構

- [x] 1.1 在 SupplierDetailView.vue 中將 `tabs` 陣列重定義為 4 個 Tab：`overview`（概況）、`sustain`（永續績效）、`comply`（合規管理）、`facility`（設施 & 聯絡）
- [x] 1.2 將 `activeTab` 初始值改為 `'overview'`
- [x] 1.3 確認 `loadedTabs` Set 的 lazy load 邏輯與新 Tab key 一致

## 2. 概況 Tab

- [x] 2.1 將風險評估 scorecard（E/S/G/GP 四維度）移入 `v-show="activeTab === 'overview'"` 容器，置於 Tab 內容區最上方
- [x] 2.2 在概況 Tab 中建立 detail-grid，整合識別資訊（供應商代碼、名稱、國家、幣別）
- [x] 2.3 將產業分類欄位（SASB 產業、次產業）移入概況 Tab 的 detail-grid，移除「產業分類」獨立 Tab
- [x] 2.4 將管理歸屬欄位（負責採購員、所屬採購群組）移入概況 Tab 的 detail-grid，移除「管理歸屬」獨立 Tab
- [x] 2.5 瀏覽器驗證：概況 Tab 預設顯示，風險評估在首位，所有識別欄位正確呈現

## 3. 永續績效 Tab

- [x] 3.1 建立 `v-show="activeTab === 'sustain'"` 容器
- [x] 3.2 將問卷記錄 section 移入容器
- [x] 3.3 將 Disclosure Profile section 移入同一容器（修正原本夾在 always-visible section 之間的 DOM 錯誤位置）
- [x] 3.4 確認 Disclosure Profile 的 `v-show` 條件更新為 `activeTab === 'sustain'`（若原本有獨立的 `v-show` 判斷需移除）
- [x] 3.5 瀏覽器驗證：切換至永續績效 Tab，問卷記錄與 Disclosure Profile 均正確顯示；切換至其他 Tab 時兩者均隱藏

## 4. 合規管理 Tab

- [x] 4.1 建立 `v-show="activeTab === 'comply'"` 容器
- [x] 4.2 將「供應材料清單」section（BOM → required_doc_types）移入容器，置於上方
- [x] 4.3 將「合規文件」section（actual doc upload status）移入容器，緊接供應材料清單之下
- [x] 4.4 確認原本兩個 section 的 API 呼叫邏輯整合至 `loadedTabs` lazy load（切換至 comply Tab 時才觸發）
- [x] 4.5 瀏覽器驗證：供應材料清單在上、合規文件在下；切換至其他 Tab 時兩者均隱藏

## 5. 設施 & 聯絡 Tab

- [x] 5.1 建立 `v-show="activeTab === 'facility'"` 容器
- [x] 5.2 將聯絡人 section、地址/網站欄位移入容器
- [x] 5.3 將生產設施 section 移入容器
- [x] 5.4 將申報記錄 section 移入容器
- [x] 5.5 將狀態歷程 Timeline section 移入容器
- [x] 5.6 瀏覽器驗證：設施 & 聯絡 Tab 正確顯示所有設施相關資訊

## 6. 清理與最終驗證

- [x] 6.1 移除原本 always-visible 的 section wrapper（確認沒有任何 section 在 Tab 切換後仍然顯示在錯誤位置）
- [x] 6.2 移除多餘的 CSS 類別（若有因重構而不再使用的樣式）
- [x] 6.3 完整 smoke test：以 admin 帳號逐一切換 4 個 Tab，確認內容正確且無空白 Tab
- [x] 6.4 以 sustain / comply / buyer 角色各登入一次，確認各自的工作情境 Tab 顯示正常
- [x] 6.5 docker cp 同步至容器並驗證 Vite HMR 更新正常
