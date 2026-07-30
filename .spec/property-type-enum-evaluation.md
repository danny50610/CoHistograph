# 新屬性型別 `ENUM` 評估

> 範圍：在既有 `PropertyType`（INTEGER…TIMESTAMPTZ）之外，**新增一種資料型別 `ENUM`**（值必須落在 schema 定義的選項集合內）。  
> 非範圍：是否用 PHP Enum 實作型別系統（已定案：繼續用 `App\Enums\PropertyType`）。

**狀態：Q1–Q21 已鎖定（G1–G2 完成）；進行 G3（Q22）。** 實作前仍須完成 AGE list round-trip spike。

---

## 結論（v1）

新增 `PropertyType::Enum = 'ENUM'`：

| 層 | 儲存 |
|----|------|
| Schema | `vertex_properties` / `edge_properties.enum_options`（json）：`[{value, label, active}, …]` |
| Revision | `revision_actions.value`：**text → jsonb**；純量依型別原生 scalar（B1）；ENUM 為非空 string array |
| AGE | agtype **list of strings**（只存 option `value`） |

語意摘要：多選集合、拒重複、依 options 定義序正規化；禁止 `[]`（清空＝delete）；可 `active:false` 停用（祖父條款）；不可設 `locale`；與 `BOOLEAN` 並存；Topic 用成員類 operator。

---

## 現況約束（評估前提）

- `vertex_properties` / `edge_properties` 目前只有 `age_property_type`（string），**沒有**選項清單欄位。
- `PropertyValueCaster::matchesType($value, PropertyType)` **只看型別、看不到 property 列**；`ENUM` 驗證勢必需要「該 property 的允許值／active 狀態／圖上 current」。
- Revision `value`、AGE 寫入路徑目前都假設**單一純量**（非 array）。
- 多語系 property 慣例上為 `STRING`（見 `.spec/localized-property.md`）。
- UI：`PropertyValueInput.vue` 依 `propertyType` 字串切換；schema 表單用 `PropertyType::selectOptions()`。

---

## 決策樹（已鎖定）

### Q1 — 單選 vs 多選 ✅ → **B 多選**

### Q2 — AGE 儲存 ✅ → **A agtype list**

實作前 spike：`set(['v.prop' => ['a','b']])` round-trip；失敗再小改 driver。

### Q3 — 選項存在哪 ✅ → **A property 上 `enum_options` JSON**

### Q4 — options 形狀 ✅ → **B `{value, label}`**（後由 Q7 加上 `active`）

約束：至少 1 個 option；`value`／`label` 非空；`value` 唯一。

### Q5 / Q5b — revision 編碼 ✅ → **B1 `value` → jsonb**

純量＝JSON scalar；ENUM＝JSON array。既有 text 以 `to_jsonb(value)` 遷移。

### Q6 — 空陣列 ✅ → **A 禁止 `[]`**；清空＝`delete_*_property`

### Q7 — options 變更 ✅ → **A + 停用 `active`**

- 可新增 option、改 label、設 `active:false`
- 硬刪／更名 `value`：圖上無人使用該 value 才允許

### Q7b — 停用後舊值 ✅ → **A 祖父條款**

- create：僅 `active` options
- update：允許 `active` ∪（current ∩ inactive）；不可**新引入** inactive

### Q8 — locale ✅ → **A ENUM 不可設 locale**

### Q9 — list 語意 ✅ → **A 集合**：拒重複；依 `enum_options` 定義序正規化

### Q10 — BOOLEAN ✅ → **A 並存**，不取代

### Q11 — Topic operators ✅ → **A 成員導向**

| Operator | 含義 | filter `value` |
|----------|------|----------------|
| `contains` | list 含該 value | 單一 option value |
| `contains_any` | 含任一 | value 陣列 |
| `contains_all` | 含全部 | value 陣列 |
| `is_null` | property 不存在 | 無 |
| `is_not_null` | property 存在 | 無 |

不做整包集合 `eq`（v1）。因 Q6 無空 list，`is_null` ≡ 無此屬性。

---

## 鎖定摘要表

| 項目 | 決定 |
|------|------|
| 基線語意 | ✅ 多選 |
| AGE 儲存 | ✅ agtype list of option `value` |
| Schema 選項 | ✅ `enum_options` JSON on property |
| options 形狀 | ✅ `[{value, label, active}, …]` |
| Revision `value` | ✅ **jsonb**（ENUM＝非空 array） |
| 空集合 | ✅ 禁止 `[]`；清空＝delete |
| 選項生命週期 | ✅ 可停用；硬刪需無人使用 |
| 停用舊值 | ✅ 祖父條款 |
| locale | ✅ 不可 |
| list 去重／序 | ✅ 拒重複；定義序正規化 |
| BOOLEAN | ✅ 並存 |
| Topic | ✅ `contains` / `contains_any` / `contains_all` / null 檢查 |

---

## Schema UI：Vertex / Edge Property 介面（進行中）

Vertex 與 Edge **同一套 partial**（與現有 `property-locale-fields` 對稱），差異只在 route／model。

### 既有畫面

- 新增／編輯：`graph-schema/{vertex|edge}-property/create-or-edit.blade.php`
- 目前欄位：名稱、描述、locale／age_property_name、`age_property_type` select
- 詳情：`show.blade.php` 僅顯示 type 字串，尚無 options

### 使用流程（依已鎖定決策推導）

```text
選 Property Type
  ├─ 非 ENUM → 隱藏選項區；locale 維持現況
  └─ ENUM
        ├─ 語言版本強制「非多語系」（禁用 locale select；Q8）
        └─ 顯示「選項 enum_options」編輯區
              每列：value | label | 使用中(active) | 操作
```

| 操作 | 行為 |
|------|------|
| 新增 option | 加一列；`active` 預設 true；`value` 在儲存前可改 |
| 改 label | 永遠可 |
| 停用 | `active=false`；之後修訂不可**新選**（祖父條款） |
| 重新啟用 | `active=true` |
| 刪除列（硬刪） | 僅當 AGE 無人使用該 `value`；否則按鈕 disabled + 說明 |
| 已寫入圖的 `value` | 編輯時 **value 輸入框鎖定**（改 value＝更名，需走硬刪護欄；避免誤改 key） |
| 儲存 | `ENUM` ⇒ `enum_options` 必填且 ≥1；非 `ENUM` ⇒ `enum_options` 必須 null |
| 詳情頁 | 表格列出 value／label／狀態（使用中／已停用） |
| 列表 badge | 既有 `ENUM` type badge；可選加「N 個選項」 |

修訂頁（`PropertyValueInput`）不在本節；該處為 **multi-select**，選項來自 schema 的 `enum_options`（active 可選；inactive 僅若已在圖上才顯示為已選且不可新勾）。

### Q12 — 選項編輯器 UI 形態？ ✅

| 選項 | 形態 |
|------|------|
| **A. 動態列編輯器（已選）** | 新增列；每列 value／label／active／刪除；上移下移＝定義序 |
| B. JSON textarea | 手寫 JSON |
| C. 獨立 Options 子頁 | 另頁維護 |

**決定：A。**  
- Vertex／Edge 共用 `graph-schema/partials/property-enum-options-fields.blade.php`
- 表單欄位名：`enum_options[i][value]`、`enum_options[i][label]`、`enum_options[i][active]`
- 列順序＝`enum_options` 陣列順序（寫入 AGE 正規化依此序）
- `age_property_type` change 時用 JS 顯示／隱藏此區，並在選 ENUM 時把 locale 設為空且 disabled

### Q13 — 建立後能否更改 `age_property_type`？ ✅

| 選項 | 規則 |
|------|------|
| **A. 有資料就鎖 type（已選）** | 有 AGE 資料 ⇒ type 唯讀；無資料可改，改離 ENUM 則 `enum_options = null` |
| B. 永遠可改 | 易不一致 |
| C. 建立後永不改 type | 過嚴 |

**決定：A。** 與現有「有資料鎖 age_property_name」同一 checker。ENUM **內部** options 增刪停用仍走 Q7，不受 type 鎖影響。

### Schema UI 鎖定摘要

| 項目 | 決定 |
|------|------|
| 選項編輯 | ✅ 動態列；共用 partial |
| locale | ✅ 選 ENUM 時強制非多語系 |
| type 變更 | ✅ 有圖資料則鎖 type |
| 詳情 | ✅ 列出 value／label／active |
| 修訂填值 | 另頁 multi-select（非本節表單）；待開題時再定 |

**Schema UI 決策已收斂（使用者確認）。**

---

## 修訂 UI：PropertyValueInput multi-select（進行中）

現況：
- `PropertyValueInput.vue` 依 `propertyType` 切換；`modelValue` 為 **string｜number｜null**，emit 字串
- `VertexPropertyActionForm` / `EdgePropertyActionForm` 只傳 `property-type`，**尚未**傳 `enum_options`
- 修訂 payload 的 `value` 在 ENUM 落地後為 **jsonb array**（Q5b）

推導必做（不另開題）：
- props 增加 `enumOptions: {value, label, active}[]`
- `modelValue` 對 ENUM 為 `string[] | null`（父層 action.value 對齊）
- 父層從 `selectedProperty.enum_options` 傳入
- 切換屬性時清空 value（既有 `onPropertyChange`）

### Q14 — 多選控件形態？ ✅

| 選項 | 形態 |
|------|------|
| **A. Checkbox 列表（已選）** | 依定義序；顯示 label、送出 value |
| B. Native `<select multiple>` | UX 差 |
| C. Tag／chip 挑選器 | v1 過重 |

**決定：A。** `PropertyValueInput` 在 `propertyType === 'ENUM'` 時渲染 checkbox 列表；emit `string[]`（非空）；父層寫入 action.`value`。

### Q15 — 停用選項顯示／復原？ ✅

| 選項 | 含義 |
|------|------|
| A′. 僅顯示 active ∪ eligibleInactive | 列表較乾淨 |
| **B′. 全列 + eligible 可復原（已選）** | 所有 options 都顯示；僅 eligible inactive 可來回勾 |
| 裸 B | 取消後 disabled → 無法復原（否決） |
| C. 預載 AGE + A′／B′ | v1 不做 |

**決定：B′。**

- 開啟此 ENUM 控件時，令 `eligibleInactive` = 當時 `value` 中 ∩ inactive options（create／空值起步為 `[]`）
- 整段編輯期間 `eligibleInactive` **固定不縮水**
- 渲染：全部 `enum_options` 依定義序；`active` 或 ∈ `eligibleInactive` → 可勾選；其餘 inactive → disabled，標示「已停用」
- 可取消後再勾回 eligible 項（復原 OK）；不可新引入非 eligible 的停用值
- 伺服端仍以圖上 `current` 做祖父驗證（UI eligible 不能取代伺服端）

### Q16 — 摘要列如何顯示 ENUM `value`？ ✅

| 選項 | 例子 |
|------|------|
| **A. label + 頓號（已選）** | `= 搖滾、爵士` |
| B. value + 逗號 | `= rock, jazz` |
| C. JSON | `= ["rock","jazz"]` |

**決定：A。** `Edit.vue`（及後台審核摘要若同樣拼接）將 array 經 options 轉 label，依定義序以「、」連接；未知 value 顯示原字串。

### 修訂 UI 鎖定摘要

| 項目 | 決定 |
|------|------|
| 控件 | ✅ checkbox 列表 |
| 停用項 | ✅ B′ 全列 + eligibleInactive 可復原 |
| 摘要 | ✅ labels 以「、」連接 |
| modelValue | ✅ ENUM 為 `string[]`；props 帶 `enumOptions` |
| 審核差異 | ⏳ 要顯示多了／少了哪些 enum（進行中） |

### Q17 — 審核頁 ENUM「變更前」從哪來？ ✅

| 選項 | 做法 |
|------|------|
| **A. 審核頁即時讀 AGE（已選）** | 查圖上現有 list，與 action.value 做差集 |
| B. 快照 `previous_value` | 作者編輯當下基準 |
| C. 不顯示增減 | 否決 |

**決定：A。**  
- create：before＝∅ → 全部為「新增」  
- delete：after＝∅ → 全部為「移除」（before 從 AGE 讀）  
- update：added = after − before，removed = before − after（集合差；順序無關）  
- 讀取失敗／target 為同修訂新建 ref：before 視為 ∅ 或標「尚無圖上值」（實作時對 ref 目標明確處理）

### Q18 — 增減 diff 怎麼呈現？ ✅

| 選項 | 呈現 |
|------|------|
| **A. 現有／新增／移除三行（已選）** | 現有＋新增（綠）＋移除（紅） |
| B. 只強調增減 | 較短 |
| C. 前後完整箭頭＋增減 | 最佔版面 |

**決定：A。** `action-card`（審核唯讀，必要時編輯頁亦可共用 presenter）對 ENUM：

- **現有：** before labels（無則「—」或「（無）」）
- **新增：** added labels（無則省略列或「—」）
- **移除：** removed labels（無則省略列或「—」）

label 轉換與「、」連接同 Q16。create 可將「現有」固定為無；delete 無 action.value 時「新增」為無、移除＝現有全部。

### 修訂 UI 鎖定摘要

| 項目 | 決定 |
|------|------|
| 控件 | ✅ checkbox 列表 |
| 停用項 | ✅ B′ 全列 + eligibleInactive 可復原 |
| 摘要（編輯列表） | ✅ labels「、」 |
| modelValue | ✅ `string[]` + `enumOptions` |
| 審核舊值 | ✅ 即時讀 AGE |
| 審核 diff | ✅ 現有／新增／移除三行 |

---

## 尚未討論的缺口（盤點）

依影響實作／一致性排列。標 **高** 建議收斂前進決策；**中／低** 可實作時定或延後。

### 高（會卡住實作或造成語意分歧）

| # | 主題 | 為何還沒定 |
|---|------|------------|
| G1 | **`value`→jsonb 後，既有純量怎麼存** | ✅ **B1**：原生 scalar；遷移盡力轉；讀取兼容 string｜native |
| G2 | **圖資料顯示（Vertex／Edge show）** | ✅ **A**：labels「、」；未知 value → raw |
| G3 | **作者修訂詳情／編輯頁是否也顯示三行 diff** | ✅ **A**：與審核頁相同（共用 presenter） |
| G4 | **option `value` 字元規則** | ✅ **A**：`^[a-z0-9_]+$`，1–64；不查保留字 |
| G5 | **可否把所有 option 都停用（active 全 false）** | ✅ **B**：允許；失敗時明確說明「無啟用選項／已停用不可新選」 |

### 中（有明確預設可推，但未明示鎖定）

| # | 主題 | 暫定可推方向（未鎖定） |
|---|------|------------------------|
| G6 | label 是否允許重複 | ✅ **B**：同一 property 內 label 唯一（trim 後精確比對） |
| G7 | value 大小寫是否敏感 | ✅ **A**：嚴格小寫，不自動轉換 |
| G8 | 圖上出現不在 `enum_options` 的 orphan value | ✅ **A**：當祖父（可留／可拿，不可新引入） |
| G9 | Schema Visualization／列表是否展示 options | 僅 type badge vs 展開 options |
| G10 | list 成員「是否被使用」AGE 查詢語意 | spike：`X IN prop`／UNWIND；失敗則硬刪護欄策略 |
| G11 | 同修訂 `create_vertex` ref 目標的審核 diff | Q17 已提「視為 ∅」；需否在 UI 明示「新建對象、無現有值」 |
| G12 | MCP／對外讀取 ENUM | 回 value list 或 value+label |

### 低／實作細節（可不開題）

- Eloquent `value` 自訂 cast（scalar｜array）
- Inertia 確保 `vertexTypes.properties` 帶 `enum_options`
- Factory／Seeder、測試矩陣
- `actions_snapshot` 與 jsonb value 對齊（多半自動）
- 文件：`.spec/property-types.md` 仍寫 7 種／text value（實作時更新）

### 文件內部不一致（應順手修，非產品決策）

- Schema UI 章節標題仍寫「進行中」；修訂 UI 有重複「鎖定摘要」且一處仍寫審核差異 ⏳
- Q15 前文有一處仍描述舊的「不可新勾」簡化句（與 B′ 不符）

### Q19 — 下一題先收哪個高優先缺口？ ✅

**決定：由高到低逐題收（G1→G5…）；本輪先 G1。**

### Q20 — G1：`value`→jsonb 後純量如何存放？ ✅

| 選項 | 含義 |
|------|------|
| A. 一律 JSON string | 含數字／布林 |
| **B1. 原生 scalar + 盡力遷移（已選）** | INTEGER／FLOAT／BOOLEAN 用 number／bool；舊資料能轉就轉 |
| B2. 僅新寫入原生、舊留 string | 永久雙軌寫入策略不同 |
| C. 雙軌並行無遷移策略 | 否決 |

**決定：B1。**

- **新寫入**：依 `PropertyType` 寫入原生 JSON（int／float／bool／string／ENUM array）
- **遷移**：`text`→`jsonb` 後，能依 action 的 property／schema 推得 INTEGER／FLOAT／BOOLEAN 者轉成原生；推不到或非該型別 → 留 JSON string
- **讀取／caster／validator**：兼容歷史 JSON string 與原生 scalar（再交給既有轉型邏輯）
- ENUM 一律 array（元素為 string）

### Q20 — G1：`value`→jsonb 後純量如何存放？ ✅

| 選項 | 含義 |
|------|------|
| A. 一律 JSON string | 含數字／布林 |
| **B1. 原生 scalar + 盡力遷移（已選）** | INTEGER／FLOAT／BOOLEAN 用 number／bool；舊資料能轉就轉 |
| B2. 僅新寫入原生、舊留 string | 永久雙軌寫入策略不同 |
| C. 雙軌並行無遷移策略 | 否決 |

**決定：B1。**

- **新寫入**：依 `PropertyType` 寫入原生 JSON（int／float／bool／string／ENUM array）
- **遷移**：`text`→`jsonb` 後，能依 action 的 property／schema 推得 INTEGER／FLOAT／BOOLEAN 者轉成原生；推不到或非該型別 → 留 JSON string
- **讀取／caster／validator**：兼容歷史 JSON string 與原生 scalar（再交給既有轉型邏輯）
- ENUM 一律 array（元素為 string）

### Q21 — G2：圖資料頁 ENUM 怎麼顯示？ ✅

| 選項 | 顯示 |
|------|------|
| **A. labels + 頓號（已選）** | `搖滾、爵士` |
| B. values + 逗號 | `rock, jazz` |
| C. labels 且停用加標記 | `爵士（已停用）` |

**決定：A。** `formatForDisplay`／Vertex·Edge show：依 `enum_options` 轉 label，定義序、「、」連接；未知 value → raw。與 Q16 一致。

### Q21 — G2：圖資料頁 ENUM 怎麼顯示？ ✅

| 選項 | 顯示 |
|------|------|
| **A. labels + 頓號（已選）** | `搖滾、爵士` |
| B. values + 逗號 | `rock, jazz` |
| C. labels 且停用加標記 | `爵士（已停用）` |

**決定：A。** `formatForDisplay`／Vertex·Edge show：依 `enum_options` 轉 label，定義序、「、」連接；未知 value → raw。與 Q16 一致。

### Q22 — G3：作者修訂頁是否也顯示三行 diff？ ✅

| 選項 | 含義 |
|------|------|
| **A. 與審核頁相同（已選）** | edit／show action-card 皆現有／新增／移除 |
| B. 僅審核頁三行 | 作者只看摘要 |
| C. 唯讀詳情才三行 | 折衷 |

**決定：A。** 共用 presenter；舊值即時讀 AGE（Q17）；同一頁可批次查圖。編輯列表摘要列（`Edit.vue` 一行 title）仍可用 Q16 labels；**卡片內文／詳情**用三行 diff。

### Q22 — G3：作者修訂頁是否也顯示三行 diff？ ✅

| 選項 | 含義 |
|------|------|
| **A. 與審核頁相同（已選）** | edit／show action-card 皆現有／新增／移除 |
| B. 僅審核頁三行 | 作者只看摘要 |
| C. 唯讀詳情才三行 | 折衷 |

**決定：A。** 共用 presenter；舊值即時讀 AGE（Q17）；同一頁可批次查圖。編輯列表摘要列（`Edit.vue` 一行 title）仍可用 Q16 labels；**卡片內文／詳情**用三行 diff。

### Q23 — G4：option `value` 字元規則？ ✅（後經修正）

| 選項 | 規則 |
|------|------|
| A. 同 age_property_name | 原 `^[a-z0-9_]+$` |
| **A′. 另允許 `-`、`+`（已選）** | `^[a-z0-9_+-]+$`，長度 1–64 |
| B. 任意非空白 Unicode | |
| C. 僅另允許 `-` | |

**決定：A′。** 小寫英文、數字、`_`、`-`、`+`；不套 Cypher 保留字檢查。

### Q24 — G5：可否將所有 option 都停用？ ✅

| 選項 | 規則 |
|------|------|
| A. 禁止全停用 | 至少 1 個 active |
| **B. 允許全停用（已選）** | 可 0 個 active；並**清楚解釋**後續失敗原因 |
| C. 全停用當凍結屬性（專用錯誤碼） | 近於 B＋文案 |

**決定：B。** Schema 允許全部 `active:false`。修訂驗證失敗時必須說明原因，例如：

- create／update 若需要**新引入**某值但該值 inactive，或目前 **没有任何 active option 可選**且提出的 list 無法只靠祖父條款滿足 → 錯誤訊息明示：  
  「此 ENUM 屬性目前沒有可選的啟用選項」／「選項「X」已停用，不可新選」  
- 前端 checkbox：全停用且無 eligibleInactive 時，顯示提示文案，避免只剩空白必填失敗

### 高優先缺口 G1–G5 已收斂。

### Q25 — 中優先缺口是否繼續逐題收？ ✅

**決定：A，繼續。**

### Q26 — G6：不同 option 的 `label` 可否重複？ ✅

| 選項 | 規則 |
|------|------|
| A. 允許重複 | |
| **B. 同一 property 內 label 唯一（已選）** | |
| C. 允許重複但 UI 附 value | |

**決定：B。** `label` trim 後在同一 property 內精確唯一（區分大小寫）。

### Q27 — G7：`value` 大小寫是否敏感？ ✅

| 選項 | 規則 |
|------|------|
| **A. 嚴格小寫（已選）** | 含大寫 → 驗證失敗，不自動 `strtolower` |
| B. 寫入前強制小寫 | |
| C. 比對忽略大小寫 | 否決 |

**決定：A。** 與 G4 字元集一致；UI 提示僅限小寫與允許符號。

### Q27 — G7：`value` 大小寫是否敏感？ ✅

| 選項 | 規則 |
|------|------|
| **A. 嚴格小寫（已選）** | 含大寫 → 驗證失敗，不自動 `strtolower` |
| B. 寫入前強制小寫 | |
| C. 比對忽略大小寫 | 否決 |

**決定：A。** 與 G4 字元集一致；UI 提示僅限小寫與允許符號。

### Q28 — G8：圖上 orphan value（不在 enum_options）？ ✅

| 選項 | 含義 |
|------|------|
| **A. 當祖父（已選）** | 可保留／移除，不可新引入其他 orphan |
| B. 強制清掉 | update 必須移除 |
| C. 只讀警告且拒含 orphan 的 update | |

**決定：A。**  
- 顯示：raw value（無 label）；審核／資料頁可標「未知選項」但不阻擋  
- 驗證：`allowed = active values ∪ (current ∩ (inactive ∪ orphan))`；不可把不在 current 的 orphan 新加進去  
- UI checkbox：orphan 出現在「額外列」（不在 enum_options 定義序內、附在末尾），納入 `eligibleInactive` 同類的 session eligible 集合以便復原

### Q29 — G9：Schema Visualization／列表是否展示 options？（進行中）

見對話。

## 實作觸點

1. `App\Enums\PropertyType` 新增 `Enum = 'ENUM'`
2. migration：`vertex_properties` / `edge_properties` 加 `enum_options`（json nullable）
3. migration：`revision_actions.value` text → jsonb + 資料轉換
4. Form Requests：ENUM 時驗證 `enum_options`、禁止 locale；非 ENUM 時 `enum_options` 必須 null
5. 更新 property 時：停用／硬刪護欄（擴充 `AgePropertyDataChecker` 查 list 成員）
6. `PropertyValueCaster` + `RevisionActionValidator` / `RevisionApplyService`：`mixed` value、ENUM 集合驗證與正規化
7. `PropertyValueInput.vue`：ENUM checkbox（B′）；`Edit.vue` 摘要 labels「、」；審核 `action-card` 現有／新增／移除（即時讀 AGE 差集）
8. Schema Blade：共用 `property-enum-options-fields` partial（Vertex／Edge）；type=ENUM 時顯示；與 locale 聯動
9. show／列表：呈現 options 與 active 狀態
10. Topic（實作時）：掛上 Q11 operators
11. 更新 `.spec/property-types.md`、`.spec/revision.md`、本文件
12. **先做** AGE list write/read spike（driver）

## 成功標準（實作 PR）

- [ ] Spike：PHP array ↔ AGE list round-trip 通過（或已修 driver）
- [ ] 可建立 ENUM property（options + active）
- [ ] 修訂 create/update 寫入非空唯一 list；delete 移除屬性
- [ ] 停用 value 不可新選；祖父值可保留
- [ ] 硬刪仍被使用的 value → 拒絕
- [ ] 相關 unit／feature tests；`composer run test` / `phpstan` 通過
