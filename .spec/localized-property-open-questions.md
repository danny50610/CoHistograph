# 多語系 Property 方案 A — 實作待釐清事項

本文件整理自 [`localized-property.md`](./localized-property.md) 規格審查，列出實作前需決策或補充說明的項目。

---

## 高優先：會直接影響正確行為


### 2. 互斥規則的匹配邏輯不夠精確

規格寫「若已存在任何 `locale != null` 且 `age_property_name` 以 `name_` 開頭的 property，則禁止建立非多語系 `name`」。但 base name 本身可能含底線，會產生誤判或漏判：

| 情境 | 問題 |
|------|------|
| 已有非多語系 `name_legacy` | 建立多語系 base=`name` 時只檢查 `age_property_name = name`，不會擋 `name_legacy`，可能混用 |
| 已有 `name_zh_tw` | 建立非多語系 `name_zh` 時，`name_zh_tw` 不以 `name_zh_` 開頭，可能不會被擋 |
| base=`title` 已有 `title_zh_tw` | 建立非多語系 `title_old` 時，`title_old` 不以 `title_` 開頭，邏輯需再釐清 |

**待決策：** 互斥應以 **base name 精確比對**（`{base}_{locale}` 或 `age_property_name = base`），而非 `starts with "{base}_"`。

---

### 3. 分組邏輯：剝後綴 vs 使用 `locale` 欄位

§7 寫「stripping `_{locale}` 後綴（即末尾的 5 字元 `_xx_xx`）後分組」，但：

- base 若為 `region_en_us`，locale=`zh_tw` → 完整名稱 `region_en_us_zh_tw`，剝 5 字元得 `region_en`，不是 `region_en_us`
- 若存在非多語系欄位 `foo_zh_tw`（`locale=null`），可能被誤分組

**待決策：** 分組應以 `locale` 欄位為主（有 `locale` 則反推 base；`locale=null` 自成一組），或明確定義 suffix strip 演算法與邊界案例。

---

### 4. 分組標題用哪個 `name`？

範例以「姓名」為組標題，但三筆 property 的 `name` 分別是「姓名」「名前」「Name」。未說明標題來源：

- 預設 locale 那筆的 `name`？
- 第一筆？
- 還原後的 base name 字面顯示？

**待決策：** 分組標題的選取規則。

---

## 中優先：Update / 表單行為細節

### 5. 編輯多語系 property 時 UI 行為

Store 有 `base_age_property_name` + 自動拼接，Update 只說 `locale` 不可變，未說明：

- `age_property_name` / base name 能否修改？（現有 Controller 有 TODO：`age_property_name cannot change when exists`）
- 編輯表單顯示完整 `name_zh_tw`，還是拆成 base + 唯讀 locale？
- 若 base name 可改，AGE 既有資料如何處理？

**待決策：** Update 時 `age_property_name` 與 base name 的可編輯性。

---

### 6. `locale` 空值語意

驗證用 `required_with:locale` / `required_without:locale`，但未定義表單送出的「非多語系」是 `null`、空字串，還是不送欄位。空字串 `""` 可能讓 `Rule::in(...)` 或 `required_with` 行為與預期不符。

**待決策：** 表單欄位與驗證對「非多語系」的統一表示方式。

---

### 7. 多語系 property 的 `age_property_type` 限制

規格開頭說 localized properties 皆為 `STRING`，但 Form Request 未強制 `locale` 有值時 `age_property_type` 必須為 `STRING`，也未禁止 Update 時改型別。

**待決策：** 是否在驗證層強制 `locale != null` ⇒ `age_property_type = STRING`。

---

### 8. `locale` 與 `age_property_name` 後綴一致性

Controller 會拼接 `{base}_{locale}`，但未要求 DB 層 invariant：`locale=zh_tw` 時 `age_property_name` 必須以 `_zh_tw` 結尾。若日後有 seeder 或直接寫 DB，顯示分組可能錯亂。

**待決策：** 是否新增驗證規則或 model 層檢查，確保 `locale` 與 suffix 一致。

---

### 9. Form Request 重構路徑

修改清單列出 `StoreVertexPropertyRequest` 等，但現況是 `VertexPropertyController` / `EdgePropertyController` 內嵌 `$this->validate()`。

**待決策：** 抽出 Form Request，或維持 inline 驗證？互斥規則放在 Request、Controller 還是獨立 Rule class？

---

## 中優先：覆蓋範圍缺口

### 10. 僅規劃 `vertex/show`，其他顯示頁未涵蓋

| 頁面 | 現況 |
|------|------|
| `graph-schema/vertex-type/show.blade.php` | 平面列出各 locale property |
| `GraphSchema/Visualization.vue` | 顯示 `age_property_name`，未分組 |
| `revisions/show` | action 可能只顯示 `name_zh_tw` |
| Edge 相關資料顯示 | 未在修改清單中 |

**待決策：** 哪些 UI 要分組顯示、哪些維持平面列表。

---

### 11. 部分 locale 覆蓋

未要求同一 base 必須定義全部 config locale。可只建 `name_zh_tw` 而不建 `name_en_us`。§7 分組顯示時：

- 缺 locale 是否隱藏？
- 是否顯示「尚未設定」？

**待決策：** 不完整 locale 集合的顯示行為。

---

### 12. Vue Revision 表單的 locale 資料來源

`RevisionController` 傳 `VertexType::with('properties')`，加上 `locale` 欄位後前端可直接讀取。但顯示格式（如 `[zh_tw]` badge、locale 顯示名稱「繁體中文」）需從 config 還是 API 傳 `locales`？

**待決策：** 前端 locale 顯示名稱的資料來源與傳遞方式。

---

## 低優先：邊界與實作細節

### 13. `base_age_property_name` 與 `AgePropertyName` 規則

現有 `AgePropertyName` 規則允許 `[a-z0-9_]*`、最長 64 字元。未說明：

- base + `_` + locale 拼接後總長是否仍須 ≤ 64？
- base 可否為 `name_zh_tw`（拼接後 `name_zh_tw_zh_tw`）？
- base 可否含類似 locale 的後綴（如 `foo_en_us`）？

**待決策：** base name 與完整 `age_property_name` 的長度與格式限制。

---

### 14. `name`（顯示名稱）欄位唯一性

同一 VertexType 下 `name` 有 unique 約束。多語系三筆的 `name` 本來就不同（姓名/名前/Name），但未說明是否允許不同 locale 使用相同顯示名稱。

**待決策：** 多語系 property 的 `name` 欄位是否允許重複。

---

### 15. 範例與 config 用語不一致

§7 範例寫「English (US)」，config 為 `'en_us' => 'English'`。實作應以 config 為準，建議統一範例用語。

---

### 16. locale 格式限制

強制 `xx_xx`（兩個小寫字母組）排除 `zh-Hant`、`en` 等標準 BCP 47 格式。若為刻意設計可保留，但應註明未來擴充（如 `zh_hant`）是否需改 migration / regex。

---

### 17. 測試範圍

修改清單僅列 `VertexPropertyTest`、`EdgePropertyTest`。互斥規則、分組顯示、`show_property_name` 等關鍵行為沒有對應測試規劃。

**待決策：** 需補充哪些 feature / unit test。

---

### 18. 既有資料 / Seeder

`SimulateGraphDataSeeder` 等未提及是否需示範多語系 property。不影響 production，但影響開發與 demo 體驗。

---

## 建議在規格補充的決策清單

實作前建議在 [`localized-property.md`](./localized-property.md) 中定案：

1. ~~**`show_property_name` 策略**~~ → 見 [`show-property-name.md`](./show-property-name.md)
2. **互斥規則精確演算法**（建議用 base name 精確比對，不用 `starts with`）
3. **分組演算法**（建議以 `locale` 欄位為主，不用 blind suffix strip）
4. **分組標題來源**
5. **Update 時 `age_property_name` / base name 是否可改**
6. **`locale` 空值的表單與驗證語意**
7. **哪些 UI 要分組、哪些維持平面列表**
8. **前端 locale 顯示名稱的資料來源**

---

## 參考：現有程式碼相關位置

- `app/Http/Controllers/GraphSchema/VertexPropertyController.php` — 內嵌驗證、`age_property_name` 更新 TODO
- `app/Rules/GraphSchema/AgePropertyName.php` — 命名規則 `[a-z0-9_]*`、最長 64 字元
- `resources/views/graph/vertex/show.blade.php` — 使用 `show_property_name` 作為標題
- `app/Http/Controllers/GraphSchema/VertexTypeController.php` — `show_property_name` 驗證為現有 property 的 `age_property_name` 之一
