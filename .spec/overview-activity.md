# Overview 動態牆（最近變更）規格

## 概述

在公開的 `/overview` 上方顯示**最近新增的頂點與邊**，讓訪客與登入使用者一眼知道圖資料有哪些新內容。

「查看更多」連到專用列表頁 `/activity`，使用與 Overview 相同的列格式，並支援分頁。

本功能對齊 [#15](https://github.com/danny50610/CoHistograph/issues/15)「讓 user 知道哪些變更」的方向；v1 只涵蓋**已核准並套用**的新增實體，不做完整編輯紀錄公開頁。

相關但不在本規格範圍：

- Topic 專題表格（見 Topic 規格／PR）
- 公開 Revision 詳情頁（`/revisions/{id}` 仍需登入）
- 圖視覺化（[#3](https://github.com/danny50610/CoHistograph/issues/3)）

---

## 目標與非目標

### 目標（v1）

1. Overview 顯示最近 **10** 筆活動列（無分頁）
2. 「查看更多」連到專用頁 `/activity`（分頁，每頁 **20**）
3. 統一列格式；以 **icon** 區分頂點／邊
4. 資料來源：已核准 Revision 的 `create_vertex`、`create_edge`
5. 核准套用時**回寫**新建實體的 AGE ID，供動態牆解析與連結
6. 公開可讀（與現有 `/overview` 相同，無需登入）

### 非目標（v1）

- 顯示 `*_property`、`delete_*`、`update_*` 等其他 action
- 依類型／動作篩選
- 公開 Revision 詳情或貢獻者個人頁連結
- 為每個 VertexType／EdgeType 配置專屬 icon
- 力導向圖或縮圖
- Overview 精簡／移除既有「依類型瀏覽」區塊（可並存；另案再改）
- 回填歷史已核准 Revision 的 AGE ID（僅保證本功能上線後新核准的資料）

---

## 名詞

| 名稱 | 說明 |
|------|------|
| 活動列（activity item） | 動態牆上的一列：對應一筆 `create_vertex` 或 `create_edge` |
| 核准時間 | 該 Revision 最新一筆 `revision_reviews.action = approved` 的 `created_at` |
| 顯示名稱 | 經 `VertexDisplayNameResolver` 解析的 Vertex 標題 |

---

## 路由與資訊架構

| Method | Path | Route name | 說明 |
|--------|------|------------|------|
| GET | `/overview` | `overview` | 既有；上方新增「最近變更」區塊 |
| GET | `/activity` | `activity.index` | 專用列表；公開；分頁 |

Navbar：**不**新增獨立選單項。進入點只有 Overview 的「查看更多」。品牌仍連 `/overview`。

---

## UI

### 共用列格式

每一列結構固定，僅 icon 與主文案語意不同：

```text
[icon]  主文案
        次要：類型標籤 · 相對時間
```

| 欄位 | `create_vertex` | `create_edge` |
|------|-----------------|---------------|
| icon | `fa-solid fa-circle-nodes` | `fa-solid fa-arrow-right-arrow-left` |
| 主文案 | Vertex displayName（連到 `/graph/vertex/{id}`） | `起點 displayName → 終點 displayName`（兩端各自可連） |
| 次要 | VertexType `name` · 相對時間 | EdgeType `name`（正向） · 相對時間 |

示意：

```text
⬤  星街彗星
    人物 · 2 小時前

⇄  星街彗星 → 夢が丘
    主唱 · 2 小時前
```

規則：

- **不**在列上寫「新增／新增關係」——v1 全是新增，icon 已表達實體種類
- EdgeType 一律用正向 `name`（不用 `reverse_name`）
- 邊屬性、頂點屬性細節**不**出現在動態牆
- 相對時間用 Laravel／Carbon 慣用 diff（與專案其他處一致即可）
- displayName 為空：主文案 fallback 為 `未命名（{VertexType.name}）`
- 實體已自 AGE 刪除或不存在：主文案顯示純文字（無連結），可加後綴 `（已刪除）`；列仍保留（依核准紀錄）

### Overview `/overview`

版面由上到下：

1. `h1`：Overview（維持現狀）
2. **最近變更**
   - `h2`：最近變更
   - 最多 **10** 列（共用列 partial）
   - 若有資料：文末「查看更多」→ `route('activity.index')`
   - 若無任何活動列：顯示空狀態「目前還沒有公開的變更」，**不**顯示「查看更多」
3. 既有「查看所有 Vertex」與依 `overview_order` 的類型瀏覽（本規格不改行為）

### 專用頁 `/activity`

1. 返回 Overview 按鈕
2. `h1`：最近變更
3. 活動列列表（共用 partial）
4. 底部分頁（每頁 **20**，固定）
5. 空狀態：「目前還沒有公開的變更」

不做篩選控件。

---

## 資料來源與查詢

### 納入條件

一筆 `revision_actions` 成為活動列，當且僅當：

1. 所屬 `revisions.status = approved`
2. `action` ∈ {`create_vertex`, `create_edge`}
3. 對應 AGE ID 可解析（見「核准時回寫 AGE ID」）
   - `create_vertex`：`target_age_id` 非 null
   - `create_edge`：`start_vertex_age_id`、`end_vertex_age_id` 皆非 null（`target_age_id` 可選存邊 id，v1 動態牆不連邊詳情頁）

### 排序

1. 核准時間 **DESC**（`revision_reviews.created_at`，該 revision 最新一筆 `approved`）
2. 同 revision 內依 `revision_actions.order` **ASC**（保留提交順序）
3. 再以 `revision_actions.id` ASC 穩定排序

Overview 取前 10；`/activity` 依此排序分頁。

### 建議查詢形狀

由 `ActivityFeedService`（名稱可調整）負責：

1. Join：`revision_actions` → `revisions` → 最新 `approved` review
2. Filter：status、action 類型、必要 AGE id 非 null
3. Paginate / limit
4. 批次載入 VertexType／EdgeType（依 `vertex_type_label`／`edge_type_label`）
5. 批次自 AGE 讀取頂點 properties，以 `VertexDisplayNameResolver` 解析顯示名

避免 N+1：同一頁內的起迄頂點 id 去重後一次查。

### 時間欄位

列上的時間 = **核准時間**，不是 `revision_actions.created_at`（草稿建立時間）也不是 AGE 內部時間。

實作注意：一個 Revision 理論上只有一筆 `approved` review；取 `reviews()->where('action','approved')->latest()` 或等價 subquery。

---

## 前置條件：核准時回寫 AGE ID

### 現況問題

`RevisionApplyService` 在 `create_vertex`／`create_edge` 時把新建 graphid 只放在記憶體 map（`vertexIdsByOrder`／`edgeIdsByOrder`），**不會**寫回 `revision_actions`。因此核准後無法從 action 列直接知道新建實體的 AGE ID，動態牆無法連結與解析顯示名。

### 要求

在 `apply` 成功套用每個 action 後（或整批 apply 結束、commit 前），回寫：

| Action | 回寫欄位 |
|--------|----------|
| `create_vertex` | `target_age_id` = 新建 vertex id |
| `create_edge` | `target_age_id` = 新建 edge id；若 `start_vertex_ref_order`／`end_vertex_ref_order` 有值，一併寫入對應的 `start_vertex_age_id`／`end_vertex_age_id`（保留原 ref_order 欄位不清除，方便稽核） |

屬性類 action 若透過 `target_ref_order` 指向新建實體，**不強制**回寫其 `target_age_id`（動態牆 v1 不讀這些 action）。可選優化，另案。

回寫必須與 AGE 寫入在同一 DB transaction 語意下完成（至少保証 approve 成功後關聯式資料已有 id）。既有 approve 流程：AGE apply → 再更新 revision status／review；回寫 action 應在 apply 階段完成，避免核准成功但 id 遺失。

### 歷史資料

本功能上線**前**已核准的 `create_vertex`／`create_edge` 可能沒有 `target_age_id`。v1：

- 查詢時略過缺少必要 id 的列
- **不做**一次性回填 migration／command（除非另開任務）

---

## 權限與可見性

| 對象 | Overview 動態牆 | `/activity` |
|------|-----------------|-------------|
| 未登入 | 可看 | 可看 |
| 登入使用者 | 可看 | 可看 |

不需新權限。不暴露 Revision title、提交者、審核者於 v1 列上（降低隱私與「未公開 revision 頁」的落差）。

---

## 實作約定

### 技術選型

| 區塊 | 技術 |
|------|------|
| Overview／Activity 頁 | Blade + Bootstrap（對齊現有 `overview`） |
| 列 UI | Blade partial，例如 `resources/views/activity/partials/item.blade.php` |
| 查詢 | `ActivityFeedService`；`HomeController@overview` 與 `ActivityController@index` 共用 |
| Icon | Font Awesome 7（`layouts.app` 已引入） |

### 控制器

- `HomeController@overview`：既有 vertex 列表 + 注入 `$activityItems`（最多 10）
- 新增 `ActivityController@index`：分頁列表

### 顯示名稱

複用 `App\Support\VertexDisplayNameResolver` 與 VertexType 的 `show_property_name`／properties（含 locale fallback）。Edge **沒有** display name；只顯示兩端頂點名 + EdgeType `name`。

---

## 與現有功能的關係

| 功能 | 關係 |
|------|------|
| `/overview` 類型瀏覽 | 並存於動態牆下方；本規格不改其查詢 |
| Vertex show Edge 區塊 | 詳情仍在該頁；動態牆不重複屬性 |
| Revision 審核 | 核准＝活動列資料來源；需擴充 apply 回寫 id |
| Topic | 策展表格；動態牆是全域時間序，兩者互補 |
| `#15` 點子 | 本功能為「讓 user 知道哪些變更」的最小公開切片 |

---

## 測試案例

建議類別：`tests/Feature/Activity/ActivityFeedTest.php`（或同等），並擴充既有 approve／apply 測試。

### A. Apply 回寫 AGE ID

| ID | 案例 | 預期 |
|----|------|------|
| A01 | 核准含 `create_vertex` 的 revision | 該 action 的 `target_age_id` 等於 AGE 新建 id |
| A02 | 核准含 `create_edge`（兩端皆 age id） | `target_age_id`＝edge id；start／end age id 維持 |
| A03 | 核准含 `create_vertex` + `create_edge`（兩端 ref_order） | edge 的 start／end age id 被回寫為對應新建 vertex id |
| A04 | 核准失敗／驗證失敗 | 不回寫、不出現於動態牆 |

### B. Overview 動態牆

| ID | 案例 | 預期 |
|----|------|------|
| B01 | 無已核准 create action | 空狀態；無「查看更多」 |
| B02 | 剛好 3 筆 | 顯示 3 列；有「查看更多」 |
| B03 | 超過 10 筆 | Overview 只顯示最新 10；「查看更多」連到 `/activity` |
| B04 | 訪客可開啟 `/overview` | 200；可見活動區塊 |
| B05 | 頂點列 | icon／displayName 連結／類型名／時間 |
| B06 | 邊列 | icon／`A → B` 兩端連結／EdgeType name／時間 |
| B07 | 同 revision 多 action | 依 `order` 出現在同一核准時間下 |

### C. `/activity`

| ID | 案例 | 預期 |
|----|------|------|
| C01 | 第一頁 | ≤20 列；排序同規格 |
| C02 | 第二頁 | 續頁資料正確 |
| C03 | 空列表 | 空狀態 |
| C04 | 訪客可開啟 | 200；無需登入 |
| C05 | 返回 Overview | 有連回 `route('overview')` |

### D. 邊界

| ID | 案例 | 預期 |
|----|------|------|
| D01 | 歷史核准、無 `target_age_id` 的 create_vertex | 不出現在 feed |
| D02 | 頂點之後被 delete | 列仍在；無連結或「已刪除」文案 |
| D03 | displayName 為空 | fallback `未命名（{type}）` |
| D04 | 僅有 property／delete action 的核准 revision | 不產生活動列 |
| D05 | Overview 與 `/activity` 前 10 筆一致 | 同一排序下 activity 第 1 頁前 10 = overview |

---

## 驗收標準

- Overview 上方有「最近變更」；最多 10 列；「查看更多」→ `/activity`
- `/activity` 公開、每頁 20、同一列格式
- 頂點／邊以約定 Font Awesome icon 區分
- 邊主文案為 `起點 → 終點`，次行為 EdgeType `name`
- 資料僅來自已核准的 `create_vertex`／`create_edge`
- 核准 apply 會回寫新建 AGE id（含 edge 端點 ref 解析）
- 缺少 id 的歷史列被略過；已刪除實體降級為純文字

---

## 決策紀錄

| 決策 | 選擇 |
|------|------|
| 放置位置 | Overview 動態牆（既有類型瀏覽上方） |
| 列格式 | 統一結構；icon 區分頂點／邊 |
| 頂點 icon | `fa-circle-nodes` |
| 邊 icon | `fa-arrow-right-arrow-left` |
| 動作文案 | v1 不寫「新增」（皆為新增） |
| 納入 action | 僅 `create_vertex`、`create_edge` |
| 屬性變更 | v1 不顯示 |
| Overview 筆數 | 10，無分頁 |
| 查看更多 | 連專用頁 `/activity` |
| `/activity` 分頁 | 每頁 20 |
| 篩選 | v1 不做 |
| 時間 | 核准時間（approved review） |
| 公開 revision 詳情 | v1 不連 |
| 貢獻者資訊 | v1 不顯示 |
| Navbar | 不新增項目 |
| AGE id | apply 時回寫；不回填歷史 |
| 既有 Overview 類型區 | 保留並存 |
