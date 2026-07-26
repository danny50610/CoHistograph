# Topic（專題）規格

## 概述

讓不熟悉圖資料結構的使用者，能透過**可設定的表格專題**閱讀實際頂點資料。

本功能取代 [#4](https://github.com/danny50610/CoHistograph/issues/4) 原先「頁面寫死 + config、不做設定介面」的方向，改為：

- 管理者在後台以結構化表單定義 Topic
- 定義以 JSON 儲存
- 前台以唯讀表格呈現查詢結果

第一版只做**表格**呈現，不做圖表／力導向圖。Issue #4 列出的三個具體情境（台灣活動、成員歌曲、近期活動）**不做成種子資料**；第一版只交付通用 Topic 引擎，之後由管理者手動建立。

相關但不在本規格範圍：[#3 視覺化頁面前端技術選擇](https://github.com/danny50610/CoHistograph/issues/3)（實例圖視覺化另案）。

---

## 目標與非目標

### 目標（v1）

1. 管理者可 CRUD Topic（含草稿／發布、手動排序、編輯時即時預覽）
2. 以結構化查詢定義（非自由 Cypher）從 Apache AGE 取出列資料
3. 前台公開列出已發布 Topic，並以固定表格＋分頁顯示
4. 支援多層路徑過濾、主體／關聯／**邊屬性**欄位、常用屬性運算子
5. 支援路徑步驟上的 **EdgeProperty** 過濾
6. 刪除 Graph Schema 前檢查 Topic JSON 依賴並阻止

### 非目標（v1）

- 前台動態篩選、排序、改 page size
- 自由 Cypher／SQL 編輯
- OR 條件組、正規表示式
- 聚合欄（count／sum 等）
- 多語系 Topic 名稱／說明／欄位標題（**欄位值的 locale 可選**，見下）
- 圖表或 graph canvas
- 一般使用者自建私人 Topic
- Issue #4 三個情境的種子 Topic
- 後台側欄並排預覽、另開視窗預覽、後台「在前台開啟」連結

---

## 名詞

| 名稱 | 說明 |
|------|------|
| Topic（專題） | 一組可發布的表格視圖定義：主體類型、過濾、欄位、排序、分頁 |
| 主體（subject） | 表格每一列對應的主 vertex type |
| 路徑過濾（path filter） | 以 edge 步驟陣列描述的多層關聯條件 |
| 關聯欄（relation column） | 沿路徑取相關 vertex，顯示自選 VertexProperty |
| 邊屬性欄（edge_property column） | 沿路徑指定步驟的 edge，顯示自選 EdgeProperty |

---

## 權限與可見性

### 權限

新增 Laratrust 權限 **`topic.manage`**：

- display_name：管理專題
- description：新增、修改、刪除 Topic，以及預覽未發布專題
- 掛給既有 `admin` 角色
- 定義於 `config/cohistograph/roles-and-permissions.php`，以既有 apply command 套用

後台 CRUD 使用 `middleware('permission:topic.manage')`，風格對齊 Graph Schema。

### 可見性

| 對象 | 已發布 | 草稿 |
|------|--------|------|
| 未登入／一般使用者 | 可看列表與表格頁 | 不可見 |
| 有 `topic.manage` | 可看 | 可預覽（直接造訪 `/topics/{id}` 或編輯流程） |

---

## 路由與資訊架構

### 前台

| Method | Path | 說明 |
|--------|------|------|
| GET | `/topics` | 已發布列表（分頁；見「實作約定」） |
| GET | `/topics/{topic}` | 表格頁（route key = id；唯讀；草稿僅管理者可預覽） |

### Navbar（`MenuService`）

對齊現有左右選單分工（左：公開瀏覽；右：帳號／管理）：

| 位置 | 項目 | 條件 | 連結 |
|------|------|------|------|
| **左邊** `left` | **專題** | 所有人（含未登入） | `/topics`（route `topics.index`） |
| **右邊** `right` →「網站管理」 | **專題管理** | 需 `topic.manage` | `/admin/topics`（route `admin.topics.index`） |

- 左邊目前為空；「專題」為第一個公開 nav item（品牌仍連 `/overview`）。
- 「專題管理」與會員管理、Graph Schema 等同樣掛在「網站管理」下拉，權限 gated。

### 後台

建議前綴：`/admin/topics`（與 `/admin/revisions` 風格一致）

| Method | Path | 說明 |
|--------|------|------|
| GET | `/admin/topics` | 管理列表（含草稿；分頁） |
| GET | `/admin/topics/create` | 建立表單（Inertia） |
| POST | `/admin/topics` | 儲存 |
| POST | `/admin/topics/preview` | 即時預覽（未儲存 definition＋page；需 `topic.manage`） |
| GET | `/admin/topics/{topic}/edit` | 編輯表單（Inertia） |
| PUT/PATCH | `/admin/topics/{topic}` | 更新 |
| DELETE | `/admin/topics/{topic}` | 硬刪除（confirm 後） |

---

## 資料模型

### `topics` 表（關聯式 PostgreSQL）

| 欄位 | 型別 | 說明 |
|------|------|------|
| `id` | bigint PK | 前台 URL 使用此 id（`/topics/{id}`），**無 slug** |
| `name` | string | 顯示名稱（v1 單語） |
| `description` | text nullable | 說明（單語） |
| `is_published` | boolean default false | 是否出現在公開列表 |
| `sort_order` | integer default 0 | 列表手動排序（小到大） |
| `definition` | json | 查詢與欄位定義（見下節） |
| `created_at` / `updated_at` | timestamps | |

不做軟刪除。不對 AGE 實例頂點做 FK。Schema 參照（vertex type／edge type／property）以 **id 寫在 JSON 內**，由應用層驗證；**不建資料庫 FK**。

---

## 實作約定

### 技術選型

| 區塊 | 技術 |
|------|------|
| 後台建立／編輯表單 | **Inertia.js + Vue 3**（對齊 `Revisions/Edit`、可複用 `AgeEntitySearch`） |
| 後台管理列表 | Blade（對齊 `admin/revisions/index`）或 Inertia，實作時擇一與專案慣例一致 |
| 前台列表／表格 | Blade + Bootstrap（對齊 `overview`） |
| 查詢邏輯 | `TopicQueryService`（前台表格與後台預覽共用） |

### 分頁

| 情境 | 每頁筆數 |
|------|----------|
| 前台 `/topics` 專題列表 | **20**（固定） |
| 前台 `/topics/{id}` 表格 | **20**（固定） |
| 後台 `/admin/topics` 管理列表 | **20**（固定） |
| 後台表單即時預覽 | **20**（固定） |

v1 **不提供** `page_size` 設定；常數 `20` 由應用層強制，可不寫入 `definition` 或寫入但儲存時覆寫為 20。

### 列表排序

前台專題列表與後台管理列表皆：**`sort_order` ASC，再 `id` ASC**。

### `columns[].key` 自動產生

管理者只填 `label`；`key` 由系統產生並在儲存時寫入 JSON，同一 Topic 內唯一：

| 欄位 `type` | `key` 規則（範例） |
|-------------|-------------------|
| `subject_property` | `p_{vertex_property_id}` |
| `relation` | `rel_{欄位在 columns 中的 0-based index}` |
| `edge_property` | `ep_{step_index}_{edge_property_id}` |

`sort.column_key` 必須引用上述 `key`。

### 預設排序（`definition.sort`）

- v1 **單欄**排序：一個 `column_key` + `direction`（`asc` / `desc`）
- 未設定或 `column_key` 為空：**主體 vertex id 升冪**（穩定預設）
- 表單 UI：下拉選已定義的 column + 方向；可選「預設（主體 id）」

### 屬性過濾與運算子

依 `VertexProperty.age_property_type` / `EdgeProperty.age_property_type` **限制可選 operator**（實作時維護對照表）。例如：

| 型別 | 可用 operator（v1） |
|------|---------------------|
| STRING | `eq`, `contains`, `is_null`, `is_not_null` |
| INTEGER / FLOAT | `eq`, `gt`, `gte`, `lt`, `lte`, `between`, `is_null`, `is_not_null` |
| BOOLEAN | `eq`, `is_null`, `is_not_null` |
| DATE / TIMESTAMPTZ 等 | `eq`, `gt`, `gte`, `lt`, `lte`, `between`, `is_null`, `is_not_null` |

`TIMESTAMPTZ` 以 **app timezone** 解讀與比較。

### 多語 property（locale）

主體 **VertexProperty** 過濾、路徑步驟 **EdgeProperty** 過濾、以及各欄位顯示，若 property 為多語系欄位：

- 每一條過濾或每一欄可選 **`locale`**（如 `zh_TW`）
- **未選**則跟 **app locale** fallback
- 表單依 property 是否多語顯示 locale 下拉

Topic 本身的 `name` / `description` / 欄位 `label` 仍為 v1 單語。

### 變更主體 VertexType

編輯時若使用者變更 `subject_vertex_type_id`：

1. 前端 **confirm**：「變更主體將清除查詢與欄位設定」
2. 確認後清空 `property_filters`、`path_filters`、`columns`、`sort`

### 欄位數量

至少 **1** 個 column 才能儲存與觸發預覽查詢。

### 即時預覽

- **防抖**自動重查（建議約 500ms）
- **不** abort 進行中請求（僅防抖）
- 定義不完整時不發查詢；查詢失敗不阻擋儲存

---

## `definition` JSON 結構

以下為 v1 約定形狀（實作時可用 Form Request／DTO 驗證；欄位名稱可微調但語意需對齊）。

```json
{
  "subject_vertex_type_id": 1,
  "sort": {
    "column_key": "p_10",
    "direction": "desc"
  },
  "property_filters": [
    {
      "vertex_property_id": 10,
      "operator": "eq",
      "value": "台灣",
      "locale": "zh_TW"
    }
  ],
  "path_filters": [
    {
      "steps": [
        {
          "edge_type_id": 5,
          "direction": "outgoing",
          "target_vertex_type_id": 2,
          "edge_property_filters": [
            {
              "edge_property_id": 3,
              "operator": "eq",
              "value": "主唱",
              "locale": "zh_TW"
            }
          ]
        }
      ],
      "target_vertex_id": 12345
    }
  ],
  "columns": [
    {
      "key": "p_1",
      "label": "名稱",
      "type": "subject_property",
      "vertex_property_id": 1,
      "locale": null,
      "link_to_subject": true
    },
    {
      "key": "rel_1",
      "label": "主辦",
      "type": "relation",
      "path": [
        {
          "edge_type_id": 5,
          "direction": "outgoing",
          "target_vertex_type_id": 2
        }
      ],
      "vertex_property_id": 4,
      "locale": "zh_TW",
      "link_to_vertex": true
    },
    {
      "key": "ep_0_5",
      "label": "參與日期",
      "type": "edge_property",
      "path": [
        {
          "edge_type_id": 6,
          "direction": "outgoing",
          "target_vertex_type_id": 2
        }
      ],
      "step_index": 0,
      "edge_property_id": 5,
      "locale": null
    }
  ]
}
```

### 欄位說明

#### 根層

| Key | 說明 |
|-----|------|
| `subject_vertex_type_id` | 主體 VertexType id（必填） |
| `sort` | 單欄排序；見「實作約定」 |
| `property_filters` | 主體 **VertexProperty** 過濾，條件之間 **AND** |
| `path_filters` | 多層路徑過濾，條件之間 **AND** |
| `columns` | 表格欄位（順序即顯示順序；至少 1 欄） |

#### `property_filters` / `edge_property_filters`

| 欄位 | 說明 |
|------|------|
| `vertex_property_id` / `edge_property_id` | 屬性 id |
| `operator` | 見運算子表 |
| `value` | 依 operator；`is_null` / `is_not_null` 可省略 |
| `locale` | 可選；多語 property 時由使用者選擇；未選跟 app locale |

#### `property_filters[].operator` / `edge_property_filters[].operator`（v1）

| Operator | 適用 | `value` |
|----------|------|---------|
| `eq` | 字串／數字／日期／布林等 | 單一值 |
| `contains` | 字串 | 字串 |
| `gt` / `gte` / `lt` / `lte` | 數字、日期／時間 | 單一值 |
| `between` | 日期／時間（亦可數字） | `{ "from": ..., "to": ... }` |
| `is_null` / `is_not_null` | 任何 | 無（或忽略） |

不做 regex、不做 OR 群組。

#### `path_filters` / 欄位 `path` 的步驟

每步：

| Key | 說明 |
|-----|------|
| `edge_type_id` | EdgeType id |
| `direction` | `outgoing` 或 `incoming`（相對當前節點） |
| `target_vertex_type_id` | 可選；用於驗證／限制對端類型 |
| `edge_property_filters` | 可選；針對**此步 edge** 的屬性過濾（AND） |

路徑可多層（步驟陣列）。`path_filters` 可選 `target_vertex_id`：最後一層對端須等於該 **AGE vertex id**（非關聯式 FK）。未指定時表示「存在符合路徑的關聯即可」。

#### `columns[]`

| `type` | 說明 |
|--------|------|
| `subject_property` | 主體上的 VertexProperty；可選 `locale` |
| `relation` | 沿 `path` 取對端 vertex；**自選** `vertex_property_id` 顯示；可選 `locale`；可選 `link_to_vertex` |
| `edge_property` | 沿 `path` 的 **`step_index`（0-based）** 步驟上的 edge；**自選** `edge_property_id`；可選 `locale` |

系統行為：

- `subject_property` 可選 `link_to_subject` 連到 `/graph/vertex/{id}`
- 關聯 vertex、edge 屬性若有多筆符合：**全部列出、換行**；vertex 可選連結
- v1 不做聚合欄

---

## 後台畫面

需 `topic.manage`。建立／編輯為 **Inertia 頁**；列表可為 Blade。

### 管理列表 `/admin/topics`

**版面：**

1. `h1`：專題管理  
2. 「新增專題」按鈕 → create  
3. Topic 卡片列表（含草稿與已發布；**不做**狀態篩選）  
4. 底部分頁（每頁 **20**）  
5. 空狀態卡（若無任何 Topic）

**每張卡：**

| 元素 | 說明 |
|------|------|
| 名稱 | 主要文字 |
| 發布狀態 | badge（已發布／草稿） |
| 說明 | 有則顯示（可截斷） |
| `sort_order` | 顯示目前值；於編輯表單修改 |
| 操作 | 編輯、刪除（**無**「在前台開啟」） |

**刪除：** 按鈕＋ `confirm('確定要刪除此專題嗎？')` 後 **硬刪除**（對齊 Graph Schema）。

列表排序：`sort_order` ASC，再 `id` ASC。

### 建立／編輯表單（Inertia）

頁面建議：`Topics/Create`、`Topics/Edit`。

**單頁分區塊**（不做 wizard）：

1. **基本資料**：`name`（必填）、`description`、`is_published`、`sort_order`；**無 slug**；**無 page_size 欄位**
2. **主體**：`subject_vertex_type_id`（變更時 confirm 並清空查詢區塊）
3. **屬性過濾**：動態列；property、operator、value、**locale（多語時）**；上移／下移
4. **路徑過濾**：每條路徑一卡；步驟可增刪排序；每步可掛 **edge_property_filters**；可選目標 vertex（`AgeEntitySearch`；可留空）
5. **欄位**：動態列；type 為 `subject_property` / `relation` / `edge_property`；relation 選顯示 property + locale；edge_property 選 `step_index` + `edge_property_id` + locale
6. **預設排序**：單欄 column + 方向，或「預設（主體 id）」
7. **儲存**／**返回列表**

**即時預覽（表單下方）：**

- 防抖自動重查；**不 abort** 進行中請求
- 以未儲存表單組 definition；每頁 **20** 列；**可分頁**
- 呈現對齊前台表格（含多值換行）
- 不完整定義不查詢；錯誤不阻擋儲存
- API：`POST /admin/topics/preview`

**不提供**「在前台開啟」按鈕（建立、編輯皆無）；預覽僅靠表單下方區塊。管理者若已知 id 仍可手動造訪 `/topics/{id}`。

控件：schema 下拉存 id；動態列新增／刪除／上移／下移；不做 raw JSON 編輯。

---

## 前台畫面

Blade + Bootstrap（`layouts.app`）。

### 列表頁 `/topics`

1. `h1`：專題  
2. 已發布 Topic 卡片（`sort_order` ASC，再 `id` ASC）  
3. 底部分頁（每頁 **20**）  
4. 空狀態卡：「目前還沒有專題」（無管理連結）

每卡：名稱（連 `/topics/{id}`）+ 說明（可選）。

### 表格頁 `/topics/{id}`

1. 草稿 alert（未發布 + `topic.manage`）+「前往編輯」  
2. 返回 `/topics`  
3. `h1` + 說明  
4. responsive 表格（每頁 **20**）+ 分頁  

- 0 列：保留表頭 + colspan 提示  
- 定義失效：alert，不渲染空表  
- 多值欄：換行；可連結 vertex  

### 前台權限與錯誤

| 情況 | 行為 |
|------|------|
| 未發布＋無 `topic.manage` | 404 |
| 未發布＋有 `topic.manage` | 表格 + 草稿 alert |
| id 不存在 | 404 |
| 定義失效 | 200 + 失效提示 |

---

## 查詢執行

1. 讀取 `definition`，驗證 schema 引用  
2. 組 AGE 查詢（`TopicQueryService`）  
3. 套用 `property_filters`、`path_filters`（含步驟上 `edge_property_filters`），皆 AND  
4. 排序（`sort` 或預設主體 id ASC）  
5. 分頁（固定 page size **20**）  
6. 依 `columns` 組列（含 relation / edge_property 路徑解析）

---

## Graph Schema 刪除保護

刪除前掃描所有 Topic 的 `definition`，檢查引用：

- VertexType  
- EdgeType  
- VertexProperty  
- **EdgeProperty**

若仍被引用：**阻止刪除**，並指出 Topic `name` / `id`。

建立／更新時驗證引用 id 存在且語意合理（property 所屬 type、edge 端點相容等）。

---

## 與現有功能的關係

| 現有 | 關係 |
|------|------|
| Inertia + Vue（Revisions） | 後台 Topic 表單沿用；複用 `AgeEntitySearch` |
| `/overview`、`/graph/vertex` | 通用瀏覽；Topic 為策展表格 |
| Revision | Topic 唯讀 AGE 資料 |
| Laratrust | 新增 `topic.manage` |

---

## 實作里程碑建議

1. **資料與權限**：migration、Model、`topic.manage`、Navbar  
2. **TopicQueryService**：過濾、路徑、欄位、分頁（含 EdgeProperty）  
3. **後台**：Blade 列表 + Inertia 表單 + preview API + 防抖預覽 UI  
4. **前台**：列表分頁 + 表格頁  
5. **Schema 刪除保護**  
6. **測試**：CRUD、preview、權限、EdgeProperty、刪除阻擋、查詢路徑  

---

## 驗收標準

- Navbar 左「專題」；右「專題管理」（`topic.manage`）  
- `/topics` 卡片列表，每頁 20，排序 `sort_order` + `id`  
- `/topics/{id}` 固定表格 + 分頁；草稿 alert  
- 後台 Inertia 表單：單頁分區塊、動態列、防抖預覽（每頁 20、可分頁）  
- 無 slug、無「在前台開啟」、無 page_size 設定  
- 主體／edge 過濾可選 locale；欄位可選顯示 property + locale  
- 路徑步驟可掛 edge 屬性過濾；欄位支援 `edge_property` + `step_index`  
- 改主體 type confirm 後清空查詢設定  
- 至少 1 欄；`columns[].key` 自動產生  
- 硬刪除 Topic；Schema 刪除檢查含 EdgeProperty  
- 無 issue #4 種子資料  

---

## 決策紀錄

| 決策 | 選擇 |
|------|------|
| 後台表單技術 | Inertia.js + Vue |
| 名稱 | Topic（專題） |
| 前台識別 | `/topics/{id}`，無 slug |
| Navbar | 左「專題」；右「專題管理」 |
| `columns[].key` | 系統自動產生 |
| 排序 | 單欄；未設 → 主體 id ASC |
| 過濾運算子 | 依 property 型別限制 |
| 多語 property | 過濾／顯示可選 locale；未選跟 app locale |
| 關聯欄 | 自選 VertexProperty |
| 邊屬性 | 步驟上可 filter；欄位 type `edge_property` |
| 改主體 type | confirm 後清空查詢設定 |
| page_size | 固定 20 |
| 列表排序 | `sort_order` + `id` ASC（前後台一致） |
| 列表分頁 | 前後台皆每頁 20 |
| 最少欄位 | 1 |
| 即時預覽 | 防抖；不 abort |
| 前台連結 | 不提供「在前台開啟」 |
| Topic 文案多語 | v1 單語 |
| 刪除 Topic | 硬刪除 |
| Schema 刪除 | 含 EdgeProperty 依賴檢查 |
| Issue #4 三情境 | 不當種子 |
