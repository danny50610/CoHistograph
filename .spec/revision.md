# CoHistograph Spec

## 專案介紹

CoHistograph 是一個協作式歷史事件知識圖譜平台，讓使用者能定義、管理並探索歷史事件之間的關聯網路。

### 技術架構

- **後端框架**：Laravel 12 (PHP 8.5)
- **雙資料庫架構**：
  - **PostgreSQL**：儲存圖譜 schema 的後設資料（VertexType、EdgeType 及其 Property 定義）
  - **Apache AGE**：PostgreSQL 的圖資料庫擴充套件，儲存實際的頂點（Vertex）與邊（Edge）資料，使用 Cypher-like 語法查詢
- **前端**：Laravel Blade + Bootstrap 5 + Vite
- **授權**：Laratrust（角色與權限管理）
- **測試**：PHPUnit + Feature Tests + Unit Tests

### 核心概念

系統採用「先定義 schema、再輸入資料」的設計：

1. **VertexType**（頂點類型）：定義圖中節點的種類，例如「人物」、「事件」、「地點」。每個 VertexType 有對應的 `age_label_name` 作為 Apache AGE 的標籤。
2. **VertexProperty**（頂點屬性）：定義某 VertexType 擁有的屬性欄位，例如「人物」有「姓名」、「生卒年」等。
3. **EdgeType**（邊類型）：定義兩個 VertexType 之間的關係種類，例如「人物」→「參與」→「事件」。支援正向名稱（name）與反向名稱（reverse_name）。
4. **EdgeProperty**（邊屬性）：定義某 EdgeType 擁有的屬性欄位。

實際的頂點與邊資料儲存在 Apache AGE 圖資料庫中，透過 `danny50610/laravel-apache-age-driver` 套件進行 Cypher 查詢。

### 目錄結構重點

```
app/
├── Http/Controllers/
│   ├── GraphSchema/     # Schema 管理（VertexType, EdgeType, Property CRUD）
│   └── Graph/           # 圖資料瀏覽（Vertex 列表與詳情）
├── Models/              # Eloquent models
├── Rules/GraphSchema/   # 自訂驗證規則（AGE label/property 命名規則）
├── Enums/               # PropertyType enum（INTEGER, FLOAT, BOOLEAN, STRING, DATE, TIMESTAMPTZ）
└── Services/            # MenuService
database/
├── migrations/          # 關聯式 DB schema
└── migrations-age/      # Apache AGE 圖 schema 初始化
resources/views/         # Blade 模板
routes/web.php           # 路由定義
tests/
├── Unit/                # 命名規則等單元測試
└── Feature/             # Controller CRUD 整合測試
```

### 開發慣例

- 所有需要修改圖譜 schema 的操作需要 `graph-schema.manage` 權限
- AGE label 與 property 命名規則：小寫英數字加底線，最長 64 字元
- 驗證邏輯使用 Form Request 類別
- 測試使用 `DatabaseTransactions` trait，並透過 Factory 建立測試資料

### 角色與權限

現有角色與權限定義於 `config/cohistograph/roles-and-permissions.php`，透過 `php artisan app:apply-role-and-permission-command` 套用。

現有權限：

| 權限 | 說明 |
|---|---|
| `user.manage` | 管理會員 |
| `role.manage` | 管理角色 |
| `graph-schema.manage` | 管理 Graph Schema |

Revision 功能

**角色：**
- `admin`（管理員）：擁有以上全部權限

---

## Features

### Revision（修訂）

讓登入的使用者能提交對圖資料的變更建議，由管理員審查後才正式套用，以避免惡意或錯誤的編輯直接影響資料庫。

---

#### 核心概念

使用者提交的是一份**修訂（Revision）**，內含一或多個操作項目（RevisionAction）。每個 RevisionAction 描述一個原子操作。Revision 採全有或全無原則——管理員接受後，所有 RevisionAction 一起套用；退回則全部不套用。

---

#### 權限

新增的權限：

| 權限 | 說明 |
|---|---|
| `revision.review` | 審核、接受或退回使用者提交的 Revision |

`admin`（管理員）: 加上 `revision.review` 權限

#### 支援的變動類型

每個 RevisionAction 的 `action` 欄位為以下十種之一：

| action | 對象 | 說明 |
|---|---|---|
| `create_vertex` | Vertex | 在指定 VertexType 下新增一個頂點（無屬性） |
| `delete_vertex` | Vertex | 刪除現有頂點 |
| `create_edge` | Edge | 在指定 EdgeType 下新增一條邊，連接兩個頂點（無屬性） |
| `delete_edge` | Edge | 刪除現有邊 |
| `create_vertex_property` | VertexProperty 值 | 在現有頂點上設定某 property 的值 |
| `update_vertex_property` | VertexProperty 值 | 修改現有頂點某 property 的現有值 |
| `delete_vertex_property` | VertexProperty 值 | 移除現有頂點某 property 的值 |
| `create_edge_property` | EdgeProperty 值 | 在現有邊上設定某 property 的值 |
| `update_edge_property` | EdgeProperty 值 | 修改現有邊某 property 的現有值 |
| `delete_edge_property` | EdgeProperty 值 | 移除現有邊某 property 的值 |

---

#### Revision 狀態流程

```
┌──────────────────────────┐
↓                          │
draft → pending_review → rejected
              ↓
           approved
```

- **draft**：全新草稿，尚未提交過
- **pending_review**：通過提交驗證，等待管理員審查
- **approved**：管理員接受，變更已套用至 Apache AGE（終態）
- **rejected**：管理員退回，使用者可修改後再次提交

---

#### 驗證規則

整份 Revision 驗證發生在兩個時間點，規則相同：

1. **使用者提交時**：驗證不通過則無法送出，Revision 停在 draft 狀態，並將本次驗證錯誤帶回詳情頁顯示
2. **管理員進入審核頁時**：重新驗證，若有問題則鎖定「接受」按鈕（仍可退回）

**驗證內容**（需考慮 Revision 內部依賴，即前面的 RevisionAction 可作為後面 RevisionAction 的前提）：

- `delete_vertex` / `*_vertex_property`：目標 Vertex 必須存在（在 AGE 中，或已在同份 Revision 的前面 action 中被 `create_vertex`）
- `delete_vertex`：該頂點在 AGE 中所有相關的 edge 必須都已在同份 Revision 的前面 action 中被 `delete_edge`，否則驗證失敗
- `delete_edge` / `*_edge_property`：目標 Edge 必須存在（同上）
- `create_edge`：start vertex 與 end vertex 都必須存在（在 AGE 中，或在同份 Revision 前面 action 中被建立）
- `create_vertex`：`vertex_type_label` 必須對應到現有的 VertexType
- `create_edge`：`edge_type_label` 必須對應到現有的 EdgeType，且 start/end vertex 的 VertexType 必須符合 EdgeType 定義
- `*_property`：`age_property_name` 必須對應到該 vertex / edge 所屬 VertexType / EdgeType 下的 VertexProperty / EdgeProperty
- `create_*_property` / `update_*_property`：`value` 的型別須符合該 Property 的 `age_property_type`
- `create_*_property`：目標 vertex / edge 上該 property 必須尚無值（在 AGE 中，且同份 Revision 前面 action 未 `create_*_property` 同一個 property）
- `update_*_property`：目標 vertex / edge 上該 property 必須已有值（在 AGE 中有值，或同份 Revision 前面 action 已 `create_*_property` 同一個 property）
- `delete_*_property`：同 `update_*_property`，必須已有值才能刪除；`value` 欄位必須為 `null`

驗證失敗時，回傳哪些 RevisionAction 有問題及原因。

---

#### 資料模型

**`revisions` 資料表**

| 欄位 | 型別 | 說明 |
|---|---|---|
| id | bigint PK | |
| title | string | 必填，使用者自訂的標題 |
| description | text nullable | 選填，說明這份變更的目的 |
| status | string | `draft`, `pending_review`, `approved`, `rejected` |
| user_id | FK → users | 擁有者（用於授權） |
| timestamps | | created_at, updated_at |

**`revision_reviews` 資料表**

| 欄位 | 型別 | 說明 |
|---|---|---|
| id | bigint PK | |
| revision_id | FK → revisions | |
| actor_user_id | FK → users | 執行此動作的操作者（approved/rejected 為審核者） |
| action | string | `approved`, `rejected` |
| comment | text nullable | 退回時必填 |
| actions_snapshot | jsonb nullable | 僅 `rejected` 時填入，儲存當次提交的完整 `revision_actions` 陣列快照，每筆 action 為 jsonb 物件 |
| timestamps | | created_at, updated_at |

**`revision_actions` 資料表**

| 欄位 | 型別 | 說明 |
|---|---|---|
| id | bigint PK | |
| revision_id | FK → revisions | |
| order | integer | 清單中的順序，用於內部依賴驗證 |
| action | string | 十種 action 之一（見上表） |
| target_age_id | bigint nullable | 操作對象在 AGE 中的 graphid（與 `target_ref_order` 互斥） |
| target_ref_order | integer nullable | 引用同 Revision 內某 `create_vertex`/`create_edge` action 的 order（與 `target_age_id` 互斥） |
| vertex_type_label | string nullable | `create_vertex` 時使用，對應 `vertex_types.age_label_name` |
| edge_type_label | string nullable | `create_edge` 時使用，對應 `edge_types.age_label_name` |
| start_vertex_age_id | bigint nullable | `create_edge` 時，start vertex 的 AGE graphid（與 `start_vertex_ref_order` 互斥） |
| start_vertex_ref_order | integer nullable | `create_edge` 時，start vertex 引用同 Revision 內 `create_vertex` action 的 order（與 `start_vertex_age_id` 互斥） |
| end_vertex_age_id | bigint nullable | `create_edge` 時，end vertex 的 AGE graphid（與 `end_vertex_ref_order` 互斥） |
| end_vertex_ref_order | integer nullable | `create_edge` 時，end vertex 引用同 Revision 內 `create_vertex` action 的 order（與 `end_vertex_age_id` 互斥） |
| age_property_name | string nullable | `*_property` 操作時使用，對應 `vertex_properties.age_property_name` 或 `edge_properties.age_property_name` |
| value | text nullable | `create_*_property` / `update_*_property` 時使用，套用時依 `age_property_type` 轉型 |
| timestamps | | created_at, updated_at |

---

#### 頁面與路由

**Navbar 入口：**

| Navbar 位置 | 文字 | 目標路由 |
|---|---|---|
| 右側「{使用者名稱}」dropdown | 我的修訂 | `revisions.index` |
| 右側「網站管理」dropdown | 修訂審核 | `admin.revisions.index` |

**以路由層定義的頁面：**

| 路由 | 頁面名稱 | 說明 | 權限 |
|---|---|---|---|
| `GET /revisions` | 我的修訂列表頁 | 顯示目前登入使用者的所有 Revision | 登入使用者 |
| `GET /revisions/create` | 新增修訂頁 | 建立新的 Revision 草稿 | 登入使用者 |
| `GET /revisions/{revision}` | 單一修訂詳情／編輯頁 | 顯示單一 Revision；本人在 draft 狀態可編輯，管理員經由此頁進入時一律唯讀 | 登入使用者（本人或管理員） |
| `GET /admin/revisions` | 管理員修訂審核列表頁 | 顯示全部 Revision，供管理員進入審核 | `revision.review` 權限 |
| `GET /admin/revisions/{revision}` | 管理員修訂審核頁 | 顯示單一 Revision、驗證結果與審核操作 | `revision.review` 權限 |

**非頁面型操作路由：**

| 路由 | 說明 | 權限 |
|---|---|---|
| `PUT /revisions/{revision}` | 更新草稿內容（title、description、actions 陣列整份覆蓋） | 本人（draft 狀態） |
| `POST /revisions/{revision}/submit` | 提交審核（觸發驗證） | 本人（draft 狀態） |
| `POST /revisions/{revision}/reopen` | 重新開啟編輯（rejected → draft） | 本人（rejected 狀態） |
| `DELETE /revisions/{revision}` | 刪除草稿 | 本人（draft 狀態） |
| `POST /admin/revisions/{revision}/approve` | 接受並套用（僅限 pending_review） | `revision.review` 權限 |
| `POST /admin/revisions/{revision}/reject` | 退回（需填理由，僅限 pending_review） | `revision.review` 權限 |

---

#### 我的修訂列表頁

- **對應路由**：`GET /revisions`
- **頁面目標**：讓登入使用者查看自己建立的所有 Revision，並快速進入建立新草稿、繼續編輯草稿、查看送審結果。

**頁面結構：**

1. 頁首區
  - 頁面標題：`我的修訂`
  - 主要按鈕：`新增修訂`

2. 卡片列表區
  - 排序：預設依 `updated_at DESC`
  - 使用卡片列表呈現
  - 每張卡片代表一筆 Revision
  - 卡片採直式資訊堆疊，需適合手機單欄瀏覽
  - 點擊整張卡片時，導向 `GET /revisions/{revision}`

**卡片內容：**

| 區塊 | 說明 |
|---|---|
| 卡片標題列 | 顯示 `title` |
| 狀態標籤 | 顯示 `draft`、`pending_review`、`rejected`、`approved` 的 badge |
| 摘要資訊 | 顯示操作數量、最後更新時間 |
| 次要資訊 | 顯示最近一次審核時間；若尚無審核紀錄則留空 |
| 點擊提示 | 顯示此卡片可點擊進入詳情頁 |

**卡片版面建議順序：**

1. 第一行：標題 + 狀態 badge
2. 第二行：`X 個操作`
3. 第三行：`最後更新時間`
4. 第四行：`最近一次審核時間`
5. 最下方：整張卡片可點擊的提示文字或箭頭圖示

- 手機版所有資訊維持單欄直向排列
- 桌機版可將摘要資訊與次要資訊併排，但不改變資訊順序
- 不在卡片內放置個別操作按鈕

**狀態顯示規則：**

| 狀態 | 顯示文字 | 視覺建議 |
|---|---|---|
| `draft` | 草稿 | 灰色 badge |
| `pending_review` | 待審核 | 黃色 badge |
| `rejected` | 已退回 | 紅色 badge |
| `approved` | 已接受 | 綠色 badge |

**卡片點擊規則：**

| 狀態 | 點擊卡片後進入的頁面 |
|---|---|
| `draft` | 單一修訂詳情／編輯頁 |
| `pending_review` | 單一修訂詳情／編輯頁（唯讀） |
| `rejected` | 單一修訂詳情／編輯頁 |
| `approved` | 單一修訂詳情／編輯頁（唯讀） |

- 進入詳情頁後，才依 Revision 狀態顯示可執行操作
- 列表頁本身不提供 `刪除`、`重新開啟` 等按鈕

**空狀態：**

- 當使用者尚未建立任何 Revision 時，顯示空狀態訊息：`目前還沒有任何修訂`
- 空狀態下仍顯示 `新增修訂` 按鈕

**分頁：**

- 卡片列表需要分頁
- 每頁筆數沿用系統既有列表頁慣例

**不在此頁處理的內容：**

- 不做搜尋功能
- 不直接編輯 RevisionAction
- 不直接顯示完整驗證錯誤明細
- 不提供管理員審核操作

---

#### 新增修訂頁

- **對應路由**：`GET /revisions/create`
- **頁面目標**：讓登入使用者建立新的 Revision 草稿，填入基本資料後進入單一修訂詳情頁繼續編輯內容。

**頁面結構：**

1. 頁首區
  - 返回入口：返回 `我的修訂`
  - 頁面標題：`新增修訂`

2. 基本資料表單區
  - 輸入 `title`
  - 輸入 `description`

3. 頁面操作區
  - 顯示建立草稿相關操作

**表單欄位：**

| 欄位 | 說明 |
|---|---|
| `title` | 必填，作為此 Revision 的主要標題 |
| `description` | 選填，描述這份修訂的目的 |

**操作規則：**

| 操作 | 說明 |
|---|---|
| `建立草稿` | 建立新的 Revision，狀態為 `draft` |
| `取消` | 返回 `我的修訂`，不建立任何資料 |

- `建立草稿` 成功後，導向 `GET /revisions/{revision}`
- 進入詳情頁後，再編輯 Action 清單與其他內容

**建立成功後的初始狀態：**

- `status` 預設為 `draft`
- `revision_actions` 初始為空
- `revision_reviews` 初始為空

**驗證規則：**

- 僅驗證基本資料表單
- 不在此頁驗證 RevisionAction 的內容

**不在此頁處理的內容：**

- 不直接建立任何 RevisionAction
- 不提供提交審核操作
- 不顯示審核歷程

---

#### 單一修訂詳情頁

- **對應路由**：`GET /revisions/{revision}`
- **頁面目標**：讓使用者查看單一 Revision 的完整內容，並在可編輯狀態下編輯草稿、提交審核、重新開啟或刪除。

**頁面模式：**

| Revision 狀態 | 頁面模式 |
|---|---|
| `draft` | 本人可編輯；管理員唯讀 |
| `pending_review` | 唯讀 |
| `rejected` | 唯讀 |
| `approved` | 唯讀 |

**頁面結構：**

1. 頁首區
  - 返回入口：返回 `我的修訂`
  - 頁面標題：顯示 `title`；
  - 狀態 badge：顯示目前 Revision 狀態

2. 標題下方操作區
  - 依 Revision 狀態顯示可用操作

3. 基本資料區
  - 顯示 `title`
  - 顯示 `description`
  - 顯示建立者
  - 顯示建立時間
  - 顯示最後更新時間

4. Action 清單區
  - 以卡片列表呈現 `revision_actions`
  - 每張卡片代表一個 action
  - 依 `order ASC` 排序
  - `revision_actions` 的新增、編輯、刪除、排序皆在此區完成

5. 歷程區
  - 顯示 `revision_reviews` 紀錄
  - 依時間倒序顯示退回、接受紀錄

**Action 卡片內容：**

| 區塊 | 說明 |
|---|---|
| 標題列 | 顯示 `#order` 與 action 類型名稱 |
| 摘要資訊 | 顯示此次 action 的目標摘要 |
| 詳細資料 | 顯示該 action 的欄位內容 |

**Action 卡片版面建議順序：**

1. 第一行：`#order` + action 類型
2. 第二行：目標摘要
3. 第三行開始：詳細欄位資料

- 手機版所有 action 卡片維持單欄排列
- 若 action 很多，不使用表格，避免橫向捲動

**Action 摘要格式規則：**

| action | 摘要格式 |
|---|---|
| `create_vertex` | `新增頂點：{vertex_type_label}` |
| `delete_vertex` | `刪除頂點：{target}` |
| `create_edge` | `新增邊：{start_vertex} - {edge_type_label} - {end_vertex}` |
| `delete_edge` | `刪除邊：{target}` |
| `create_vertex_property` | `新增頂點屬性：{target}.{age_property_name} = {value}` |
| `update_vertex_property` | `修改頂點屬性：{target}.{age_property_name} = {value}` |
| `delete_vertex_property` | `刪除頂點屬性：{target}.{age_property_name}` |
| `create_edge_property` | `新增邊屬性：{target}.{age_property_name} = {value}` |
| `update_edge_property` | `修改邊屬性：{target}.{age_property_name} = {value}` |
| `delete_edge_property` | `刪除邊屬性：{target}.{age_property_name}` |

- `{target}` 應盡量使用使用者可辨識的名稱，而不是直接顯示 AGE graphid
- 若 action 引用同一份 Revision 內新建立的 vertex / edge，摘要應顯示可讀的暫時名稱，而非僅顯示 `ref_order`
- 若某個值目前無法轉成友善名稱，才退回顯示技術識別值
- 摘要內容需足以讓使用者在不展開 card 的情況下理解這個 action 做了什麼

**Action 清單區互動規則：**

| 互動 | 說明 |
|---|---|
| `新增 action` | 放在 Action 清單區標題旁，點擊後開啟 modal，用來加入新的 RevisionAction |
| `編輯 action` | 放在 action card 內，點擊後開啟 modal 編輯該 action |
| `刪除 action` | 放在 action card 內，刪除單一 RevisionAction |
| `調整順序` | 放在 action card 內，支援上移、下移 |

- `draft` 狀態下才顯示上述互動控制項
- `pending_review`、`rejected`、`approved` 狀態下，Action 清單區僅供檢視
- 管理員經由 `GET /revisions/{revision}` 進入此頁時，即使 Revision 為 `draft` 也僅供檢視
- Action 清單區不另外拆成獨立頁面
- `新增 action` 與 `編輯 action` 共用 modal 互動模式
- modal 會先決定 action 類型，再顯示該類型專屬的表單欄位
- 不使用單一萬用表單同時承載所有 action 類型
- 儲存 Action 清單的結果，仍透過整份 Revision 的 `PUT /revisions/{revision}` 完成

**Action modal 流程：**

1. 使用者點擊 `新增 action` 或某張 card 的 `編輯 action`
2. 開啟 modal
3. 若為新增 action：先選擇 action 類型
4. modal 根據 action 類型顯示對應欄位
5. 使用者填寫或修改欄位後，按下 `儲存 action`
6. modal 關閉，回到 Action 清單區
7. 更新後的 action card 立即反映最新摘要內容

**Action 類型選擇規則：**

| 分組 | 可選 action |
|---|---|
| Vertex 操作 | `create_vertex`、`delete_vertex` |
| Edge 操作 | `create_edge`、`delete_edge` |
| Vertex 屬性操作 | `create_vertex_property`、`update_vertex_property`、`delete_vertex_property` |
| Edge 屬性操作 | `create_edge_property`、`update_edge_property`、`delete_edge_property` |

- 新增 action 時，modal 第一步先顯示上述分組
- 每個 action 選項需顯示中文名稱與簡短說明
- 使用固定展開的分組列表呈現所有 action，不使用兩個下拉清單
- 使用者直接在分組區塊中選擇具體 action
- 不使用可收合區塊隱藏分組內容
- 不以一長串無分組清單呈現全部 10 種 action
- 手機版需維持單欄排列，避免同時顯示過多選項

**Action modal 規則：**

| 情境 | 規則 |
|---|---|
| 新增 action | 需先選 action 類型後才能進入表單 |
| 編輯 action | 直接開啟既有 action 類型對應的表單 |
| 切換 action 類型 | 僅限新增 action 時發生 |
| 關閉 modal | 不自動儲存未提交的變更 |

- modal 標題需清楚區分 `新增 action` 與 `編輯 action`
- modal 底部操作至少包含 `取消` 與 `儲存 action`
- 若 modal 內欄位驗證失敗，錯誤訊息顯示在 modal 內，不關閉 modal

**編輯 action 規則：**

1. 使用者從某張 action card 點擊 `編輯 action`
2. 開啟該 action 類型對應的 modal 表單
3. modal 內預先帶入此 action 目前的欄位值
4. 使用者修改欄位後，按下 `儲存 action`
5. 儲存成功後關閉 modal，原 action card 就地更新

| 項目 | 規則 |
|---|---|
| 可編輯範圍 | 僅可編輯該 action 類型本身需要的欄位 |
| action 類型 | 編輯既有 action 時不可切換 action 類型 |
| order | 不在編輯 modal 內直接修改；順序調整透過 card 的上移、下移完成 |
| 儲存結果 | 更新該 action 的摘要資訊與詳細資料 |

- 編輯 action 時不重新顯示 action 類型選擇步驟
- 若使用者需要改成另一種 action 類型，應刪除原 action 後重新新增
- 若編輯後導致摘要內容改變，card 摘要需立即更新

**Blade 實作與元件重用規則：**

- 單一修訂詳情頁與 Action modal 以 Blade 模板實作
- 一般表單欄位優先重用既有的 input / select 元件
- Action 類型選擇區使用自訂 Blade 區塊，不使用既有 input / select 元件硬套
- 不同 action 類型的表單欄位以 Blade partial 拆分
- modal 內不同 action 類型的切換以少量 JavaScript 控制顯示與隱藏

| 區域 | 實作建議 |
|---|---|
| Revision 基本資料區 | 重用既有 input 元件；多行文字欄位可直接用原生 textarea |
| Action modal 一般欄位 | 重用既有 input / select 元件 |
| Action 類型選擇區 | 使用固定展開的分組列表 Blade partial |
| Action 類型專屬表單 | 依 action 類型或 action 分組拆成 Blade partial |

- 不要求為所有欄位建立新的通用 Blade 元件
- 若某欄位屬於單次使用且結構特殊，可直接寫在對應 partial 中
- 規格需以 Blade 可實作為前提，避免依賴 SPA 式前端架構

**Action modal / partial 拆分策略：**

- 使用一個共用的 Bootstrap modal 容器處理 `新增 action` 與 `編輯 action`
- modal 外框負責標題、關閉按鈕、取消按鈕與儲存按鈕
- modal 內容依 action 類型切換對應的 Blade partial
- 不為 10 種 action 分別建立 10 個完全獨立的大 modal

| 層級 | 拆分方式 |
|---|---|
| modal 容器 | 1 個共用 modal shell |
| 類型選擇區 | 1 個固定展開的 action 類型選擇 partial |
| Vertex 操作表單 | 1 組 partial |
| Edge 操作表單 | 1 組 partial |
| Vertex 屬性操作表單 | 1 組 partial |
| Edge 屬性操作表單 | 1 組 partial |

- `新增 action` 時先顯示類型選擇區，再切換到對應表單 partial
- `編輯 action` 時略過類型選擇區，直接載入該 action 對應 partial
- create / update / delete 的差異優先透過同組 partial 內的條件顯示處理
- 若某一組表單差異過大，再拆成該組內多個 partial

**Vertex 操作 modal 內容定義：**

適用 action：`create_vertex`、`delete_vertex`

| action | modal 目的 |
|---|---|
| `create_vertex` | 在指定 VertexType 下新增一個 vertex |
| `delete_vertex` | 刪除既有 vertex |

**`create_vertex` modal 欄位：**

| 欄位 | 說明 |
|---|---|
| `vertex_type_label` | 必填，選擇要建立在哪一種 VertexType 下 |

- 不顯示 `target_age_id`
- 不顯示 `target_ref_order`
- 建立後的摘要依 `新增頂點：{vertex_type_label}` 顯示

**`delete_vertex` modal 欄位：**

| 欄位 | 說明 |
|---|---|
| `target` | 必填，選擇要刪除的 vertex，實際對應 `target_age_id` 或 `target_ref_order` |

- `target` 應優先顯示使用者可辨識名稱，其次才是技術識別值
- 不顯示 `vertex_type_label`
- 不顯示 `value`
- 刪除後的摘要依 `刪除頂點：{target}` 顯示

**Vertex 操作 modal 共通規則：**

| 項目 | 規則 |
|---|---|
| 表單元件 | 優先使用既有 select 元件 |
| 表單複雜度 | 保持單欄、短表單 |
| 驗證錯誤 | 顯示在 modal 內，不關閉 modal |
| Blade partial | `create_vertex` 與 `delete_vertex` 可拆成同組 partial 或各自 partial |

- `create_vertex` 與 `delete_vertex` 都不需要 `value` 欄位
- `delete_vertex` 的 target 可對應既有 vertex，或同份 Revision 內前面建立的 vertex

**Edge 操作 modal 內容定義：**

適用 action：`create_edge`、`delete_edge`

| action | modal 目的 |
|---|---|
| `create_edge` | 在指定 EdgeType 下新增一條 edge |
| `delete_edge` | 刪除既有 edge |

**`create_edge` modal 欄位：**

| 欄位 | 說明 |
|---|---|
| `edge_type_label` | 必填，選擇要建立哪一種 EdgeType |
| `start_vertex` | 必填，選擇起點 vertex，可對應既有 vertex 或同份 Revision 內前面建立的 vertex |
| `end_vertex` | 必填，選擇終點 vertex，可對應既有 vertex 或同份 Revision 內前面建立的 vertex |

- `start_vertex` 實際對應 `start_vertex_age_id` 或 `start_vertex_ref_order`
- `end_vertex` 實際對應 `end_vertex_age_id` 或 `end_vertex_ref_order`
- 不顯示 `target_age_id`
- 不顯示 `value`
- 建立後的摘要依 `新增邊：{start_vertex} - {edge_type_label} - {end_vertex}` 顯示

**`delete_edge` modal 欄位：**

| 欄位 | 說明 |
|---|---|
| `target` | 必填，選擇要刪除的 edge，實際對應 `target_age_id` 或 `target_ref_order` |

- `target` 應優先顯示使用者可辨識的關係摘要，其次才是技術識別值
- 不顯示 `edge_type_label`
- 不顯示 `value`
- 刪除後的摘要依 `刪除邊：{target}` 顯示

**Edge 操作 modal 共通規則：**

| 項目 | 規則 |
|---|---|
| 表單元件 | 優先使用既有 select 元件 |
| 表單複雜度 | 維持單欄排列，但可包含 3 個主要選擇欄位 |
| 驗證錯誤 | 顯示在 modal 內，不關閉 modal |
| Blade partial | `create_edge` 與 `delete_edge` 可拆成同組 partial 或各自 partial |

- `create_edge` 需要依據 `edge_type_label` 限制 `start_vertex` 與 `end_vertex` 的可選範圍
- `delete_edge` 的 target 可對應既有 edge，或同份 Revision 內前面建立的 edge
- `create_edge` 與 `delete_edge` 都不需要 `value` 欄位

**Vertex 屬性操作 modal 內容定義：**

適用 action：`create_vertex_property`、`update_vertex_property`、`delete_vertex_property`

| action | modal 目的 |
|---|---|
| `create_vertex_property` | 在既有 vertex 上新增一個 property 值 |
| `update_vertex_property` | 修改既有 vertex 上的 property 值 |
| `delete_vertex_property` | 刪除既有 vertex 上的 property 值 |

**`create_vertex_property` / `update_vertex_property` modal 欄位：**

| 欄位 | 說明 |
|---|---|
| `target` | 必填，選擇目標 vertex，實際對應 `target_age_id` 或 `target_ref_order` |
| `age_property_name` | 必填，選擇該 vertex 可用的 property |
| `value` | 必填，輸入要建立或更新的 property 值 |

- `target` 可對應既有 vertex，或同份 Revision 內前面建立的 vertex
- `age_property_name` 的可選範圍需依目標 vertex 所屬 VertexType 動態限制
- `value` 的輸入方式需配合 property type
- 摘要分別依 `新增頂點屬性：{target}.{age_property_name} = {value}` 與 `修改頂點屬性：{target}.{age_property_name} = {value}` 顯示

**`delete_vertex_property` modal 欄位：**

| 欄位 | 說明 |
|---|---|
| `target` | 必填，選擇目標 vertex，實際對應 `target_age_id` 或 `target_ref_order` |
| `age_property_name` | 必填，選擇要刪除的 property |

- 不顯示 `value` 輸入欄位
- 摘要依 `刪除頂點屬性：{target}.{age_property_name}` 顯示

**Vertex 屬性操作 modal 共通規則：**

| 項目 | 規則 |
|---|---|
| 表單元件 | `target`、`age_property_name` 優先使用既有 select 元件；`value` 依型別決定輸入元件 |
| 欄位相依 | 需先選擇 `target`，才能正確決定 `age_property_name` 的可選範圍 |
| 驗證錯誤 | 顯示在 modal 內，不關閉 modal |
| Blade partial | 3 種 vertex property action 可拆成同組 partial，依 action 決定是否顯示 `value` |

- `create_vertex_property` 與 `update_vertex_property` UI 結構可共用，差異主要在文案與驗證規則
- `delete_vertex_property` 不輸入 `value`，實際上 `value` 應為 `null`
- 若 `target` 引用同份 Revision 內新建立的 vertex，仍需能正確列出該 VertexType 可用的 property

**Edge 屬性操作 modal 內容定義：**

適用 action：`create_edge_property`、`update_edge_property`、`delete_edge_property`

| action | modal 目的 |
|---|---|
| `create_edge_property` | 在既有 edge 上新增一個 property 值 |
| `update_edge_property` | 修改既有 edge 上的 property 值 |
| `delete_edge_property` | 刪除既有 edge 上的 property 值 |

**`create_edge_property` / `update_edge_property` modal 欄位：**

| 欄位 | 說明 |
|---|---|
| `target` | 必填，選擇目標 edge，實際對應 `target_age_id` 或 `target_ref_order` |
| `age_property_name` | 必填，選擇該 edge 可用的 property |
| `value` | 必填，輸入要建立或更新的 property 值 |

- `target` 可對應既有 edge，或同份 Revision 內前面建立的 edge
- `age_property_name` 的可選範圍需依目標 edge 所屬 EdgeType 動態限制
- `value` 的輸入方式需配合 property type
- 摘要分別依 `新增邊屬性：{target}.{age_property_name} = {value}` 與 `修改邊屬性：{target}.{age_property_name} = {value}` 顯示

**`delete_edge_property` modal 欄位：**

| 欄位 | 說明 |
|---|---|
| `target` | 必填，選擇目標 edge，實際對應 `target_age_id` 或 `target_ref_order` |
| `age_property_name` | 必填，選擇要刪除的 property |

- 不顯示 `value` 輸入欄位
- 摘要依 `刪除邊屬性：{target}.{age_property_name}` 顯示

**Edge 屬性操作 modal 共通規則：**

| 項目 | 規則 |
|---|---|
| 表單元件 | `target`、`age_property_name` 優先使用既有 select 元件；`value` 依型別決定輸入元件 |
| 欄位相依 | 需先選擇 `target`，才能正確決定 `age_property_name` 的可選範圍 |
| 驗證錯誤 | 顯示在 modal 內，不關閉 modal |
| Blade partial | 3 種 edge property action 可拆成同組 partial，依 action 決定是否顯示 `value` |

- `create_edge_property` 與 `update_edge_property` UI 結構可共用，差異主要在文案與驗證規則
- `delete_edge_property` 不輸入 `value`，實際上 `value` 應為 `null`
- 若 `target` 引用同份 Revision 內新建立的 edge，仍需能正確列出該 EdgeType 可用的 property

**驗證錯誤呈現規則：**

1. 單一修訂詳情頁需提供一個驗證摘要區塊
2. 整份 Revision 的驗證摘要僅在最近一次提交審核失敗後顯示
3. 當 Revision 驗證失敗時，摘要區塊顯示錯誤總數與錯誤清單
4. 每筆錯誤需對應到具體的 RevisionAction
5. 點擊錯誤項目時，可將畫面定位到對應的 action card

**驗證錯誤顯示層級：**

| 層級 | 顯示內容 |
|---|---|
| 頁面層級 | 在最近一次提交失敗後，顯示整份 Revision 不可提交，並列出錯誤摘要 |
| Action card 層級 | 標記該 action card 有錯誤，並顯示此 action 的錯誤訊息 |
| modal 層級 | 顯示欄位驗證錯誤，不關閉 modal |

- 頁面層級錯誤摘要放在標題下方操作區與基本資料區之間
- 有錯誤的 action card 需有明顯視覺標記
- 若錯誤屬於跨 action 相依問題，仍需至少指向一個主要 action card
- `draft` 狀態下，提交失敗後的驗證錯誤不阻止使用者繼續編輯，但會阻止再次提交審核，直到內容修正後重新提交
- `rejected` 狀態下，若重新開啟後再次進入編輯，也沿用相同錯誤呈現方式

**歷程區內容：**

| 紀錄類型 | 顯示內容 |
|---|---|
| `rejected` | 審核者、退回時間、退回理由 |
| `approved` | 審核者、接受時間 |

**標題下方操作規則：**

| 狀態 | 可顯示操作 |
|---|---|
| `draft` | `編輯`、`儲存變更`、`提交審核`、`刪除修訂` |
| `pending_review` | 無 |
| `rejected` | `重新開啟編輯`|
| `approved` | 無 |

- `編輯` 用於切換或強調目前處於可編輯狀態
- `儲存變更` 對應 `PUT /revisions/{revision}`
- `提交審核` 對應 `POST /revisions/{revision}/submit`
- `刪除修訂` 對應 `DELETE /revisions/{revision}`，需二次確認
- `重新開啟編輯` 對應 `POST /revisions/{revision}/reopen`

**狀態對頁面行為的影響：**

- `draft`：允許編輯基本資料與 Action 清單，並顯示編輯相關按鈕
- `pending_review`：所有欄位唯讀，不顯示編輯控制項
- `rejected`：所有欄位唯讀，需先重新開啟後才能編輯；不可刪除
- `approved`：完全唯讀

**空狀態：**

- 若 Action 清單為空，顯示 `目前尚未加入任何操作`
- 若歷程紀錄為空，顯示 `目前尚無任何審核紀錄`

**不在此頁處理的內容：**

- 不提供管理員接受或退回操作
- 不直接顯示管理員審核列表資訊

---

#### 管理員修訂審核列表頁

- **對應路由**：`GET /admin/revisions`
- **頁面目標**：讓擁有 `revision.review` 權限的管理員查看所有 Revision，並快速進入單一修訂審核頁。

**頁面結構：**

1. 頁首區
  - 頁面標題：`修訂審核`

2. 卡片列表區
  - 使用卡片列表呈現
  - 每張卡片代表一筆 Revision
  - 預設依日期倒序排列，最新更新的 Revision 排在最前面
  - 點擊整張卡片時，導向 `GET /admin/revisions/{revision}`

**卡片內容：**

| 區塊 | 說明 |
|---|---|
| 卡片標題列 | 顯示 `title` |
| 狀態標籤 | 顯示 `draft`、`pending_review`、`rejected`、`approved` 的 badge |
| 提交資訊 | 顯示建立者與最近一次審核時間 |
| 摘要資訊 | 顯示操作數量、最後更新時間 |
| 點擊提示 | 顯示此卡片可點擊進入審核頁 |

**卡片版面建議順序：**

1. 第一行：標題 + 狀態 badge
2. 第二行：建立者
3. 第三行：`X 個操作`
4. 第四行：`最後更新時間`
5. 第五行：`最近一次審核時間`
6. 最下方：整張卡片可點擊的提示文字或箭頭圖示

- 手機版所有資訊維持單欄直向排列
- 桌機版可將提交資訊與摘要資訊併排，但不改變資訊順序
- 不在卡片內放置個別審核按鈕

**列表卡片共用策略：**

- 「我的修訂列表頁」與「管理員修訂審核列表頁」優先共用同一個 revision card 骨架 partial
- 共用範圍包含：卡片外框、標題列、狀態 badge、摘要資訊、點擊整張卡片進入詳情頁的互動
- 差異資訊透過 `mode` 或等價參數切換顯示內容

| mode | 差異內容 |
|---|---|
| `user-list` | 顯示使用者自己的提交／審核資訊，點擊導向 `GET /revisions/{revision}` |
| `admin-list` | 顯示建立者與管理員需要的提交資訊，點擊導向 `GET /admin/revisions/{revision}` |

- 不要求把所有差異都抽成高度通用的單一元件
- 若共用卡片 partial 的條件分支過多，可改為共用基底 partial，再由 user/admin 各自包一層薄 partial

**狀態顯示規則：**

| 狀態 | 顯示文字 | 視覺建議 |
|---|---|---|
| `draft` | 草稿 | 灰色 badge |
| `pending_review` | 待審核 | 黃色 badge |
| `rejected` | 已退回 | 紅色 badge |
| `approved` | 已接受 | 綠色 badge |

**排序規則：**

- 不提供篩選功能
- 不提供搜尋功能
- 預設依 `updated_at DESC` 排序

**空狀態：**

- 當系統中尚無任何 Revision 時，顯示空狀態訊息：`目前還沒有任何修訂`

**分頁：**

- 卡片列表需要分頁
- 每頁筆數沿用系統既有列表頁慣例

**不在此頁處理的內容：**

- 不直接執行接受或退回操作
- 不直接編輯 Revision 內容
- 不提供篩選與搜尋功能

---

#### 管理員修訂審核頁

- **對應路由**：`GET /admin/revisions/{revision}`
- **頁面目標**：讓擁有 `revision.review` 權限的管理員查看單一 Revision 的完整內容、驗證結果與審核歷程，並執行接受或退回。

**頁面模式：**

- 此頁為管理員審核模式
- Revision 內容一律唯讀，不提供編輯 Revision 或 RevisionAction 的控制項

**頁面結構：**

1. 頁首區
  - 返回入口：返回 `修訂審核`
  - 頁面標題：顯示 `title`
  - 狀態 badge：顯示目前 Revision 狀態

2. 標題下方審核操作區
  - 顯示管理員可執行的審核操作

3. 審核摘要區
  - 顯示建立者
  - 顯示建立時間
  - 顯示送審時間（可由 Revision 進入 `pending_review` 的狀態異動時間取得）
  - 顯示目前驗證結果

4. 基本資料區
  - 顯示 `title`
  - 顯示 `description`
  - 顯示最後更新時間

5. 驗證錯誤區
  - 顯示重新驗證後的錯誤摘要與錯誤清單

6. Action 清單區
  - 以唯讀卡片列表呈現 `revision_actions`
  - 依 `order ASC` 排序

7. 歷程區
  - 顯示 `revision_reviews` 紀錄
  - 依時間倒序顯示退回、接受紀錄

**審核操作規則：**

| 操作 | 說明 |
|---|---|
| `接受並套用` | 對應 `POST /admin/revisions/{revision}/approve`，僅限 `pending_review` |
| `退回` | 對應 `POST /admin/revisions/{revision}/reject`，僅限 `pending_review` |
| `返回列表` | 返回 `GET /admin/revisions` |

- 僅 `pending_review` 狀態可顯示審核操作
- `接受並套用` 前需重新驗證整份 Revision
- 驗證失敗時，`接受並套用` 按鈕需 disabled
- 驗證失敗時，`退回` 仍可使用
- `退回` 需透過 modal 輸入退回理由，且 comment 必填

**與使用者詳情頁的共用策略：**

- 優先共用「單一修訂詳情頁」的主版面骨架
- 共用區域包含：基本資料區、Action 清單區、歷程區、驗證錯誤區
- 管理員審核頁僅在頁首文案、審核摘要區、標題下方審核操作區上與使用者頁不同
- 若共用條件分支過多，可採用共用基底模板，再由 user/admin 各自包一層薄模板

**驗證錯誤顯示規則：**

- 進入審核頁時需重新驗證
- 驗證錯誤摘要顯示在驗證錯誤區
- 有錯誤的 action card 需維持明顯視覺標記
- 點擊錯誤項目時，可將畫面定位到對應的 action card

**不在此頁處理的內容：**

- 不直接編輯 Revision 基本資料
- 不直接編輯 RevisionAction
- 不提供部分接受

---

#### 套用邏輯

1. 取得 `Cache::lock`（graph 層級），確保同一時間只有一份 Revision 在套用；取不到 lock 則回傳錯誤，管理員稍後再試
2. 重新驗證所有 RevisionAction（與進入審核頁時相同的驗證規則）
3. 依 `order` 順序依序執行每個 RevisionAction 對 Apache AGE 的操作，使用資料庫 transaction 確保原子性；過程中維護 `order → 實際 AGE graphid` 的對應表，供後續引用 `target_ref_order` / `*_vertex_ref_order` 的 action 使用
4. 套用成功後將 Revision 狀態更新為 `approved`，同時寫入一筆 `action=approved` 的 `revision_reviews` 紀錄，釋放 Redis lock
5. reject 時寫入一筆 `action=rejected` 的 `revision_reviews` 紀錄並更新 Revision 狀態為 `rejected`
6. submit 時僅將 Revision 狀態更新為 `pending_review`，不寫入 `revision_reviews` 紀錄

---

#### 不在此次範圍內

- 審查完成後通知提交者
- 編輯 VertexType / EdgeType schema
- 部分接受（只套用部分 RevisionAction）

