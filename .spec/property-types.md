# Property Types

## 現況

`PropertyType` enum（`app/Enums/PropertyType.php`）定義屬性型別。目前共 **7** 種：

| Enum case | 儲存值 | AGE 實際型別 | PHP 讀出型別 |
|-----------|--------|--------------|--------------|
| `Integer` | `INTEGER` | agtype integer | `int` |
| `Float` | `FLOAT` | agtype float | `float` |
| `Boolean` | `BOOLEAN` | agtype boolean | `bool` |
| `String` | `STRING` | agtype string | `string` |
| `Date` | `DATE` | **agtype string**（`Y-m-d`） | `Carbon\CarbonImmutable` |
| `MonthDay` | `MONTH_DAY` | **agtype string**（`m-d`，如 `07-22`） | `Carbon\CarbonImmutable`（sentinel year `2000`） |
| `Timestamptz` | `TIMESTAMPTZ` | **agtype string**（ISO-8601 + offset） | `Carbon\CarbonImmutable` |

`DATE` 對應完整日期；`MONTH_DAY` 只存月日（週年／紀念日等，不綁年份）；`TIMESTAMPTZ` 為帶時區的時間點。

## 字串儲存／讀出轉換

中心邏輯在 `App\Support\PropertyValueCaster`：

- `matchesType()`：Revision 驗證（`RevisionActionValidator`）
- `toStorage()`：寫入 AGE 前轉換（`RevisionApplyService`）
  - `DATE` / `MONTH_DAY` / `TIMESTAMPTZ` **維持字串**（不把 Carbon 丟進 Cypher SET）
  - `TIMESTAMPTZ` 會正規化成 ISO-8601（例如 `2024-07-22T14:30:00+08:00`）
- `fromStorage()`：從 AGE 讀出後轉換（`LocalizedPropertyGrouper`）
  - `DATE` → `CarbonImmutable`（UTC midnight）
  - `MONTH_DAY` → `CarbonImmutable`（year 固定 `2000`，以便支援 `02-29`）
  - `TIMESTAMPTZ` → `CarbonImmutable`（保留原始 offset）
- `formatForDisplay()`：Blade 顯示用

輸入格式：

- `DATE`：嚴格 `YYYY-MM-DD`，且須為有效曆日
- `MONTH_DAY`：嚴格 `MM-DD`（零填充），以 leap year `2000` 驗證曆日（允許 `02-29`）
- `TIMESTAMPTZ`：必須帶時區（`Z` 或 `±HH:MM` / `±HHMM`），禁止無時區的 naive datetime

修訂編輯 UI（`resources/js/Pages/Revisions/Partials/PropertyValueInput.vue`）依型別切換輸入元件，仍寫入上述字串格式。

`revision_actions.value` 仍為 text；型別轉換發生在驗證／套用／讀取顯示時，不靠 Eloquent cast。

## Apache AGE driver 是否需要修改？

**目前不需要修改 `danny50610/laravel-apache-age-driver`。**

原因：

1. AGE 的 agtype **沒有**穩定、可在 driver 端對應到 PHP `DateTime` 的原生 datetime property 型別；社群作法本來就是用字串或 epoch。
2. Driver 的 `SetClause` / `WithProperties` 已把 PHP `string` 當 Cypher 字串參數寫入。
3. 讀回時 vertex/edge properties 經 `json_decode`（或 agtype parser）得到 PHP string；應用層依 schema 的 `PropertyType` 再轉 Carbon。
4. 型別語意屬於 **CoHistograph schema**（`vertex_properties.age_property_type`），不是 AGE 內建 schema。

因此「DB 存字串、讀出轉 PHP 物件」應實作在 **本專案** 的 `PropertyValueCaster`，不要塞進 driver。

### 給其他 AI：什麼情況才該改 driver？

只有在下列需求成立時，才考慮動 `laravel-apache-age-driver`：

| 需求 | 可能改動點 |
|------|------------|
| 希望 SET 時自動把 `DateTimeInterface` 編成字串 | `Query/SetClause.php`、`Query/Concerns/WithProperties.php`：對 `DateTimeInterface` 做 `format()` 後再當 string binding |
| 希望 WHERE 比較時用 datetime 語意 | `Query/WherePartClause.php` 與參數綁定；並確認 AGE 端比較字串 ISO-8601 是否足夠 |
| 希望 agtype annotation（若未來 AGE 支援）還原成 Carbon | `Parser/AgtypeBaseListenerImpl.php` 的 `exitTypeAnnotation` / string value 路徑 |
| 希望 CREATE/SET 產生 AGE 原生 temporal（若版本支援） | 查 AGE 文件後改 Cypher 產生方式；**不要**在應用層假設 driver 會回 Carbon |

若只是新增屬性型別（如 DATE / MONTH_DAY / TIMESTAMPTZ）：

1. 擴充 `App\Enums\PropertyType`
2. 更新 `PropertyValueCaster` 的 `match` 分支
3. 補 unit / feature tests
4. 更新本文件與 `.spec/revision.md`

**不要**為了應用層型別去 fork driver，除非上面表格的需求出現。

### Driver 關鍵檔案索引（參考用）

套件路徑：`vendor/danny50610/laravel-apache-age-driver/`

- `src/Query/SetClause.php` — `SET prop = $vN`
- `src/Query/Concerns/WithProperties.php` — CREATE map properties
- `src/Query/AfterQuery.php` — 把 `...::vertex` / `::edge` 轉成 Model（properties 多為 `json_decode` 陣列）
- `src/Parser/AgtypeBaseListenerImpl.php` — path 等情境的 agtype 解析（string/int/float/bool/null）

## Touch points checklist（新增型別時）

1. `app/Enums/PropertyType.php`
2. `app/Support/PropertyValueCaster.php`
3. `RevisionActionValidator` / `RevisionApplyService`（已委派給 caster）
4. `LocalizedPropertyGrouper` + `resources/views/components/localized-property-groups.blade.php`
5. Schema 表單 select（`PropertyType::selectOptions()` 自動包含）
6. `.spec/revision.md`、本文件
7. tests：`PropertyValueCasterTest`、相關 Feature 驗證
