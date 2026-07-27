# Overview 動態牆（最近變更）規格

## 概述

在公開的 `/overview` 上方顯示**最近變更的頂點與邊**（含新增、編輯屬性、刪除），讓訪客與登入使用者一眼知道圖資料有哪些變動。

「查看更多」連到專用列表頁 `/activity`，使用與 Overview 相同的列格式，並支援分頁。

本功能對齊 [#15](https://github.com/danny50610/CoHistograph/issues/15)「讓 user 知道哪些變更」的方向；v1 涵蓋**已核准並套用**的全部 graph action 類型，但不做公開 Revision 詳情頁。

相關但不在本規格範圍：

- Topic 專題表格（見 Topic 規格／PR）
- 公開 Revision 詳情頁（`/revisions/{id}` 仍需登入）
- 圖視覺化（[#3](https://github.com/danny50610/CoHistograph/issues/3)）

---

## 目標與非目標

### 目標（v1）

1. Overview 顯示最近 **10** 筆活動列（無分頁）
2. 「查看更多」連到專用頁 `/activity`（分頁，每頁 **20**）
3. 統一列格式；以 **icon** 區分頂點／邊；以 **動作文案** 區分新增／修改／刪除
4. 資料來源：已核准 Revision 的**全部** `revision_actions`（10 種 action）
5. 核准套用時**回寫** AGE ID，並寫入 **`activity_snapshot`**（供刪除後仍能顯示名稱）
6. 公開可讀（與現有 `/overview` 相同，無需登入）

### 非目標（v1）

- 依類型／動作篩選（頂點／邊、新增／刪除等）
- 公開 Revision 詳情或貢獻者個人頁連結
- 為每個 VertexType／EdgeType 配置專屬 icon
- 力導向圖或縮圖
- Overview 精簡／移除既有「依類型瀏覽」區塊（可並存；另案再改）
- 回填歷史已核准 Revision 的 AGE ID／snapshot（僅保證本功能上線後新核准的資料）

---

## 名詞

| 名稱 | 說明 |
|------|------|
| 活動列（activity item） | 動態牆上的一列：對應一筆已核准的 `revision_actions` |
| 核准時間 | 該 Revision 最新一筆 `revision_reviews.action = approved` 的 `created_at` |
| 顯示名稱 | 經 `VertexDisplayNameResolver` 解析的 Vertex 標題 |
| `activity_snapshot` | apply 當下寫入 action 的 JSON 快照，供 feed 顯示（刪除後仍可用） |

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

每一列結構固定：

```text
[icon]  主文案
        次要：動作 · 類型標籤 ·（屬性摘要）· 相對時間
```

#### Icon（依實體種類，不依動作）

| 實體 | icon |
|------|------|
| 頂點相關（`*_vertex*`） | `fa-solid fa-circle-nodes` |
| 邊相關（`*_edge*`） | `fa-solid fa-arrow-right-arrow-left` |

刪除列可用 `text-secondary` 或略淡樣式；**不**另換 icon。

#### 動作文案

| Action | 動作文案 |
|--------|----------|
| `create_vertex` / `create_edge` | 新增 |
| `delete_vertex` / `delete_edge` | 刪除 |
| `create_vertex_property` / `create_edge_property` | 新增屬性 |
| `update_vertex_property` / `update_edge_property` | 修改屬性 |
| `delete_vertex_property` / `delete_edge_property` | 刪除屬性 |

#### 主文案

| Action 類 | 主文案 |
|-----------|--------|
| Vertex 本體／Vertex 屬性 | Vertex displayName（可連 `/graph/vertex/{id}`；已刪除則純文字） |
| Edge 本體／Edge 屬性 | `起點 displayName → 終點 displayName`（兩端各自可連；已刪除端點則該端純文字） |

#### 次要列

| Action 類 | 次要 |
|-----------|------|
| `create_vertex` / `delete_vertex` | `{動作} · {VertexType.name} · {相對時間}` |
| `create_edge` / `delete_edge` | `{動作} · {EdgeType.name} · {相對時間}` |
| Vertex 屬性 | `{動作} · {VertexType.name} · {屬性標籤}{=值?} · {相對時間}` |
| Edge 屬性 | `{動作} · {EdgeType.name} · {屬性標籤}{=值?} · {相對時間}` |

屬性摘要規則：

- 屬性標籤：用既有 `LocalizedPropertyLabelResolver`（或等價）顯示人類可讀名稱，不是裸 `age_property_name`
- `create_*_property`／`update_*_property`：附加 ` = {value}`；value 過長（建議 > 40 字）截斷加 `…`
- `delete_*_property`：只顯示屬性標籤，不顯示舊值
- EdgeType 一律用正向 `name`（不用 `reverse_name`）

示意：

```text
⬤  星街彗星
    新增 · 人物 · 2 小時前

⇄  星街彗星 → 夢が丘
    新增 · 主唱 · 2 小時前

⬤  星街彗星
    修改屬性 · 人物 · 暱稱 = Suisei · 1 小時前

⇄  星街彗星 → 夢が丘
    刪除屬性 · 主唱 · 曲序 · 50 分鐘前

⬤  舊頻道名稱（已刪除）
    刪除 · 人物 · 30 分鐘前
```

其他規則：

- displayName 為空：fallback `未命名（{VertexType.name}）`
- 實體已不存在（含本列即為刪除、或之後另案刪除）：主文案無連結，可加 `（已刪除）`
- 相對時間用 Laravel／Carbon 慣用 diff

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
2. `action` 為下列任一：
   - `create_vertex`、`delete_vertex`
   - `create_edge`、`delete_edge`
   - `create_vertex_property`、`update_vertex_property`、`delete_vertex_property`
   - `create_edge_property`、`update_edge_property`、`delete_edge_property`
3. `activity_snapshot` 非 null（見「核准時回寫與快照」）

> 上線前的歷史核准列若無 snapshot，查詢時略過。

### 排序

1. 核准時間 **DESC**（`revision_reviews.created_at`，該 revision 最新一筆 `approved`）
2. 同 revision 內依 `revision_actions.order` **ASC**（保留提交順序）
3. 再以 `revision_actions.id` ASC 穩定排序

Overview 取前 10；`/activity` 依此排序分頁。

### 建議查詢形狀

由 `ActivityFeedService`（名稱可調整）負責：

1. Join：`revision_actions` → `revisions` → 最新 `approved` review
2. Filter：status、十種 action、`activity_snapshot` 非 null
3. Paginate / limit
4. **顯示文案以 `activity_snapshot` 為準**（不必為歷史列重查 AGE）
5. 可選：對仍存在的 vertex id 檢查是否可連結（不存在則強制無連結 + `（已刪除）`）

避免依賴即時 AGE 組主文案，以免刪除列空白、也避免 N+1。

### 時間欄位

列上的時間 = **核准時間**，不是 `revision_actions.created_at`，也不是 AGE 內部時間。

---

## 前置條件：核准時回寫 AGE ID 與 activity_snapshot

### 現況問題

1. `RevisionApplyService` 在 `create_vertex`／`create_edge` 時把新建 graphid 只放在記憶體 map，**不會**寫回 `revision_actions`。
2. 屬性 action 可能只用 `target_ref_order`，核准後未必有 `target_age_id`。
3. `delete_*` 套用後 AGE 實體消失，之後無法再解析 displayName／端點名稱。

### Schema 變更

`revision_actions` 新增：

| 欄位 | 型別 | 說明 |
|------|------|------|
| `activity_snapshot` | `jsonb` nullable | apply 成功後寫入；feed 顯示用 |

### 回寫 AGE ID

在 `apply` 每個 action 成功後回寫（保留既有 `*_ref_order` 不清除）：

| Action | 回寫 |
|--------|------|
| `create_vertex` | `target_age_id` = 新建 vertex id |
| `create_edge` | `target_age_id` = 新建 edge id；解析並寫入 `start_vertex_age_id`／`end_vertex_age_id` |
| `*_vertex_property` | 若僅有 `target_ref_order`，回寫 `target_age_id` = 對應 vertex id |
| `*_edge_property` | 若僅有 `target_ref_order`，回寫 `target_age_id` = 對應 edge id |
| `delete_vertex`／`delete_edge` | 維持既有 target id（刪除前即可用） |

### `activity_snapshot` 內容（建議 shape）

於**該 action 套用當下**寫入（刪除類須在 AGE delete **之前**解析名稱）：

```json
{
  "entity_kind": "vertex",
  "action_label": "新增",
  "type_name": "人物",
  "type_label": "person",
  "primary": {
    "kind": "vertex",
    "age_id": 123,
    "display_name": "星街彗星"
  },
  "start": null,
  "end": null,
  "property": null
}
```

邊範例：

```json
{
  "entity_kind": "edge",
  "action_label": "刪除",
  "type_name": "主唱",
  "type_label": "vocal",
  "primary": {
    "kind": "edge",
    "age_id": 456,
    "display_name": null
  },
  "start": { "kind": "vertex", "age_id": 123, "display_name": "星街彗星" },
  "end": { "kind": "vertex", "age_id": 789, "display_name": "夢が丘" },
  "property": null
}
```

屬性範例：

```json
{
  "entity_kind": "vertex",
  "action_label": "修改屬性",
  "type_name": "人物",
  "type_label": "person",
  "primary": { "kind": "vertex", "age_id": 123, "display_name": "星街彗星" },
  "start": null,
  "end": null,
  "property": {
    "age_property_name": "nickname",
    "label": "暱稱",
    "value": "Suisei"
  }
}
```

規則：

- `display_name` 用當時的 `VertexDisplayNameResolver`；空則存 `未命名（{type_name}）`
- `delete_*_property` 的 `property.value` 為 `null`
- `create_edge`／edge 屬性／`delete_edge`：必須帶 `start`／`end` 快照（刪除邊前從 AGE 讀兩端）
- `create_vertex` 當下可能尚無顯示屬性：若同 revision 稍後才 `create_vertex_property` 寫入顯示名，**允許** create 列顯示名為 fallback；屬性列會另有一筆「新增屬性」。不要求回溯改寫先前 snapshot（保持簡單）

回寫與 snapshot 須在 approve／apply 成功路徑完成；失敗不得留下部分公開 feed 狀態。

### 歷史資料

本功能上線**前**已核准的 action 可能沒有 id 回寫／snapshot。v1：

- 查詢時略過 `activity_snapshot` 為 null 的列
- **不做**一次性回填（除非另開任務）

---

## 權限與可見性

| 對象 | Overview 動態牆 | `/activity` |
|------|-----------------|-------------|
| 未登入 | 可看 | 可看 |
| 登入使用者 | 可看 | 可看 |

不需新權限。不暴露 Revision title、提交者、審核者於 v1 列上。

---

## 實作約定

### 技術選型

| 區塊 | 技術 |
|------|------|
| Overview／Activity 頁 | Blade + Bootstrap（對齊現有 `overview`） |
| 列 UI | Blade partial，例如 `resources/views/activity/partials/item.blade.php` |
| 查詢 | `ActivityFeedService`；`HomeController@overview` 與 `ActivityController@index` 共用 |
| Icon | Font Awesome 7（`layouts.app` 已引入） |
| Apply 擴充 | `RevisionApplyService`：回寫 id + 寫 `activity_snapshot` |

### 控制器

- `HomeController@overview`：既有 vertex 列表 + 注入 `$activityItems`（最多 10）
- 新增 `ActivityController@index`：分頁列表

### 顯示名稱

寫 snapshot 時複用 `VertexDisplayNameResolver`。Feed 渲染以 snapshot 為準；連結是否有效可再對 AGE 做存在檢查（可選）。

---

## 與現有功能的關係

| 功能 | 關係 |
|------|------|
| `/overview` 類型瀏覽 | 並存於動態牆下方；本規格不改其查詢 |
| Vertex show Edge 區塊 | 詳情仍在該頁；動態牆只給變更摘要 |
| Revision 審核 | 核准＝活動列資料來源；需擴充 apply 回寫 id + snapshot |
| Topic | 策展表格；動態牆是全域時間序，兩者互補 |
| `#15` 點子 | 本功能為「讓 user 知道哪些變更」的公開切片 |

---

## 測試案例

建議類別：`tests/Feature/Activity/ActivityFeedTest.php`（或同等），並擴充既有 approve／apply 測試。

### A. Apply 回寫 AGE ID 與 snapshot

| ID | 案例 | 預期 |
|----|------|------|
| A01 | 核准 `create_vertex` | `target_age_id` 有值；`activity_snapshot` 含 type／primary |
| A02 | 核准 `create_edge`（兩端 age id） | edge／start／end id 正確；snapshot 含兩端 display_name |
| A03 | `create_vertex` + `create_edge`（ref_order） | edge 端點 age id 回寫；snapshot 可解析兩端 |
| A04 | 核准 `delete_vertex` | 刪除前寫入 snapshot display_name；AGE 中頂點已不存在 |
| A05 | 核准 `delete_edge` | snapshot 含起迄 display_name 與 EdgeType name |
| A06 | 核准 `update_vertex_property` | snapshot 含 property label 與 value；必要時回寫 `target_age_id` |
| A07 | 核准 `delete_edge_property` | snapshot 含 property label；value 為 null |
| A08 | 核准失敗／驗證失敗 | 不出現於動態牆 |

### B. Overview 動態牆

| ID | 案例 | 預期 |
|----|------|------|
| B01 | 無已核准 action（或皆無 snapshot） | 空狀態；無「查看更多」 |
| B02 | 剛好 3 筆 | 顯示 3 列；有「查看更多」 |
| B03 | 超過 10 筆 | Overview 只顯示最新 10；「查看更多」連到 `/activity` |
| B04 | 訪客可開啟 `/overview` | 200；可見活動區塊 |
| B05 | 新增頂點列 | icon／主文案／`新增 · {type} · 時間` |
| B06 | 新增邊列 | `A → B`／`新增 · {edge type} · 時間` |
| B07 | 修改屬性列 | 次行含 `修改屬性` 與屬性標籤、值 |
| B08 | 刪除頂點列 | `刪除`；主文案無連結或標「已刪除」 |
| B09 | 同 revision 多 action | 依 `order` 排列於同一核准時間下 |

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
| D01 | 歷史核准、無 `activity_snapshot` | 不出現在 feed |
| D02 | 新增後又被另一 revision 刪除 | 新增列可降級無連結；刪除列依自己的 snapshot |
| D03 | create 時尚無顯示名 | snapshot fallback `未命名（{type}）` |
| D04 | 屬性 value 很長 | 截斷顯示 |
| D05 | Overview 與 `/activity` 前 10 筆一致 | activity 第 1 頁前 10 = overview |

---

## 驗收標準

- Overview 上方有「最近變更」；最多 10 列；「查看更多」→ `/activity`
- `/activity` 公開、每頁 20、同一列格式
- icon 區分頂點／邊；動作文案區分新增／修改／刪除（含屬性）
- 十種 revision action 核准後皆可出現在 feed
- apply 回寫必要 AGE id，並寫入 `activity_snapshot`（刪除前完成名稱解析）
- 無 snapshot 的歷史列被略過；已刪除實體主文案可無連結

---

## 決策紀錄

| 決策 | 選擇 |
|------|------|
| 放置位置 | Overview 動態牆（既有類型瀏覽上方） |
| 列格式 | 統一結構；icon 區分頂點／邊；動作文案區分增刪改 |
| 頂點 icon | `fa-circle-nodes` |
| 邊 icon | `fa-arrow-right-arrow-left` |
| 納入 action | 全部 10 種 graph action（含編輯屬性、刪除） |
| 屬性變更 | v1 **要**顯示（新增／修改／刪除屬性） |
| Overview 筆數 | 10，無分頁 |
| 查看更多 | 連專用頁 `/activity` |
| `/activity` 分頁 | 每頁 20 |
| 篩選 | v1 不做 |
| 時間 | 核准時間（approved review） |
| 顯示資料 | 以 apply 時 `activity_snapshot` 為準 |
| 公開 revision 詳情 | v1 不連 |
| 貢獻者資訊 | v1 不顯示 |
| Navbar | 不新增項目 |
| 歷史回填 | 不做 |
| 既有 Overview 類型區 | 保留並存 |
