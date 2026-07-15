## 1. 資料庫 Migration

- [x] 1.1 建立 `create_customers_table` migration：id/code/name/country_code/eori_number/vat_number/customer_type(enum)/address/website/notes/status(enum)/created_by/timestamps/softDeletes
- [x] 1.2 建立 `create_customer_contacts_table` migration：id/customer_id FK/name/email(unique)/phone/title/is_primary/timestamps
- [x] 1.3 建立 `alter_trade_goods_add_customer_id` migration：加 customer_id UUID NULL FK + UNIQUE(customer_id, product_code)
- [x] 1.4 建立 `alter_shipments_add_customer_fields` migration：加 customer_id UUID NULL FK + customer_po_no VARCHAR(100) NULL

## 2. Laravel Models

- [x] 2.1 建立 `Customer` Model（HasUuids、softDeletes、fillable、casts、hasMany contacts/tradeGoods/shipments）
- [x] 2.2 建立 `CustomerContact` Model（HasUuids、fillable、belongsTo Customer）
- [x] 2.3 修改 `TradeGood` Model：加 customer_id fillable、belongsTo Customer
- [x] 2.4 修改 `Shipment` Model：加 customer_id/customer_po_no fillable、belongsTo Customer

## 3. Form Requests & 驗證

- [x] 3.1 建立 `StoreCustomerRequest`：name/code/country_code/customer_type 必填，eori_number 條件驗證（EU 成員國格式 + 前兩碼一致性），code unique
- [x] 3.2 建立 `UpdateCustomerRequest`：same rules，code unique ignore self
- [x] 3.3 建立 `StoreCustomerContactRequest`：name/email 必填，email unique
- [x] 3.4 修改 `StoreShipmentRequest`（或對應 Request）：加 customer_id nullable uuid、customer_po_no nullable string

## 4. Services

- [x] 4.1 建立 `CustomerService`：`list(filters)`、`create(data)`、`update(customer, data)`、`destroy(customer)`
- [x] 4.2 建立 `CustomerContactService`：`addContact(customer, data)`（is_primary 互斥邏輯）、`removeContact(contact)`
- [x] 4.3 修改 `ShipmentService::create()`：加 target_market 自動推導邏輯（country_code → region）、agent 警告產生

## 5. Controllers & Routes

- [x] 5.1 建立 `CustomerController`：index / store / show / update / destroy
- [x] 5.2 建立 `CustomerContactController`：store / destroy
- [x] 5.3 在 `routes/api.php` 加入：`/api/v1/customers` CRUD + `/api/v1/customers/{id}/contacts`
- [x] 5.4 修改 `TradeGoodController`：store/update 接受 customer_id；list response 加 customer.name
- [x] 5.5 修改 `ShipmentController`：store/update 接受 customer_id/customer_po_no；response 加 customer、warnings

## 6. 前端 API 模組

- [x] 6.1 建立 `esgchain-web/src/api/modules/customers.ts`：Customer / CustomerContact 介面定義、customersApi（list/get/create/update/destroy）、customerContactApi（create/destroy）

## 7. 前端頁面

- [x] 7.1 建立 `CustomerMdmView.vue`（/settings/customers，admin 角色）：列表（search/status/type 篩選）、新增/編輯 Modal（含 EORI 欄位）、聯絡人子列表
- [x] 7.2 修改 `TradeGoodsView.vue`：新增/編輯 Modal 加入客戶選擇下拉（CustomerSelect）、顯示客戶名稱欄位
- [x] 7.3 修改 `ShipmentsView.vue` / `ShipmentDetailView.vue`：加入客戶選擇、customer_po_no 輸入、agent 警告提示
- [x] 7.4 在 `AppSidebar.vue` settings-group 加入 `{ name: 'customers', path: '/settings/customers', label: '客戶主檔', roles: ['admin'] }`
- [x] 7.5 在 `router/index.ts` 加入 `/settings/customers` 路由

## 8. Docker 同步與驗證

- [x] 8.1 docker cp + restart esgchain-api，執行 migration
- [x] 8.2 curl 驗證 Customer CRUD API（POST/GET/PATCH/DELETE）
- [x] 8.3 curl 驗證 EORI 條件驗證（EU 客戶缺少 EORI 警告、格式錯誤 422）
- [x] 8.4 curl 驗證 Shipment 建立時 target_market 自動帶入、agent 警告
- [x] 8.5 docker cp esgchain-web，瀏覽器驗證 CustomerMdmView、TradeGoods/Shipments 表單整合
