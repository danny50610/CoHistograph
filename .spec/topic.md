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
| GET | `/topics/{slug}` | 表格頁（唯讀；草稿僅管理者可預覽） |

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
| DELETE | `/admin/topics/{topic}` | 刪除 |

---

## 資料模型

### `topics` 表（關聯式 PostgreSQL）

| 欄位 | 型別 | 說明 |
|------|------|------|
| `id` | bigint PK | |
| `name` | string | 顯示名稱（v1 單語） |
| `slug` | string unique | 前台 URL |
| `description` | text nullable | 說明（單語） |
| `is_published` | boolean default false | 是否出現在公開列表 |
| `sort_order` | integer default 0 | 列表手動排序（小到大） |
| `definition` | json | 查詢與欄位定義（見下節） |
| `created_at` / `updated_at` | timestamps | |

不對 AGE 實例頂點做 FK。Schema 參照（vertex type／edge type／property）以 **id 寫在 JSON 內**，由應用層驗證；**不建資料庫 FK**。

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

## 後台 UX

- **結構化表單**，可動態新增／刪除：
  - 屬性過濾列
  - 路徑過濾（每條路徑可多步驟）
  - 欄位列
- VertexType／EdgeType／Property 以現有 schema 下拉選擇（寫入 JSON 的是 id）
- 路徑過濾的目標實例頂點：以既有 graph search 選點，存 AGE id
- 不做 raw JSON 主編輯（除錯用 textarea 非必須）

---

## 前台 UX

### 列表 `/topics`

- 只顯示 `is_published = true`
- 排序：`sort_order` ASC，再 `updated_at` DESC（或實作時選定的次要鍵，需一致）
- 顯示名稱、簡短說明、進入連結

### 表格頁 `/topics/{slug}`

- 標題、說明
- 依 `definition` 查 AGE，渲染 HTML 表格
- **不可**變更篩選、排序、page size
- 僅翻頁
- 關聯多值欄：同一格內換行顯示多個連結／文字
- 若定義引用的 schema 已缺漏（理論上刪除時會阻擋；若仍發生）：顯示「此專題設定已失效」，不要靜默空表

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
- 回傳明確錯誤（指出哪些 Topic name／slug 依賴它）

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
2. **後台 CRUD**：結構化表單＋ Form Request 驗證 `definition`
3. **查詢服務**：依 definition 查 AGE＋分頁
4. **前台**：列表＋表格頁
5. **Schema 刪除保護**：Graph Schema 刪除路徑接入依賴檢查
6. **測試**：Feature tests 覆蓋 CRUD、權限、發布可見性、刪除阻擋、表格查詢快樂路徑／失效定義

---

## 驗收標準

- 有 `topic.manage` 者可建立草稿 Topic，填主體、過濾、路徑、欄位後儲存
- 發布後未登入可於 `/topics` 看到並開啟表格
- 草稿對未授權使用者 404（或等同不可見）
- 前台無法改排序／篩選／page size，僅能翻頁
- 關聯多值欄換行列出
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
| 分頁 | 要；page size 由定義決定 |
| 權限 | `topic.manage`；公開讀已發布 |
| 後台 UI | 結構化表單 |
| 儲存 | `definition` JSON（不建 schema FK） |
| Schema 刪除 | 檢查 Topic 依賴並阻止 |
| 多語 | v1 單語 |
| Issue #4 三情境 | 不當種子，之後手動建 |
