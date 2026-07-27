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

待與產品／維護者確認；每題附推薦答案。

### Q1 — `ENUM` 基線語意（進行中）

見對話。

---

## 暫定假設（未鎖定前勿實作）

（確認決策後填入。）

---

## 實作觸點（確認後）

依 `.spec/property-types.md` checklist，另加：

1. Schema：選項儲存欄位或關聯表
2. Form Request：建立／更新 property 時驗證選項
3. `RevisionActionValidator`：`ENUM` 需帶入該 property 的允許值（caster API 可能要擴充）
4. `PropertyValueInput.vue`：select；需能取得 options（不只 type 字串）
5. Topic 過濾（若已實作）：`eq` / `in`? / null 檢查
6. 文件：`.spec/property-types.md`、本文件決策紀錄
