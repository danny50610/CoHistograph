# 新屬性型別 `ENUM` 評估

> 範圍：在既有 `PropertyType`（INTEGER…TIMESTAMPTZ）之外，**新增一種資料型別 `ENUM`**（值必須落在 schema 定義的選項集合內）。  
> 非範圍：是否用 PHP Enum 實作型別系統（已定案：繼續用 `App\Enums\PropertyType`）。

## 現況約束（評估前提）

- `vertex_properties` / `edge_properties` 目前只有 `age_property_type`（string），**沒有**選項清單欄位。
- `PropertyValueCaster::matchesType($value, PropertyType)` **只看型別、看不到 property 列**；`ENUM` 驗證勢必需要「該 property 的允許值」。
- Revision `value`、AGE 寫入路徑目前都假設**單一純量**（非 array）。
- 多語系 property 慣例上為 `STRING`（見 `.spec/localized-property.md`）。
- UI：`PropertyValueInput.vue` 依 `propertyType` 字串切換；schema 表單用 `PropertyType::selectOptions()`。

## 決策樹（進行中）

每題附推薦答案；已鎖定者標 ✅。

### Q1 — 基線語意：單選 vs 多選 ✅

| 選項 | 含義 |
|------|------|
| A. 單選 | AGE 存單一 string |
| **B. 多選（已選）** | 一個屬性可同時持有多個選項值 |

**決定：B。** v1 以多選為基線（非整數「剛好選一個」的單選型別）。

### Q2 — AGE 多值如何儲存？ ✅

| 選項 | AGE 實際型別 | 例子 |
|------|--------------|------|
| **A. 原生 list（已選）** | agtype list | `['rock', 'jazz']` |
| B. JSON 字串 | agtype string | `'["rock","jazz"]'` |
| C. 逗號分隔 | agtype string | `'rock,jazz'` |

**決定：A。** 實作前先 spike：`set(['v.prop' => ['a','b']])` 經現有 `laravel-apache-age-driver` 寫讀 round-trip；失敗再小改 driver，不退回字串方案。

### Q3 — Schema 上「允許的選項」存在哪？ ✅

| 選項 | 做法 |
|------|------|
| **A. Property JSON 欄位（已選）** | `vertex_properties` / `edge_properties` 加 `enum_options`（json） |
| B. 獨立關聯表 | `property_enum_options` |
| C. 全域共用選項集 | 多 property 共用 |

**決定：A。** 僅當 `age_property_type = ENUM` 時有意義；其他型別為 `null`／忽略。

### Q4 — `enum_options` JSON 形狀？ ✅

| 選項 | 形狀 | AGE list 存什麼 |
|------|------|-----------------|
| A. 純字串陣列 | `["rock", "jazz"]` | 同字串 |
| **B. value + label（已選）** | `[{"value":"rock","label":"搖滾"}, …]` | 只存 `value` |
| C. value + 多語 labels | `value` + `labels:{…}` | 只存 `value` |

**決定：B。** 約束（實作時寫進 Form Request）：
- 至少 1 個 option
- 每個 `value`、`label` 為非空字串
- `value` 在同一 property 內唯一
- AGE／revision 比對只認 `value`；`label` 僅 schema／UI 顯示

### Q5 — Revision `value` 如何編碼多選？ ✅

| 選項 | 做法 |
|------|------|
| A. JSON 陣列塞進既有 text `value` | 一筆 action，`value = '["rock","jazz"]'` |
| **B. 改 DB 結構（已選）** | 調整欄位型別或另開欄位／表 |
| C. 多筆 revision action | 每個選中值一筆 |

**決定：B → 細化為 B1。**

### Q5b — `revision_actions` 具體怎麼改？ ✅

| 選項 | Schema |
|------|--------|
| **B1. `value` → jsonb（已選）** | 單欄；純量＝JSON scalar，ENUM＝JSON array |
| B2. text `value` + jsonb `values` | 雙欄互斥 |
| B3. 子表 | 一列一個選中值 |

**決定：B1。** 實作要點：
- migration：`text` → `jsonb`；既有列以 `to_jsonb(value)`（或等價）轉成 JSON string
- Eloquent：`value` 需能承載 scalar 與 array（自訂 cast 或等價策略）
- `PropertyValueCaster` / validator / apply：接受 `mixed`，不再一律 `(string) $action->value`
- `actions_snapshot` 已是 jsonb，對齊後 ENUM 直接是 array

### Q6 — 空陣列 `[]` 與「刪除屬性」如何區分？ ✅

| 選項 | 含義 |
|------|------|
| **A. 禁止 `[]`（已選）** | create/update 至少 1 個值；清空走 delete |
| B. 允許 `[]` 與 delete 並存 | 空 list ≠ 無 property |
| C. `[]` 自動當 delete | update 隱藏成 REMOVE |

**決定：A。** create/update 的 ENUM `value` 必須為**非空** JSON array；每個元素必須 ∈ 該 property 的**仍可選** options。刪除屬性仍用 `delete_*_property` 且 `value = null`。

### Q7 — 既有圖資料下，能否改／刪 `enum_options`？ ✅

| 選項 | 規則 |
|------|------|
| **A + 停用標記（已選）** | 可加 option、改 label；可將 option **標記為不在使用**；硬刪／更名 `value` 若圖上仍有成員使用則拒絕 |
| B. 全開放 | 允許 orphan |
| C. 有資料整包鎖定 | 過嚴 |
| D. 僅軟刪、無硬刪護欄 | 不足 |

**決定：A + 停用。** `enum_options` 元素：

```json
{"value":"rock","label":"搖滾","active":true}
```

- `active: true`（預設）：可新選
- `active: false`：**未來不能再新增此值**（見 Q7b）
- 硬刪 option 或改 `value` 字串：僅當 AGE 中無人使用該 value（擴充 data checker）

### Q7b — 停用後，圖上「已經選過」的值怎麼辦？ ✅

| 選項 | 含義 |
|------|------|
| **A. 祖父條款（已選）** | 已在圖上的停用 value 可保留；不可新引入 |
| B. 全面禁止停用 value | create/update 皆拒 |
| C. 停用即掃圖清除 | 無 revision 審計，不採用 |

**決定：A。** 驗證規則（update）：
- 令 `incoming` = 修訂提出的 value 集合，`current` = 圖上現有 list 集合
- 允許的元素 = `active` options ∪ (`current` ∩ inactive options)
- `incoming` 必須 ⊆ 允許集合，且 `incoming` 非空
- create：只允許 `active` options

### Q8 — `ENUM` 能否搭配 property `locale`（多語系欄位）？（進行中）

見對話。

---

## 暫定假設（未鎖定前勿實作）

| 項目 | 狀態 |
|------|------|
| 基線語意 | ✅ 多選 |
| AGE 儲存格式 | ✅ agtype list of strings（option `value`） |
| Schema 選項定義 | ✅ property 上 `enum_options` JSON |
| `enum_options` 形狀 | ✅ `[{value, label, active}, …]` |
| Revision `value` 編碼 | ✅ `revision_actions.value` → **jsonb**（ENUM＝array） |
| 空集合 vs 刪除屬性 | ✅ 禁止 `[]`；清空＝delete |
| 選項變更 vs 既有資料 | ✅ 可停用；硬刪需無人使用 |
| 停用後舊值語意 | ✅ 祖父條款（不可新引入） |
| 與 locale／BOOLEAN 關係 | ⏳ |

---

## 實作觸點（確認後）

依 `.spec/property-types.md` checklist，另加：

1. Schema：選項儲存欄位或關聯表
2. Form Request：建立／更新 property 時驗證選項
3. `RevisionActionValidator`：`ENUM` 需帶入該 property 的允許值（caster API 可能要擴充）
4. `PropertyValueInput.vue`：select；需能取得 options（不只 type 字串）
5. Topic 過濾（若已實作）：`eq` / `in`? / null 檢查
6. 文件：`.spec/property-types.md`、本文件決策紀錄
