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

1. 管理者可 CRUD Topic（含草稿／發布、手動排序）
2. 以結構化查詢定義（非自由 Cypher）從 Apache AGE 取出列資料
3. 前台公開列出已發布 Topic，並以固定表格＋分頁顯示
4. 支援多層路徑過濾、主體／關聯欄位、常用屬性運算子
5. 刪除 Graph Schema 前檢查 Topic JSON 依賴並阻止

### 非目標（v1）

- 前台動態篩選、排序、改 page size
- 自由 Cypher／SQL 編輯
- OR 條件組、正規表示式
- 聚合欄（count／sum 等）
- 多語系名稱／說明／欄位標題
- 圖表或 graph canvas
- 一般使用者自建私人 Topic
- Issue #4 三個情境的種子 Topic

---

## 名詞

| 名稱 | 說明 |
|------|------|
| Topic（專題） | 一組可發布的表格視圖定義：主體類型、過濾、欄位、排序、分頁 |
| 主體（subject） | 表格每一列對應的主 vertex type |
| 路徑過濾（path filter） | 以 edge 步驟陣列描述的多層關聯條件 |
| 關聯欄（relation column） | 沿路徑取相關 vertex 的顯示值 |

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
| 有 `topic.manage` | 可看 | 可預覽 |

---

## 路由與資訊架構

### 前台

| Method | Path | 說明 |
|--------|------|------|
| GET | `/topics` | 已發布列表（依 `sort_order`，同序再依更新時間） |
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

建議前綴：`/admin/topics`（或與現有 Admin 路由風格一致）

| Method | Path | 說明 |
|--------|------|------|
| GET | `/admin/topics` | 管理列表（含草稿） |
| GET | `/admin/topics/create` | 建立表單 |
| POST | `/admin/topics` | 儲存 |
| GET | `/admin/topics/{topic}/edit` | 編輯表單 |
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

> `page_size` 放在 `definition` JSON 內（見下節），不單獨成 column。

---

## `definition` JSON 結構

以下為 v1 約定形狀（實作時可用 Form Request／DTO 驗證；欄位名稱可微調但語意需對齊）。

```json
{
  "subject_vertex_type_id": 1,
  "page_size": 20,
  "sort": [
    {
      "column_key": "event_date",
      "direction": "desc"
    }
  ],
  "property_filters": [
    {
      "vertex_property_id": 10,
      "operator": "eq",
      "value": "台灣"
    },
    {
      "vertex_property_id": 11,
      "operator": "between",
      "value": { "from": "2024-01-01", "to": "2024-12-31" }
    }
  ],
  "path_filters": [
    {
      "steps": [
        {
          "edge_type_id": 5,
          "direction": "outgoing",
          "target_vertex_type_id": 2
        }
      ],
      "target_vertex_id": 12345
    }
  ],
  "columns": [
    {
      "key": "title",
      "label": "名稱",
      "type": "subject_property",
      "vertex_property_id": 1,
      "link_to_subject": true
    },
    {
      "key": "organizers",
      "label": "主辦",
      "type": "relation",
      "path": [
        {
          "edge_type_id": 5,
          "direction": "outgoing",
          "target_vertex_type_id": 2
        }
      ],
      "display": "show_property",
      "link_to_vertex": true
    }
  ]
}
```

### 欄位說明

#### 根層

| Key | 說明 |
|-----|------|
| `subject_vertex_type_id` | 主體 VertexType id（必填） |
| `page_size` | 每頁筆數；前台不可改 |
| `sort` | 預設排序；前台不可改。`column_key` 對應 `columns[].key` 或約定的主體屬性 key |
| `property_filters` | 主體屬性過濾，條件之間 **AND** |
| `path_filters` | 多層路徑過濾，條件之間 **AND** |
| `columns` | 表格欄位（順序即顯示順序） |

#### `property_filters[].operator`（v1）

| Operator | 適用 | `value` |
|----------|------|---------|
| `eq` | 字串／數字／日期／布林等 | 單一值 |
| `contains` | 字串 | 字串 |
| `gt` / `gte` / `lt` / `lte` | 數字、日期／時間 | 單一值 |
| `between` | 日期／時間（亦可數字） | `{ "from": ..., "to": ... }` |
| `is_null` / `is_not_null` | 任何 | 無（或忽略） |

不做 regex、不做 OR 群組。

#### `path_filters` / 關聯欄 `path` 的步驟

每步：

| Key | 說明 |
|-----|------|
| `edge_type_id` | EdgeType id |
| `direction` | `outgoing` 或 `incoming`（相對當前節點） |
| `target_vertex_type_id` | 可選；用於驗證／限制對端類型 |

路徑可多層（步驟陣列）。`path_filters` 可選 `target_vertex_id`：最後一層對端須等於該 **AGE vertex id**（非關聯式 FK）。未指定時表示「存在符合路徑的關聯即可」。

#### `columns[]`

| `type` | 說明 |
|--------|------|
| `subject_property` | 主體上的 VertexProperty |
| `relation` | 沿 `path` 取相關頂點；顯示其 show property（或約定顯示欄） |

系統行為：

- 主體列應能連到既有 `/graph/vertex/{id}` 詳情（由 `link_to_subject` 控制）
- 關聯多值：**全部列出、換行、可點連結**（`link_to_vertex`）
- v1 不做聚合欄

---

## 後台畫面

對齊現有 Blade＋Bootstrap 管理頁（Graph Schema／角色表單風格）。需 `topic.manage`。

### 管理列表 `/admin/topics`

**版面：**

1. `h1`：專題管理  
2. 「新增專題」按鈕 → create  
3. Topic 卡片列表（含草稿與已發布；**不做**狀態篩選）  
4. 空狀態卡（若無任何 Topic）

**每張卡：**

| 元素 | 說明 |
|------|------|
| 名稱 | 主要文字 |
| 發布狀態 | badge（已發布／草稿） |
| 說明 | 有則顯示（可截斷） |
| `sort_order` | 顯示目前值；於編輯表單修改 |
| 操作 | 編輯、預覽前台（`/topics/{id}`）、刪除 |

**刪除：** 按鈕＋ `confirm('確定要刪除此專題嗎？')` 後 **硬刪除**（對齊 Graph Schema）。不做軟刪除、不做獨立確認頁。

列表排序建議：`sort_order` ASC，再 `id` ASC。

### 建立／編輯表單 `/admin/topics/create`、`/admin/topics/{topic}/edit`

**單頁分區塊**（不做 wizard；不做儲存前即時查詢預覽）：

1. **基本資料**
   - `name`（必填）
   - `description`（textarea，可選）
   - `is_published`（checkbox）
   - `sort_order`（number）
   - `page_size`（number；寫入 `definition.page_size`）
   - **無 slug 欄位**；前台以 id 識別
2. **主體**：`subject_vertex_type_id` 下拉（現有 VertexType）
3. **屬性過濾**：可動態新增／刪除列；每列 property 下拉、operator、value；列可上移／下移
4. **路徑過濾**：每條路徑一張小卡；卡內步驟可新增／刪除／上移／下移；可選目標 AGE vertex（既有 graph search 選點；可留空）
5. **欄位**：可動態新增／刪除／上移／下移；依 type 顯示 subject_property 或 relation 相關欄位
6. **預設排序**：對應 `definition.sort`
7. **儲存**／**返回列表**

控件原則：

- VertexType／EdgeType／Property：schema 下拉，JSON 存 id  
- 動態列：**新增／刪除＋上移／下移**（不做拖曳）  
- 不做 raw JSON 主編輯  

編輯頁可另提供「預覽」連到前台 `/topics/{id}`（草稿時靠權限可見）。

---

## 前台畫面

對齊現有 Blade＋Bootstrap 風格（`layouts.app`、`container`、卡片／`table`），不另做設計系統。

### Navbar

見上方「Navbar（`MenuService`）」：左邊「專題」→ `/topics`。

### 列表頁 `/topics`

**版面（由上到下）：**

1. `h1`：專題  
2. 已發布 Topic 卡片列表（`sort_order` ASC，同序再 `updated_at` DESC）  
3. 若無任何已發布 Topic：一張空狀態卡（文案如「目前還沒有專題」；**不**附管理後台連結）

**每張專題卡：**

| 元素 | 說明 |
|------|------|
| 名稱 | 主要文字，連到 `/topics/{id}` |
| 說明 | 有 `description` 才顯示；可截斷過長文字（實作時用既有／簡單 CSS 即可） |

不做：縮圖、統計、更新時間、列數、篩選。

瀏覽器 `<title>`：`專題 - {app display-name}`（對齊既有 `@section('title')`）。

### 表格頁 `/topics/{id}`

**版面（由上到下）：**

1. **草稿預覽提示**（僅當未發布且檢視者有 `topic.manage`）：Bootstrap alert，文案如「此專題尚未發布，僅管理者可見」，並附「前往編輯」連到後台編輯頁  
2. 返回按鈕 → `/topics`  
3. `h1`：專題名稱  
4. 說明（有則顯示）  
5. 資料表格  
6. 分頁（`{{ $paginator->links() }}`，對齊修訂／schema 列表）

**不顯示：** 查詢條件摘要、排序說明、主體類型、page size 選擇器、欄位排序控制。

**表格：**

- Bootstrap `table`，外層 `table-responsive`（小螢幕橫向捲動）  
- 欄位順序＝`definition.columns` 順序；表頭文字＝各欄 `label`  
- 主體可連結欄：連到 `/graph/vertex/{id}`  
- 關聯多值欄：同一格內**換行**列出；可連結者各成一行連結  
- **0 列**：保留表頭，一行 `colspan` 提示「目前沒有符合的資料」  
- **定義失效**（引用的 schema 缺漏等）：不渲染空表；改顯示錯誤狀態（如 alert「此專題設定已失效」）  
- 分頁在表格下方；page size 完全由定義決定

瀏覽器 `<title>`：`{專題名稱} - {app display-name}`。  
若有說明，可選擇放入 meta description（選用，非必須）。

### 前台權限與錯誤（畫面層）

| 情況 | 行為 |
|------|------|
| 未發布＋無 `topic.manage` | 404（或與專案慣例一致的不可見） |
| 未發布＋有 `topic.manage` | 正常表格＋草稿 alert |
| id 不存在 | 404 |
| 定義失效 | 200＋失效提示（管理者亦可見編輯入口，選用） |

---

## 查詢執行

1. 讀取 Topic `definition`，解析並驗證引用的 schema id 仍存在
2. 組出 Apache AGE Cypher（或專案既有查詢封裝）
3. 套用 property_filters、path_filters（AND）
4. 依 `sort` 排序，再依 `page_size` 分頁
5. 依 `columns` 組列資料（關聯欄另外解析路徑結果）

實作應落在 Service 層（例如 `TopicQueryService`），Controller 不寫 Cypher。

---

## Graph Schema 刪除保護

即使 `definition` 為 JSON、無 DB FK，刪除下列資源前仍須掃描所有 Topic 的 `definition`：

- VertexType
- EdgeType
- VertexProperty
- EdgeProperty（若未來欄位／過濾會引用）

若任一 Topic 仍引用該 id：

- **阻止刪除**
- 回傳明確錯誤（指出哪些 Topic name／id 依賴它）

建立／更新 Topic 時亦須驗證 JSON 內所有引用 id 存在且語意合理（例如 property 屬於 subject type、edge 方向與端點類型相容——能做多少做多少，至少 id 存在）。

---

## 與現有功能的關係

| 現有 | 關係 |
|------|------|
| `/overview`、`/graph/vertex` | 通用瀏覽；Topic 是策展式表格，不取代它們 |
| `/graph-schema/visualization` | Schema 視覺化；與 Topic 無關 |
| Revision | Topic 只讀已套用到 AGE 的資料，不經修訂流程寫入 |
| Laratrust | 新增 `topic.manage` |

---

## 實作里程碑建議

1. **資料與權限**：`topics` migration、Model、`topic.manage`、Navbar（左「專題」、右「專題管理」）
2. **後台 CRUD**：管理列表＋單頁分區塊表單＋ Form Request 驗證 `definition`；硬刪除
3. **查詢服務**：依 definition 查 AGE＋分頁
4. **前台**：列表卡片＋`/topics/{id}` 表格頁（含空狀態、草稿 alert、responsive table）
5. **Schema 刪除保護**：Graph Schema 刪除路徑接入依賴檢查
6. **測試**：Feature tests 覆蓋 CRUD、權限、發布可見性、刪除阻擋、表格查詢快樂路徑／失效定義

---

## 驗收標準

- 未登入使用者 navbar 左邊可見「專題」並進入 `/topics`
- `/topics` 為卡片列表（名稱＋說明）；無資料時顯示空狀態卡
- `/topics/{id}` 為返回＋標題＋說明＋表格＋分頁；草稿有預覽警告
- 有 `topic.manage` 者在「網站管理」下可見「專題管理」
- 管理列表可新增／編輯／預覽／硬刪除（confirm）
- 建立／編輯為單頁分區塊；動態列可新增／刪除／上移／下移；無 slug
- 有 `topic.manage` 者可建立草稿 Topic，填主體、過濾、路徑、欄位後儲存
- 發布後未登入可於 `/topics` 看到並開啟表格
- 草稿對未授權使用者 404（或等同不可見）
- 前台無法改排序／篩選／page size，僅能翻頁
- 關聯多值欄換行列出；0 列時保留表頭並提示無資料
- 刪除仍被 Topic 引用的 VertexType／EdgeType／Property 會失敗並提示依賴
- 無 issue #4 三情境的強制種子資料

---

## 決策紀錄

| 決策 | 選擇 |
|------|------|
| 名稱 | Topic（專題），不用 view |
| Navbar | 左邊公開「專題」→ `/topics`；右邊「網站管理」下「專題管理」（`topic.manage`） |
| 可設定方式 | 後台 CRUD，非寫死 config |
| 呈現 | 先做表格 |
| 查詢 | 結構化 JSON，非 raw Cypher |
| 路徑 | 多層步驟陣列，條件 AND |
| 欄位 | 主體屬性＋關聯欄 |
| 多值關聯 | 全部列出、換行、可連結 |
| 前台互動 | 完全固定（選項 C），僅分頁 |
| 前台列表 | 簡單卡片：名稱＋說明；空狀態卡（無管理連結） |
| 前台表格頁 | 返回＋標題＋說明＋responsive table；草稿 alert；空資料保留表頭；URL 用 id |
| 前台識別 | `/topics/{id}`，無 slug |
| 分頁 | 要；page size 由定義決定 |
| 權限 | `topic.manage`；公開讀已發布 |
| 後台列表 | 卡片＋狀態 badge＋編輯／預覽／硬刪除；無狀態篩選 |
| 後台表單 | 單頁分區塊；動態列新增／刪除／上移／下移 |
| 刪除 | confirm 後硬刪除（不軟刪） |
| 儲存 | `definition` JSON（不建 schema FK） |
| Schema 刪除 | 檢查 Topic 依賴並阻止 |
| 多語 | v1 單語 |
| Issue #4 三情境 | 不當種子，之後手動建 |
