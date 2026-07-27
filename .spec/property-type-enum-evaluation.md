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

### Q5 — Revision `value` 如何編碼多選？ ✅（方向）／細化中

| 選項 | 做法 |
|------|------|
| A. JSON 陣列塞進既有 text `value` | 一筆 action，`value = '["rock","jazz"]'` |
| **B. 改 DB 結構（已選）** | 調整欄位型別或另開欄位／表 |
| C. 多筆 revision action | 每個選中值一筆 |

**決定：B（方向）。** 需再鎖定具體 schema 改法（Q5b）。

### Q5b — `revision_actions` 具體怎麼改？（進行中）

見對話。現況：`value` 為 `text nullable`（migration `2026_04_12_130031`）。

---

## 暫定假設（未鎖定前勿實作）

| 項目 | 狀態 |
|------|------|
| 基線語意 | ✅ 多選 |
| AGE 儲存格式 | ✅ agtype list of strings（option `value`） |
| Schema 選項定義 | ✅ property 上 `enum_options` JSON |
| `enum_options` 形狀 | ✅ `[{value, label}, …]` |
| Revision `value` 編碼 | ⏳ 改 DB（細化中） |
| 空集合 vs 刪除屬性 | ⏳ |
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
