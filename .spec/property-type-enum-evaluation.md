# 資料型別實作：Enum 評估

## 結論

**維持並延續 PHP backed string Enum（`App\Enums\PropertyType`）作為屬性資料型別的唯一來源。**

不採用 PostgreSQL `ENUM`、不改回字串常數／設定檔陣列、不把型別語意下沉到 Apache AGE driver。

Topic 過濾等後續功能應在此 Enum 上擴充行為（方法／對照），並另建 `FilterOperator` 等獨立 Enum，而非另開平行的型別字串表。

---

## 評估範圍

| 項目 | 是否在範圍 |
|------|------------|
| Schema 屬性的 `age_property_type`（INTEGER…TIMESTAMPTZ） | ✅ |
| 驗證／儲存／顯示的型別轉換（`PropertyValueCaster`） | ✅ |
| DB 欄位要用 PHP Enum cast 還是 PG ENUM | ✅ |
| Topic 過濾的「型別 → operator」對照該放哪 | ✅（預研） |
| 新增 AGE 原生 temporal 型別／改 driver | ❌（見 `.spec/property-types.md`） |
| 前端 TypeScript union 與後端 Enum 自動同步 | ❌（可日後再評） |

---

## 現況摘要

已落地：

- `app/Enums/PropertyType.php`：7 cases（`INTEGER` / `FLOAT` / `BOOLEAN` / `STRING` / `DATE` / `MONTH_DAY` / `TIMESTAMPTZ`）
- Eloquent cast：`VertexProperty` / `EdgeProperty` 的 `age_property_type`
- 驗證：`Rule::enum(PropertyType::class)`（GraphSchema Form Requests）
- 行為中心：`PropertyValueCaster`（`match ($propertyType)` 窮舉）
- UI：`selectOptions()`；修訂 value 依型別切換（`PropertyValueInput.vue`）
- DB：`string` 欄位存 Enum **value**（非 PostgreSQL ENUM）
- 文件：`.spec/property-types.md`

同專案其他領域也已採同一模式：`RevisionStatus`、`RevisionActionType`、`RevisionReviewAction`。

---

## 方案比較

| 方案 | 優點 | 缺點 | 判決 |
|------|------|------|------|
| **A. PHP backed string Enum（現況）** | 型別安全、`match` 窮舉可被靜態分析、Eloquent / Form Request 原生支援、與現有 Enum 一致 | 新增 case 需改 PHP + caster + 測試；前端需手動對齊 value | **採用** |
| B. PostgreSQL `ENUM` | DB 端拒絕非法值 | 加值要 migration／`ALTER TYPE`；Eloquent 仍常再包一層；與 AGE 無關的 schema 表過度耦合 | 不採用 |
| C. 設定檔 / 陣列常數 | 改設定不必改 class | 無編譯期窮舉、易漏分支、驗證與 UI 易漂移 | 不採用 |
| D. 策略 class 對每個型別 | 大型行為可拆檔 | 目前 7 型、行為集中在 caster 已足夠；過早抽象 | 現階段不採用 |
| E. 字串裸奔（無 Enum） | 最少程式碼 | 已證明會有 `NUMERIC`→`FLOAT` 遷移與大小寫問題；驗證／cast 脆弱 | 已淘汰 |

**推薦答案：A。** 理由是型別集合小且穩定、跨驗證／套用／顯示多處分支，Enum + `match` 的窮舉成本低於維護平行字串表。

---

## 為何不要 DB ENUM

1. `age_property_type` 語意屬於 **CoHistograph schema**，不是 AGE／PostgreSQL 原生 property type。
2. 歷史上已有 `NUMERIC` → `FLOAT` 資料遷移；PHP Enum + string 欄位用 data migration 即可，不必碰 `ALTER TYPE`。
3. Laravel `Rule::enum` + model cast 已能在應用邊界擋非法值；DB ENUM 的額外保證有限。
4. 測試與 SQLite（若使用）對 PG ENUM 支援不一致時成本更高。

---

## Enum 應承載什麼／不應承載什麼

### 應放在 `PropertyType`（或其明確方法）

- Case 與儲存字串（`INTEGER` 等）— **單一真相來源**
- UI select 選項（已有 `selectOptions()`）
- **型別能力查詢**（建議後續補上），例如：
  - `isTemporal()` / `isNumeric()` / `isComparable()`
  - `allowedFilterOperators(): list<FilterOperator>`（Topic 用）
- 與顯示相關的粗分類（若多處重複 `match`）

### 應留在 `PropertyValueCaster`（不要塞進 Enum case 本體）

- 正則、曆日驗證、TIMESTAMPTZ 正規化
- `toStorage` / `fromStorage` / `formatForDisplay`
- Carbon sentinel year 等實作細節

Enum 負責「是什麼／能做什麼」；Caster 負責「怎麼轉」。避免 Enum 長成上帝物件。

### 不應做的事

- 為每個 `PropertyType` case 建獨立 strategy 目錄（除非單一型別邏輯明顯膨脹）
- 在 Enum 內產生 Cypher 或碰 AGE driver
- 同時維護一份 `config/property_types.php` 與 Enum（必然漂移）

---

## Topic 過濾預研（型別 × operator）

`.spec/topic.md` 要求依 `age_property_type` 限制 operator。建議：

1. **新增** `App\Enums\FilterOperator`（backed string：`eq`、`contains`、`gt`…）。
2. 在 `PropertyType` 上提供 `allowedFilterOperators(): array`（或獨立的純函式／小 class 對照表，但仍以 `PropertyType` 為 key）。
3. 驗證 Topic `definition` 時：`Rule::enum(FilterOperator::class)` +「此 operator 是否屬於該 property 的 allowed 集合」。
4. `MONTH_DAY`：Topic 表目前寫「DATE / TIMESTAMPTZ 等」— 實作前應明確：
   - **建議**：`MONTH_DAY` 比照可比較型別給 `eq/gt/gte/lt/lte/between`（字串 `MM-DD` 字典序在零填充下等同月日序），或 v1 先只開放 `eq` / null 檢查。
   - 選定後寫進 Topic 規格，並由 Enum 方法單一實作，避免 controller 手寫 if。

**不建議**再做一個 `string => string[]` 的平行 map 當唯一來源；那會回到方案 C。

---

## 風險與缺口（現況）

| 缺口 | 影響 | 建議 |
|------|------|------|
| `PropertyType` 幾乎只有 case + `selectOptions()`，能力查詢分散在 caster／未來 Topic | Topic 實作時易複製粘貼 operator 表 | 實作 Topic 前補 `allowedFilterOperators()`（或等價） |
| 前端 Vue 以字串 value 分支，無共享 TS union | 新增 case 可能漏 UI | checklist 已含於 `.spec/property-types.md`；可接受 |
| Form Request 寫 `['required', 'string', Rule::enum(...)]` | `string` 多餘但不錯 | 可簡化為 `['required', Rule::enum(...)]`（順手時） |
| DB 無 check constraint | 理論上可寫入非法字串 | 應用層已擋；若要防直接 SQL，可加 check 或保持現狀 |

以上皆非「改掉 Enum」的理由。

---

## 決策紀錄

| 問題 | 決定 |
|------|------|
| 資料型別用什麼實作？ | PHP backed string Enum（`PropertyType`） |
| DB 欄位型態？ | 繼續 `string` 存 Enum value |
| 轉換邏輯放哪？ | `PropertyValueCaster` + `match` 窮舉 |
| 要不要 PG ENUM？ | 不要 |
| Topic operator？ | 另建 `FilterOperator` Enum；允許集合掛在 `PropertyType`（或以其為 key 的單一對照） |
| 改 AGE driver？ | 不要（條件同 `.spec/property-types.md`） |

---

## 成功標準（本評估）

- [x] 明確採納／排除替代方案
- [x] 與現有程式與 `.spec/property-types.md` 一致
- [x] 標出 Topic 實作時的 Enum 擴充方向，避免第二套型別字串

本文件為決策評估，**不含程式碼變更**。實作 Topic 過濾時再依「Touch points」擴充 Enum／測試。
