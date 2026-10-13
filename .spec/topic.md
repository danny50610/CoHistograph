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
- **`sort` 以 `relation`／`edge_property` 欄排序**（v1 僅 `subject_property`）

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
- `column_key` **僅允許**對應 `type = subject_property` 的 column（不可為 `rel_*`／`ep_*`）
- 未設定或 `column_key` 為空：**主體 vertex id 升冪**（穩定預設）
- 表單 UI：下拉選已定義的 **subject_property** column + 方向；可選「預設（主體 id）」

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
6. **測試**：依下方「測試案例」由淺入深實作  

---

## 測試案例

實作時以 **PHPUnit**（Feature / Unit）撰寫；慣例對齊專案既有測試（`DatabaseTransactions`、權限用 `givePermission('topic.manage')`）。  
建議**由層級 1 開始**，通過後再往下；不必一次寫完。

### 層級 1：設定與 Model（最簡）

| ID | 案例 | 預期 |
|----|------|------|
| T1-01 | `config/cohistograph/roles-and-permissions.php` 含 `topic.manage` | permissions 陣列有定義；`admin` 角色 permissions 含 `topic.manage` |
| T1-02 | `Topic::PAGE_SIZE`（或同等常數） | 值為 **20** |
| T1-03 | `TopicFactory` 建立一筆 | 可 persist；`definition` 讀回為 **array** |
| T1-04 | `Topic::published()` scope | 只含 `is_published = true` |
| T1-05 | `Topic::orderedForList()` scope（或列表查詢慣例） | 排序為 `sort_order` ASC、`id` ASC |

### 層級 2：路由與權限

| ID | 案例 | 預期 |
|----|------|------|
| T2-01 | 訪客 `GET /topics` | **200** |
| T2-02 | 訪客 `GET /admin/topics` | **redirect** 登入 |
| T2-03 | 已登入但無 `topic.manage` → `GET /admin/topics` | **403** |
| T2-04 | 有 `topic.manage` → `GET /admin/topics` | **200** |
| T2-05 | 訪客 `POST /admin/topics/preview` | **redirect** 登入 |
| T2-06 | 無權限 `POST /admin/topics/preview` | **403** |
| T2-07 | 有權限 `GET /admin/topics/create` | **200**（Inertia `Topics/Create`） |
| T2-08 | 有權限 `GET /admin/topics/{topic}/edit` | **200**（Inertia `Topics/Edit`） |
| T2-09 | 無權限 `POST /admin/topics` | **403** |
| T2-10 | 無權限 `PATCH /admin/topics/{topic}` | **403** |
| T2-11 | 無權限 `DELETE /admin/topics/{topic}` | **403** |
| T2-12 | 訪客 `POST`／`PATCH`／`DELETE` 後台 Topic | **redirect** 登入 |
| T2-13 | `php artisan app:apply-role-and-permission-command` 後 | `admin` 角色實際擁有 `topic.manage` |

### 層級 3：前台列表 `/topics`

| ID | 案例 | 預期 |
|----|------|------|
| T3-01 | 僅已發布專題出現在列表 | 草稿不出現 |
| T3-02 | 列表排序 | `sort_order` ASC，再 `id` ASC |
| T3-03 | 列表分頁 | 每頁 **20**；第 21 筆在第二頁 |
| T3-04 | 無已發布專題 | 顯示空狀態卡「目前還沒有專題」；**無**管理後台連結 |
| T3-05 | 卡片內容 | 名稱連到 `/topics/{id}`；有說明則顯示 |

### 層級 4：前台表格 `/topics/{id}`

| ID | 案例 | 預期 |
|----|------|------|
| T4-01 | 訪客瀏覽已發布專題 | **200**；標題、說明、表格、底部分頁 |
| T4-02 | 訪客瀏覽草稿專題 | **404** |
| T4-03 | 有 `topic.manage` 瀏覽草稿 | **200**；頂部 **草稿 alert**＋「前往編輯」 |
| T4-04 | 不存在的 id | **404** |
| T4-05 | 查無資料列 | 保留表頭；一行 colspan「目前沒有符合的資料」 |
| T4-06 | `definition` 引用已刪 schema | **200**；失效 alert（不渲染空表） |
| T4-07 | 表格分頁 | 每頁 **20**；訪客**不能**改排序／篩選／page size |
| T4-08 | 關聯多值欄 | 同一格換行；可連結 vertex |
| T4-09 | `edge_property` 多值 | 同一格換行列出 |
| T4-10 | 已登入但**無** `topic.manage` 瀏覽草稿 | **404**（與訪客相同） |
| T4-11 | Topic 硬刪除後再 `GET /topics/{id}` | **404** |
| T4-12 | `link_to_subject` / `link_to_vertex` 為 false | 顯示文字、**無**連結 |
| T4-13 | 欄位值為 null／空 | 格子顯示空（或約定 placeholder，實作寫死並測） |

### 層級 5：後台列表與 CRUD

| ID | 案例 | 預期 |
|----|------|------|
| T5-01 | 管理列表含草稿與已發布 | 兩者皆可見；badge 區分狀態 |
| T5-02 | 管理列表分頁 | 每頁 **20** |
| T5-03 | 管理列表操作 | 有編輯、刪除；**無**「在前台開啟」 |
| T5-04 | `POST /admin/topics` 有效 payload | 建立成功；redirect 或留在編輯頁（實作擇一，需一致） |
| T5-05 | `PATCH /admin/topics/{topic}` | 更新成功 |
| T5-06 | `DELETE /admin/topics/{topic}` | 硬刪除；資料庫無該筆 |
| T5-07 | 管理列表空狀態 | 無 Topic 時顯示空狀態卡 |
| T5-08 | 發布 → 前台列表出現；取消發布 → 前台列表消失 | 列表與 `is_published` 同步 |

### 層級 6：`definition` 驗證（Form Request）

| ID | 案例 | 預期 |
|----|------|------|
| T6-01 | 缺少 `name` | 驗證失敗 |
| T6-02 | `columns` 為空 | 驗證失敗（至少 1 欄） |
| T6-03 | 缺少 `subject_vertex_type_id` | 驗證失敗 |
| T6-04 | 引用不存在的 VertexType / Property / EdgeType id | 驗證失敗 |
| T6-05 | `operator` 與 property 型別不符（如 STRING 用 `gt`） | 驗證失敗 |
| T6-06 | 儲存後 `columns[].key` | 依規則自動產生（如 `p_{id}`、`rel_{index}`、`ep_{step}_{id}`） |
| T6-07 | `sort` 未設或空 | 查詢時預設主體 vertex **id ASC** |
| T6-08 | `sort` 設單欄 | 依 `column_key` + `direction` 排序 |
| T6-09 | 多語 property 過濾帶 `locale` | 接受並寫入 JSON |
| T6-10 | 路徑 step 含 `edge_property_filters` | 接受並寫入 JSON |
| T6-11 | 欄位 type `edge_property` 含 `step_index` | 接受並寫入 JSON |
| T6-12 | 引用不存在的 **EdgeProperty** id | 驗證失敗 |
| T6-13 | `vertex_property_id` 不屬於 `subject_vertex_type_id` | 驗證失敗 |
| T6-14 | `edge_property_id` 不屬於該 step 的 `edge_type_id` | 驗證失敗 |
| T6-15 | `sort.column_key` 指向不存在的 column key | 驗證失敗 |
| T6-16 | 請求帶自訂 `page_size` | 忽略或覆寫為 **20**（不得存其他值） |
| T6-17 | `relation`／`edge_property` 欄缺少必要 path／property id | 驗證失敗 |
| T6-18 | `target_vertex_type_id` 與 EdgeType 端點類型不符 | 驗證失敗 |

### 層級 7：`TopicQueryService`（Unit / Feature）

| ID | 案例 | 預期 |
|----|------|------|
| T7-01 | 僅 `property_filters`（AND） | 只回符合全部條件的主體列 |
| T7-02 | `property_filters` + `path_filters`（AND） | 交集結果 |
| T7-03 | 多層 `path_filters.steps` | 多跳路徑正確 |
| T7-04 | step 上 `edge_property_filters` | 只保留 edge 屬性符合的列 |
| T7-05 | `path_filters.target_vertex_id` 有設 | 終點須為該 AGE id |
| T7-06 | `path_filters.target_vertex_id` 未設 | 存在符合路徑即可 |
| T7-07 | 欄位 `subject_property` + `locale` | 顯示對應語系值 |
| T7-08 | 欄位 `relation` + 自選 `vertex_property_id` | 顯示對端屬性 |
| T7-09 | 欄位 `edge_property` + `step_index` | 顯示該步 edge 屬性 |
| T7-10 | 分頁 | 固定 **20** 筆／頁 |

> 需 AGE 測試資料的案例，使用下方 **「搜尋條件測試案例」** 定義的 Fixture 與 QS-* 編號；可實作為 `Tests\Fixtures\TopicQueryGraphFixture`（或 seeder trait），與 `SimulateGraphDataSeeder` 分離以保持案例可預期。

---

## 搜尋條件測試案例

針對 `TopicQueryService`（及 preview API）的**查詢條件**設計。每案應 assert **回傳主體 vertex id 集合**（或名稱集合）與**總筆數**；欄位渲染可另測，此處聚焦「誰被篩進來」。

### 測試資料夾具（Fixture）

實作時建立**固定、最小** AGE 圖，每次測試前 seed（`DatabaseTransactions` 內 rollback 或獨立 test graph）。建議兩套互補：

#### QF-Event（主體屬性與單跳路徑）

| 類型 | age_label | 備註 |
|------|-----------|------|
| 活動 `event` | `event` | 主體；屬性見下 |
| 成員 `member` | `member` | 路徑對端 |
| 公司 `company` | `company` | Issue #4「公司辦活動」 |

**活動頂點（主體列）：**

| vertex | `location` (STRING) | `event_date` (DATE) | `starts_at` (TIMESTAMPTZ) | `attendee_count` (INTEGER) | `score` (FLOAT) | `is_public` (BOOLEAN) | `note` (STRING, 可 null) |
|--------|---------------------|---------------------|---------------------------|----------------------------|-----------------|-------------------------|--------------------------|
| E1 | 台灣 | 2024-06-01 | 2024-06-01 10:00:00+08 | 100 | 4.5 | true | 夏季活動 |
| E2 | 日本 | 2024-07-15 | 2024-07-15 18:00:00+09 | 50 | 3.0 | false | null |
| E3 | 台灣 | 2024-08-20 | 2024-08-20 14:00:00+08 | 200 | 4.5 | true | 秋季活動 |
| E4 | 韓國 | 2023-12-01 | 2023-12-01 12:00:00+09 | 30 | 2.5 | false | 海外 |
| E5 | 台灣 | 2024-09-01 | 2024-09-01 09:00:00+08 | 10 | 1.0 | true | 無主辦 |
| E6 | 台灣 | 2024-10-10 | 2024-10-10 19:00:00+08 | 80 | 3.5 | true | 公司場 |

> `starts_at` 比較一律以 **app timezone** 解讀（見實作約定）。E5 **沒有**主辦邊；E6 由**公司**主辦。

**成員頂點：** M1（Alice）、M2（Bob）  
**公司頂點：** C1（Acme）

**邊 `organized_by_member`：** `member` → `event`  
**邊 `organized_by_company`：** `company` → `event`  
（實作可共用同一 EdgeType `organized_by` 若 start 端允許多 type；測試以**兩條 EdgeType** 或單一 `organized_by`＋不同 start type 皆可，fixture 需能分別篩「成員主辦／公司主辦」。建議：**兩條 EdgeType** `organized_by_member`、`organized_by_company`。）

| 邊 | EdgeType | 起點 | 終點 |
|----|----------|------|------|
| OB1 | organized_by_member | M1 | E1 |
| OB2 | organized_by_member | M1 | E2 |
| OB3 | organized_by_member | M2 | E3 |
| OB4 | organized_by_member | M2 | E4 |
| OB5 | organized_by_company | C1 | E6 |

（E5 無邊）

#### QF-Song（多跳路徑、Edge 屬性、多語）

延伸 `SimulateGraphDataSeeder` 概念；主體為 **歌曲 `song`**。

**歌曲：**  

| vertex | `name` | `is_cover` (BOOLEAN) |
|--------|--------|----------------------|
| S1 | 綺麗事 | false |
| S2 | いのち | false |
| S3 | The Last Frontier | false |
| S4 | 紅蓮華（cover） | **true** |

**VTuber：** V1 星街すいせい、V2 AZKi  

**團體：** G1  

**地點：** P1（台灣）— 供三跳路徑  

**邊 `vocal`（`vtuber` → `song`）與屬性：**

| 邊 | V | S | `order` (INT) | `role_zh_tw` | `role_ja_jp` |
|----|---|---|---------------|--------------|--------------|
| VC1 | V1 | S1 | 1 | 主唱 | ボーカル |
| VC2 | V2 | S1 | 2 | 和聲 | コーラス |
| VC3 | V2 | S2 | 1 | 主唱 | ボーカル |
| VC4 | V1 | S3 | 1 | 主唱 | ボーカル |
| VC5 | V2 | S3 | 2 | 和聲 | コーラス |
| VC6 | V1 | S4 | 1 | 主唱 | ボーカル |

**邊 `group_vocal`（`group` → `song`）：** G1 → S3（團體名義曲，供「團體出的歌曲」）

**邊 `member`（`vtuber` → `group`）：** V1、V2 皆屬 G1  

**邊 `based_in`（`group` → `place`）：** G1 → P1  

**邊 `has_youtube_video`（`song` → `youtube_video`）：** 每首歌至少一筆（id 固定字串即可）

---

### A. 主體屬性過濾（`property_filters`）

主體：`QF-Event.event`。每案僅列與其他條件不同的 filter；無則 `{}`。

| ID | 條件 | 預期主體 |
|----|------|----------|
| QS-A01 | `location` `eq` `台灣` | E1, E3, E5, E6 |
| QS-A02 | `location` `contains` `台` | E1, E3, E5, E6 |
| QS-A03 | `location` `eq` `日本` | E2 |
| QS-A04 | `note` `is_null` | E2 |
| QS-A05 | `note` `is_not_null` | E1, E3, E4, E5, E6 |
| QS-A06 | `attendee_count` `gte` `100` | E1, E3 |
| QS-A07 | `attendee_count` `between` `40`–`60` | E2 |
| QS-A08 | `event_date` `between` `2024-06-01`–`2024-08-31` | E1, E2, E3 |
| QS-A09 | `event_date` `lt` `2024-01-01` | E4 |
| QS-A10 | `is_public` `eq` `true` | E1, E3, E5, E6 |
| QS-A11 | 兩條 AND：`location eq 台灣` + `is_public eq true` | E1, E3, E5, E6 |
| QS-A12 | 兩條 AND：`location eq 台灣` + `attendee_count gt 150` | E3 only |
| QS-A13 | 兩條 AND 無符合：`location eq 台灣` + `attendee_count lt 10` | （空） |
| QS-A14 | `is_public` `eq` `false` | E2, E4 |
| QS-A15 | `attendee_count` `gt` `100` | E3（不含 100） |
| QS-A16 | `attendee_count` `lte` `50` | E2, E4, E5 |
| QS-A17 | `attendee_count` `lt` `50` | E4, E5 |
| QS-A18 | `score` (FLOAT) `eq` `4.5` | E1, E3 |
| QS-A19 | `score` `gt` `3.0` | E1, E3, E6 |
| QS-A20 | `score` `between` `2.0`–`3.5` | E2, E4, E6 |
| QS-A21 | `starts_at` (TIMESTAMPTZ) `gte` app-tz 下 `2024-06-01 00:00:00` | E1, E2, E3, E5, E6 |
| QS-A22 | `starts_at` `lt` app-tz 下 `2024-01-01 00:00:00` | E4 |
| QS-A23 | `starts_at` `between` 2024-07-01～2024-08-31（app tz） | E2, E3 |

### B. 路徑過濾（`path_filters`）

主體仍為 `event`（QF-Event），除非註明。

| ID | 條件 | 預期主體 |
|----|------|----------|
| QS-B01 | 一步 `organized_by_member` **incoming**（event ← member），無 `target_vertex_id` | E1–E4（**不含 E5、E6**） |
| QS-B02 | 同上 + `target_vertex_id` = **M1** | E1, E2 |
| QS-B03 | 同上 + `target_vertex_id` = **M2** | E3, E4 |
| QS-B04 | 同上 + `target_vertex_id` = 不存在的 AGE id | （空） |
| QS-B05 | `property_filters`: `location eq 台灣` **AND** `path`: incoming `organized_by_member` + target **M2** | E3 only |
| QS-B06 | 兩條 `path_filters` AND：target M1 **且** target M2 | （空） |
| QS-B07 | 兩條 `path_filters` AND、有交集：補 fixture「E1 亦被 M2 主辦」後，target M1 **且** target M2 | 僅該共同主辦活動（快樂路徑） |
| QS-B08 | 僅 path「存在 `organized_by_member`」相對全列 | 結果為 E1–E4（**不含 E5、E6**） |
| QS-B09 | 一步 `organized_by_company` incoming + target **C1** | E6 |

> QS-B07 需在 fixture 另加邊 OB5：M2→E1（僅此案或共用擴充 fixture）。若不想改基底圖，可於該測試內臨時加邊。

### C. 多層路徑（`path_filters.steps` 多步）

主體：`QF-Song.song`。

| ID | 條件 | 預期主體 |
|----|------|----------|
| QS-C01 | 一步：`vocal` **incoming**（song ← vtuber），target **V1** | S1, S3, S4 |
| QS-C02 | 一步：`vocal` incoming，target **V2** | S1, S2, S3 |
| QS-C03 | 兩步：① `vocal` incoming → vtuber ② `member` **outgoing**（vtuber → group）終點類型 `group`；無 target | S1–S4 |
| QS-C04 | 兩步 + 路徑終點約束（`target_vertex_id` = **最後一層**）：最後一層 group = **G1** | S1–S4 |
| QS-C05 | 兩步 + 另測「中間層為特定 vtuber」：用**兩條** path_filters（一步 target V1 + 一步存在 member→G1）或單 path 第一步加 edge／中間約束 | S1, S3, S4（語意寫進實作註解） |
| QS-C06 | 兩步 + 最後一層終點 = 不存在 group id | （空） |
| QS-C07 | 錯誤方向：`vocal` **outgoing** from song（schema 為 vtuber→song） | （空）或驗證拒絕 |
| QS-C08 | **三步**：① vocal incoming ② member outgoing ③ `based_in` outgoing → place；target **P1** | S1–S4 |
| QS-C09 | 三步 + target **P1** 不存在的 id | （空） |
| QS-C10 | step 上 `target_vertex_type_id` 與 EdgeType 端點**不符** | 驗證失敗（見 T6-18）；若漏網則查詢空結果 |

> **`target_vertex_id` 語意（寫死）：** 一律約束該條 `path_filter` **最後一步**的對端頂點。若需約束中間層，改用另一步驟的 `edge_property_filters`、拆成多條 path_filters，或未來擴充（v1 不支援中間層 target）。

### D. 路徑步驟 Edge 屬性過濾（`edge_property_filters`）

主體：`QF-Song.song`；路徑通常為一步 `vocal` incoming。

| ID | 條件 | 預期主體 |
|----|------|----------|
| QS-D01 | step 上 `order` `eq` `1` | S1, S2, S3, S4 |
| QS-D02 | step 上 `order` `eq` `2` | S1, S3 |
| QS-D03 | step 上 `role_zh_tw` `eq` `主唱` + `locale` `zh_TW` | S1, S2, S3, S4 |
| QS-D04 | step 上 `role_zh_tw` `eq` `和聲` | S1, S3 |
| QS-D05 | step 上 `role_ja_jp` `eq` `ボーカル` + `locale` `ja_jp` | 同 QS-D03 |
| QS-D06 | step 上 `role_zh_tw` `eq` `主唱` **且** path 最後終點 **V1** | S1, S3, S4 |
| QS-D07 | step 上 `role_zh_tw` `eq` `主唱` **且** path 最後終點 **V2** | S2 only |
| QS-D08 | 多層路徑（兩步）+ **第一步** `edge_property_filters`: `role_zh_tw eq 主唱` | S1, S2, S3, S4 |
| QS-D09 | 僅 `edge_property_filters`（無主體 property_filters、無 target）+ `order eq 2` | S1, S3（同 QS-D02） |
| QS-D10 | `order` `between` `1`–`1` | 同 QS-D01 |
| QS-D11 | **同一 step 兩條** edge filter AND：`order eq 1` + `role_zh_tw eq 主唱` | S1, S2, S3, S4 |
| QS-D12 | 同一 step AND：`order eq 2` + `role_zh_tw eq 主唱` | （空） |

### E. 條件組合（跨類型 AND）

| ID | 主體 | 條件摘要 | 預期主體 |
|----|------|----------|----------|
| QS-E01 | event | `location eq 台灣` + path incoming `organized_by_member` target M1 | E1 |
| QS-E02 | event | `location eq 台灣` + path `organized_by_member` target M2 | E3 |
| QS-E03 | song | path vocal incoming target **V1** + step `role_zh_tw eq 主唱` | S1, S3, S4 |
| QS-E04 | song | path vocal incoming target **V1** + step `role_zh_tw eq 和聲` | （空） |
| QS-E05 | song | path vocal incoming（無 target）+ step `role_zh_tw eq 和聲` | S1, S3 |
| QS-E06 | event | `property_filters` 空 + `path_filters` 空 | E1–E6 全列 |
| QS-E07 | event | `location eq 台灣` + path 存在 `organized_by_member`（無 target） | E1, E3（**不含 E5、E6**） |

**路徑 + edge 屬性語意（QS-E03～E05）：** `target_vertex_id` 約束該 path **最後一步**對端；`edge_property_filters` 約束**滿足該步路徑的那條 edge**。同一主體多條同類邊時，**存在一條**同時滿足即可（existential）。

### F. 排序（`sort`）

使用 QF-Event；預設全列時注意 E5 插入後 id 順序以 fixture 實際 AGE id 為準（測試用名稱集合＋穩定排序鍵 assert）。

| ID | `sort` | 預期順序（主體） |
|----|--------|------------------|
| QS-F01 | 未設 / null | 主體 **AGE id ASC**（以 fixture 回傳 id 驗證，勿寫死 E1…E6 順序假設） |
| QS-F02 | `event_date` **asc** | E4, E1, E2, E3, E5, E6 |
| QS-F03 | `event_date` **desc** | E6, E5, E3, E2, E1, E4 |
| QS-F04 | `attendee_count` **desc** | E3, E1, E6, E2, E4, E5 |
| QS-F05 | `score` **asc** | E5, E4, E2, E6, E1, E3（同分再以 id ASC 穩定） |

> v1 **`sort.column_key` 僅允許 `subject_property` 欄的 key**（不可指 `rel_*`／`ep_*`）。見 T6 與下方非目標備註。

### G. 分頁（固定 20）

| ID | 條件 | 預期 |
|----|------|------|
| QS-G01 | QF-Event 全列、`page=1` | 6 列；`total=6` |
| QS-G02 | 人工 seed **25** 筆活動（同 type）、`page=1` / `page=2` | 第 1 頁 20 筆、第 2 頁 5 筆；`total=25` |
| QS-G03 | 篩選後僅 3 筆、`page=2` | 空列或第 2 頁無資料（依 Laravel paginator 慣例） |

### H. 負向與邊界

| ID | 案例 | 預期 |
|----|------|------|
| QS-H01 | `property_filters` 與 `path_filters` 皆空陣列 | 主體 type 下**全部**頂點（含 E5、E6） |
| QS-H02 | 主體 type 下無任何頂點 | 空結果；總筆數 0 |
| QS-H03 | `contains` 空字串 | **驗證拒絕**（Form Request） |
| QS-H04 | `between` from > to | **驗證拒絕** |
| QS-H05 | `locale` 未設，過濾多語 property | 使用 **app locale** 對應欄位 |
| QS-H06 | `locale` 與 property 的 locale 標籤不一致（如對 `role_ja_jp` 傳 `zh_TW`） | **驗證拒絕** |
| QS-H07 | `path_filters.steps` 空陣列 | 驗證失敗 |
| QS-H08 | `edge_property` 欄位 `step_index` 超出 path 長度 | 驗證失敗 |
| QS-H09 | 引用已刪除的 `vertex_property_id`（查詢前） | 定義失效；不執行查詢 |
| QS-H10 | `contains` 含 Cypher／特殊字元字串 | 安全跳脫；不 500；結果可空 |
| QS-H11 | `sort.column_key` 為 `rel_*`／`ep_*` | 驗證拒絕（v1 僅 subject_property） |

### I. 預覽 API 與查詢一致性

| ID | 案例 | 預期 |
|----|------|------|
| QS-I01 | `POST /admin/topics/preview` body = 與 QS-A11 相同 definition | 回傳主體集合與 **QS-A11** 一致 |
| QS-I02 | preview `page=1` 與前台 `GET /topics/{id}?page=1` 同 Topic 定義 | 列資料一致 |
| QS-I03 | 修改表單尚未儲存 → preview | 結果不寫入 DB，且與儲存後同定義之查詢一致 |
| QS-I04 | create 頁與 edit 頁 preview 同一 payload | 行為與結果一致 |
| QS-I05 | 連續快速變更觸發多次 preview（不 abort） | 最終畫面與**最後一次完成的**回應一致即可（允許中間過時；不強制最新優先） |

### J. 欄位渲染（顯示，非篩選）

使用 QF-Song；主體 `song`；assert 儲存格文字／連結。

| ID | 案例 | 預期 |
|----|------|------|
| QS-J01 | `subject_property` 多語欄 + 明確 `locale` | 顯示該語系值 |
| QS-J02 | `subject_property` 多語欄 + `locale` null | 跟 **app locale** |
| QS-J03 | `relation` 自選 `vertex_property_id`；一歌多位演唱 | **換行**列出；順序穩定（建議對端 AGE **id ASC**） |
| QS-J04 | `relation` + `link_to_vertex` true／false | 有／無 `<a href>` 至 `/graph/vertex/{id}` |
| QS-J05 | `edge_property` + `step_index=0`（一步 vocal 的 `order`） | S1 顯示 `1` 與 `2` 換行（多條 vocal） |
| QS-J06 | `edge_property` + **兩步 path** 的 `step_index=1`（第二步 edge 屬性；fixture 需第二步有 EdgeProperty，或對 `based_in` 加屬性） | 只取第二步 edge 的值 |
| QS-J07 | 顯示 property 值為 null | 該列該格為空字串 |

### 實作備註

1. **參數化測試**：建議 `TopicQueryServiceTest` 以 `#[DataProvider]` 或 dataset 對應 QS-* 表。  
2. **Fixture 建立順序**：先 PG schema（VertexType 等）→ AGE 頂點／邊 → Topic `definition` 引用 PG id。  
3. **id 穩定**：測試內用 fixture 回傳的 M1/E1 AGE id，勿寫死 magic number。  
4. **方向詞彙**：`incoming` / `outgoing` 一律相對**當前 traversal 節點**（主體或步驟累積的當前點）。  
5. **層級 7（T7-*）** 可由本節 QS-* 涵蓋；實作時以 QS 為準，T7 作煙霧測試即可。  
6. **v1 sort**：僅 `subject_property` 欄；`rel_*`／`ep_*` 排序為非目標（QS-H11）。  
7. **E5／E6／三跳／FLOAT／TIMESTAMPTZ／cover**：為補齊缺口與 Issue #4 情境而擴充的 fixture，與初版 QF 表一併維護。

---

## Issue #4 情境測試案例

來源：[issue #4](https://github.com/danny50610/CoHistograph/issues/4) 需求列舉：

1. 特定成員或公司在台灣舉辦的活動  
2. 特定成員或團體出的歌曲（包含 cover 曲）  
3. 用表格呈現近期活動  

**定位：** 這些是 **查詢能力／端到端驗收** 案例（`TopicQueryService` + 前台表格頁），證明通用引擎能表達 issue 情境。  
**不是** 預建種子 Topic（產品決策：不當種子，由管理者之後手動建立）。測試可在 setup 時用 factory **臨時建立** Topic（或只呼叫 QueryService），測完 rollback。

建議測試類：`tests/Feature/Topic/Issue4ScenarioTest.php`（或同等），Fixture 用 QF-Event／QF-Song。

### 情境 1：特定成員或公司在台灣舉辦的活動

| ID | 對應 issue | definition 摘要 | 預期 |
|----|------------|-----------------|------|
| ISS-1-01 | 特定**成員**在台灣辦的活動 | 主體 `event`；`location eq 台灣`；path `organized_by_member` incoming + `target_vertex_id` = **M1**；columns：活動名稱／日期／地點／主辦（relation） | 主體 **E1**；不含 E2（日本）、E3（M2）、E5（無主辦）、E6（公司） |
| ISS-1-02 | 特定**公司**在台灣辦的活動 | 主體 `event`；`location eq 台灣`；path `organized_by_company` incoming + target **C1**；同上欄位風格 | 主體 **E6** |
| ISS-1-03 | 成員＋台灣，排除海外同主辦 | 同 ISS-1-01 條件 | **不含 E2**（M1 有主辦但 location≠台灣） |
| ISS-1-04 | 端到端前台 | 以 ISS-1-01 建**已發布** Topic → 訪客 `GET /topics/{id}` | **200**；表格列對應 E1；可見地點「台灣」與主辦名稱 |

### 情境 2：特定成員或團體出的歌曲（含 cover）

| ID | 對應 issue | definition 摘要 | 預期 |
|----|------------|-----------------|------|
| ISS-2-01 | 特定**成員**出的歌曲（含 cover） | 主體 `song`；path `vocal` incoming + target **V1**；columns：歌名、`is_cover`、演唱者（relation） | **S1, S3, S4**；其中 S4 `is_cover=true` |
| ISS-2-02 | 僅 cover | 同 path target V1 + `is_cover eq true` | **S4** only |
| ISS-2-03 | 特定**團體**出的歌曲 | 主體 `song`；path `group_vocal` incoming + target **G1**；columns：歌名、團體（relation） | **S3**（fixture 中 G1→S3） |
| ISS-2-04 | 成員所屬團體相關曲（多跳） | 兩步：vocal incoming → member outgoing，最後一層 target **G1** | **S1–S4**（經 V1/V2 屬 G1） |
| ISS-2-05 | 端到端前台（含 cover） | 以 ISS-2-01 建已發布 Topic → 訪客開啟表格 | 列含 S4；cover 欄可辨識 true／顯示 |

### 情境 3：用表格呈現近期活動

| ID | 對應 issue | definition 摘要 | 預期 |
|----|------------|-----------------|------|
| ISS-3-01 | 近期活動（日期下界） | 主體 `event`；`event_date gte 2024-06-01`；`sort` = `event_date` **desc**；columns：名稱、日期、地點、是否公開 | 主體 **E6, E5, E3, E2, E1**（依日期新→舊）；**不含 E4**（2023） |
| ISS-3-02 | 近期＋僅公開 | 同上 + `is_public eq true` | **E6, E5, E3, E1**（不含 E2） |
| ISS-3-03 | 表格欄位與排序 | 同 ISS-3-01；assert 列順序與欄位 `label` | 第一列為日期最新者（E6）；表頭含名稱／日期／地點 |
| ISS-3-04 | 端到端前台 | 以 ISS-3-01 建已發布 Topic → 訪客 `GET /topics/{id}` | 唯讀表格＋分頁；**無**篩選控件；順序同 ISS-3-01 |
| ISS-3-05 | preview 與前台一致 | 同 definition 打 `POST /admin/topics/preview` 與已存 Topic 前台頁 | 主體集合與順序一致 |

### 與 QS-* 的對照

| Issue 情境 | 可複用／相近的 QS |
|------------|-------------------|
| ISS-1-01 | QS-E01、QS-B05 |
| ISS-1-02 | QS-B09 |
| ISS-2-01／02 | QS-C01 + `is_cover` filter（新增於本節） |
| ISS-2-03 | 專用 `group_vocal` path（本節） |
| ISS-2-04 | QS-C03／C04 |
| ISS-3-01／02 | QS-A08／A10／A21 + QS-F03 |

### 驗收（相對 issue #4）

- 引擎能以 **結構化 definition** 表達上述三類查詢，無需寫死頁面  
- 前台能以**表格**呈現結果（情境 3 的核心 UI）  
- **不**要求 repo 內預置對應名稱的種子 Topic  

---

### 層級 8：即時預覽 `POST /admin/topics/preview`

| ID | 案例 | 預期 |
|----|------|------|
| T8-01 | 有效未儲存 definition + `page=1` | 回傳表格資料（≤20 列）與總筆數 |
| T8-02 | `page=2` | 第二頁資料 |
| T8-03 | definition 不完整（無主體等） | 不查詢；回傳提示狀態 |
| T8-04 | 查詢錯誤 | 預覽區錯誤訊息；**不**等同儲存失敗 |
| T8-05 | 與前台表格欄位一致 | 欄序、`label`、多值換行規則相同 |

### 層級 9：Graph Schema 刪除保護

| ID | 案例 | 預期 |
|----|------|------|
| T9-01 | 刪除被 Topic 引用的 **VertexType**（含 `subject_vertex_type_id`） | 失敗；錯誤含 Topic name／id |
| T9-02 | 刪除被引用的 **EdgeType**（含 `path`／`path_filters.steps`） | 同上 |
| T9-03 | 刪除被引用的 **VertexProperty**（含 filters 與 columns） | 同上 |
| T9-04 | 刪除被引用的 **EdgeProperty**（含 `edge_property_filters` 與 `edge_property` 欄） | 同上 |
| T9-05 | 無 Topic 引用 | 刪除成功（沿用既有 schema 刪除測試行為） |
| T9-06 | 僅在 `columns[].path[].edge_type_id` 引用 | 仍阻止刪除（掃描路徑完整） |
| T9-07 | 僅在 `edge_property_filters[].edge_property_id` 引用 | 仍阻止刪除 |

### 層級 10：Navbar 與選單

| ID | 案例 | 預期 |
|----|------|------|
| T10-01 | 任意頁面 navbar 左側 | 有「專題」→ `/topics` |
| T10-02 | 訪客 navbar 右側「網站管理」 | **無**「專題管理」 |
| T10-03 | 有 `topic.manage` | 「網站管理」下有「專題管理」 |

### 層級 11：前端表單行為（可选手動／瀏覽器測試）

| ID | 案例 | 預期 |
|----|------|------|
| T11-01 | 變更主體 VertexType | confirm 後清空 filters／columns／sort |
| T11-02 | 動態列 | 屬性過濾、路徑、欄位可新增／刪除／上移／下移 |
| T11-03 | 表單變更 | 防抖後自動呼叫 preview（不 abort 舊請求） |
| T11-04 | 建立／編輯頁 | **無**「在前台開啟」按鈕 |

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
- **Issue #4 三情境**以 ISS-* 查詢／E2E 案例覆蓋；**無**強制種子 Topic  

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
| sort 範圍 | v1 僅 subject_property 欄 |
| Topic 文案多語 | v1 單語 |
| 刪除 Topic | 硬刪除 |
| Schema 刪除 | 含 EdgeProperty 依賴檢查 |
| Issue #4 三情境 | 不當種子；以 ISS-* 測試案例驗證查詢能力 |
