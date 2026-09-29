# 介面多語系（UI i18n）設計方案

## 核心概念

本文件處理**介面字串**的多語系：選單、按鈕、標題、flash、驗證訊息、郵件、DataTables、Vue 元件文案。

這與既有的**圖譜資料多語系**是兩條獨立軸：

| 軸 | 管什麼 | 現況 |
|----|--------|------|
| **UI locale**（本文件） | 介面 chrome、系統訊息 | **未實作**；幾乎全部寫死繁中 |
| **Graph locale**（[`localized-property.md`](./localized-property.md)、[`show-property-name.md`](./show-property-name.md)） | Property 值、Vertex 顯示名稱 | **已實作**；`zh_tw` / `ja_jp` / `en_us` |

兩者最終會對齊（切換介面語言時，Vertex 標題跟著切），但**不可共用同一套 key、也不可把 UI 字串塞進 graph config**。

---

## 現況

### 前端結構

| 層 | 技術 | UI 字串寫法 |
|----|------|-------------|
| 絕大多數頁面 | Blade + Bootstrap 5 | 模板內寫死繁中 |
| 兩頁 SPA 島 | Inertia v3 + Vue 3（`Revisions/Edit`、`GraphSchema/Visualization`） | `.vue` 內寫死繁中 |
| 選單 | `MenuService` + Lavary Menu | PHP 字串寫死繁中 |
| 表格 | Yajra DataTables | `resources/views/components/datatable/js.blade.php` 寫死繁中 |

`package.json` **沒有** `vue-i18n`。`HandleInertiaRequests::share()` 未分享 locale / 翻譯。

### Laravel 翻譯系統幾乎沒用

- 專案**沒有** `lang/` 或 `resources/lang/`
- `__()` / `trans()` 僅出現在忘記／重設密碼的 flash（Laravel 預設英文）
- `config/app.php`：`locale` / `fallback_locale` 預設 `en`（`.env.example` 亦然）
- `User` **沒有**語系欄位；`bootstrap/app.php` **沒有** SetLocale middleware
- `<html lang>` 寫成「`zh_tw` → `zh-Hant-TW`，其餘 → `en`」，與預設 `APP_LOCALE=en` 不一致；實際畫面卻是繁中

### 字串分佈（約）

| 區域 | 含中文檔案 |
|------|------------|
| `resources/views/**/*.blade.php` | 53 / 62 |
| `resources/js/**/*.vue` | 9 / 9 |
| `app/**/*.php`（flash、Rule、Mail、Menu…） | 約 43 / 125 |

### 已存在的不一致

1. 密碼重設成功 flash 為**英文**（`trans()` + `APP_LOCALE=en`），周圍 UI 為繁中
2. 自訂 Rule（如 `AgePropertyName`）中英混雜
3. Graph Schema Form Request 多數走 Laravel 預設英文驗證訊息
4. Vertex 顯示名稱走 `COHISTOGRAPH_DISPLAY_LOCALE`（預設 `zh_tw`），**不**跟瀏覽者走

### 與既有 spec 的關係

[`show-property-name.md`](./show-property-name.md) 明確延後：

> 本階段**不**綁定 Laravel `APP_LOCALE`…未來若要做 per-user locale，再擴充 resolver 參數即可。

`VertexDisplayNameResolver::resolve(..., ?string $locale = null)` 已預留參數；呼叫端目前都沒傳，因此一律用 `config('cohistograph.app.graph.display_locale')`。

---

## 設計目標

1. **單一翻譯來源**：Laravel `lang/`，Blade / PHP / Vue 共用，不維護兩套目錄
2. **可切換**：訪客用 cookie；登入使用者另持久化偏好
3. **可自訂 fallback**：登入使用者可排序內容找不到時要試的語言；站台預設為繁中 → 日文 → 英文
4. **預設與現況一致**：未選擇時仍是繁中，既有 `assertSee('登入')` 類測試不需一次全改
5. **與 graph locale 對齊**：介面語言切換後，Vertex 顯示名稱跟著解析，再依使用者 fallback 取值
6. **不新增套件**：不用 `vue-i18n`、不用 `laravel-lang/*`（驗證譯文可手動抄需要的檔，不引入套件）
7. **分階段落地**：先基建 + chrome，再逐頁抽字串；禁止「一次改完全站」的巨型 PR

---

## 方案比較

### 方案 A：JSON 翻譯、key = 原文（`'登入'`）

`__('登入')`，`lang/en.json` 映射到 `"Login"`。

| 優點 | 缺點 |
|------|------|
| 抽字串快 | 繁中文案一改，key 全斷 |
| 符合 Laravel JSON 翻譯慣例 | Vue / 測試依賴不穩定字面值 |

### 方案 B：PHP 檔 + 穩定英文 key（建議）

`__('nav.login')`，檔案如 `lang/zh_TW/nav.php`。

| 優點 | 缺點 |
|------|------|
| key 不隨文案改動 | 第一次抽取要命名 |
| 可依領域拆檔 | 比 JSON 多一層目錄 |
| Blade / PHP / Vue 同一 key | |

**採用方案 B。**

### 前端 i18n 函式庫

| 做法 | 結論 |
|------|------|
| 加 `vue-i18n` | 拒絕：第二套 catalog |
| Inertia share 翻譯 + 小型 `t()` | **採用**：零新依賴 |

---

## 設計細節

### 1. 支援的 UI locale

| UI locale（Laravel） | 顯示名（自身語言，不翻譯） | `html lang` | 對應 graph locale |
|----------------------|----------------------------|-------------|-------------------|
| `zh_TW` | 繁體中文 | `zh-Hant-TW` | `zh_tw` |
| `en` | English | `en` | `en_us` |
| `ja` | 日本語 | `ja` | `ja_jp` |

- **預設介面語言：** `zh_TW`（現況介面語言）
- **預設 fallback 鏈：** `zh_TW` → `ja` → `en`（繁中 → 日文 → 英文）。與現況 graph config `display_locale_fallback = [zh_tw, en_us, ja_jp]` 不同，以本文件為準
- 變更：`config/app.php` 與 `.env.example` 的 `APP_LOCALE`、`APP_FALLBACK_LOCALE` 改為 `zh_TW`（Laravel 只支援單一 chrome fallback；內容解析另走 §6 鏈）
- UI locale **不是** graph 的 `zh_tw` 小寫格式。對照集中在一處（見 §6），禁止各處手寫 mapping

設定放 `config/cohistograph/ui.php`（新建），**不要**擴充 `config/cohistograph/app.php` 的 `graph` 區塊：

```php
<?php

return [
    'locales' => [
        'zh_TW' => [
            'native_label' => '繁體中文',
            'html_lang' => 'zh-Hant-TW',
            'graph_locale' => 'zh_tw',
        ],
        'en' => [
            'native_label' => 'English',
            'html_lang' => 'en',
            'graph_locale' => 'en_us',
        ],
        'ja' => [
            'native_label' => '日本語',
            'html_lang' => 'ja',
            'graph_locale' => 'ja_jp',
        ],
    ],
    'default' => 'zh_TW',
    'fallback_locales' => ['zh_TW', 'ja', 'en'],
];
```

`native_label` 永遠以該語言自己的名稱顯示（語言切換器慣例）。

`fallback_locales` 是**站台預設內容 fallback 鏈**（繁中 → 日文 → 英文）。登入使用者可覆寫，見 §3。

### 2. Locale 解析順序

Middleware `SetUiLocale`（web stack，Inertia 之前）：

1. 合法的 cookie `ui_locale`
2. 已登入且 `users.preferred_locale` 非空且合法
3. `config('cohistograph.ui.default')`（`zh_TW`）

然後 `App::setLocale($locale)`。

**刻意不做：**

- `Accept-Language` 自動偵測（產品以繁中為主，自動切英文會讓人錯亂）
- URL 前綴（`/en/...`）
- query string `?locale=`

Cookie 名稱：`ui_locale`；`httpOnly`、`SameSite=Lax`、一年。不放進 session，避免訪客一登出就丟。

### 3. 持久化

| 身分 | 介面語言 | 內容 fallback 鏈 |
|------|----------|------------------|
| 訪客 | cookie `ui_locale` | 站台預設（不可自訂） |
| 登入使用者 | cookie **加上** `users.preferred_locale` | `users.fallback_locales`（nullable JSON）；`null` = 站台預設 |

切換語言（`POST /locale`）只改**介面語言**，不改 fallback 鏈：

1. 驗證 locale ∈ `config('cohistograph.ui.locales')` keys
2. 寫 cookie
3. 若已登入，更新 `preferred_locale`
4. `back()`

`User` 實作 `Illuminate\Contracts\Translation\HasLocalePreference`，回傳 `preferred_locale`，讓驗證信等 notification 用使用者偏好而非當下 request cookie。

Profile 頁可另放介面語言 `<select>` 綁 `preferred_locale`；navbar 切換器仍是主要入口。兩個入口寫同一欄 + cookie，避免分叉。

#### 3.1 使用者自訂 fallback

**用途：** 目前介面語言對應的圖譜內容（Vertex 顯示名稱等）沒有值時，依此順序再試其他語言。

**站台預設（訪客與未設定的使用者）：**

1. 繁體中文（`zh_TW` → `zh_tw`）
2. 日本語（`ja` → `ja_jp`）
3. English（`en` → `en_us`）

**解析順序：** `unique([目前介面語言, ...fallback 鏈])`。介面語言永遠最先試；fallback 裡若重複則略過。

| 介面語言 | 使用預設 fallback 時的實際順序 |
|----------|--------------------------------|
| `zh_TW` | `zh_TW` → `ja` → `en` |
| `ja` | `ja` → `zh_TW` → `en` |
| `en` | `en` → `zh_TW` → `ja` |

**儲存：** `users.fallback_locales` 為 UI locale key 的有序陣列，例如 `["ja","zh_TW","en"]`。

驗證：

- 每個元素必須是 `config('cohistograph.ui.locales')` 的 key
- 不可重複
- 必須剛好包含**全部**支援語言（三種都在，只改順序）。不提供「關掉某語言」——沒值本來就會跳下一項
- `null` / 省略 = 使用站台預設，不寫死一份副本進 DB

**設定 UI：** Profile（階段 3）。可排序清單（上移／下移），顯示各語言 `native_label`。Navbar 語言切換器**不**暴露此設定。

`POST /profile`（或獨立 `PATCH /profile/locale-fallback`）更新此欄。與改顯示名稱／密碼分開較清楚，但也可併進現有 `UpdateProfileRequest`。

**不做：** 訪客 cookie 記 fallback（進階設定，登入後再改即可）。MCP / Artisan 無使用者時，繼續用 graph config 的 `display_locale` + `display_locale_fallback`（階段 6 把後者預設改成 `['zh_tw', 'ja_jp', 'en_us']` 以對齊）。

### 4. 語言切換器 UI

放在 navbar 右側、使用者／登入選單**之前**（全站 `layouts/app.blade.php` 都看得到，含未登入）。

- 桌面：標籤為目前 locale 的 `native_label`
- 選項：三種語言的 `native_label`
- 目前語言 disabled / active
- POST form + CSRF（狀態變更不用 GET）
- 不需權限

### 5. 翻譯檔結構

專案根目錄 `lang/`（Laravel 12 預設位置）：

```
lang/
├── zh_TW/
│   ├── nav.php
│   ├── auth.php
│   ├── common.php          # 儲存、取消、刪除、搜尋…
│   ├── validation.php      # 自訂 Rule + 覆蓋需要的框架訊息
│   ├── mail.php
│   ├── datatables.php
│   ├── revision.php
│   ├── graph.php
│   ├── graph_schema.php
│   └── admin.php
├── en/   （同上）
└── ja/   （同上）
```

規則：

- key 為小寫 snake、以領域為檔名：`__('nav.login')`、`__('revision.submitted')`
- **禁止**以繁中原文當 key
- 缺 key 時 Laravel fallback 到 `zh_TW`，再顯示 key 本身
- `ja` 允許分階段補齊；未譯的 key 顯示繁中，不算 bug。`en` 與 `zh_TW` 在合併該頁 PR 時必須齊套
- 參數用 Laravel `:name`：`__('role.created', ['name' => $role->name])`

抽取時維持產品詞彙：**Vertex / Edge / Graph Schema / Overview / MCP** 在三種語言都保留英文（與現況 Graph Schema 管理畫面一致），不要譯成「頂點／邊」。

### 6. Graph display locale 綁定

新增單一入口，例如 `App\Support\UiLocale`：

```php
public static function current(): string { /* App::getLocale()，非法則 default */ }

/** @return list<string> UI locale keys，已含目前語言且 unique */
public static function resolveOrder(?User $user = null): array { /* current + fallback */ }

public static function graphLocale(): string { /* current 對應的 graph locale */ }

/** @return list<string> graph locale keys，對應 resolveOrder() */
public static function graphLocaleOrder(?User $user = null): array { /* mapping */ }

/** @return list<string> 不含 current 的 graph fallback，給 resolver 第二參數 */
public static function graphFallbackLocales(?User $user = null): array { /* … */ }
```

`fallbackLocales()` 來源：登入使用者的 `fallback_locales`（通過驗證才用），否則 `config('cohistograph.ui.fallback_locales')`。

Web 請求裡所有 `VertexDisplayNameResolver::resolve(...)` 改為：

- `$locale` = `UiLocale::graphLocale()`
- 新增（或使用既有 fallback 參數）`$fallbackLocales` = `UiLocale::graphFallbackLocales()`

現有 resolver 已是 `unique([$primary, ...$fallback])`，與 §3.1 一致。未傳 fallback 時（MCP / console）仍讀 `config('cohistograph.app.graph.display_locale_fallback')`。

階段 6 將該 config 預設由 `['zh_tw', 'en_us', 'ja_jp']` 改為 `['zh_tw', 'ja_jp', 'en_us']`，與站台 UI 預設對齊。HTTP 路徑以使用者／UI config 為準，不再依賴這份 graph config 當登入使用者的來源。

**UI chrome `__()`：** Laravel 只能設一個 `fallback_locale`。維持 `zh_TW`（預設鏈的第一項）。**不**為選單／按鈕實作三語逐級查找。使用者自訂的鏈只影響**圖譜內容**解析。

**瀏覽頁的屬性列表**（`LocalizedPropertyGrouper`）**維持列出所有已定義 locale**。編輯／審核需要看到缺譯；瀏覽端若只顯示當前語言會藏資料。標題／列表卡片走 resolver 即可。若之後要「只看我的語言」，另開議題。

Graph locale 的 badge 文案（「繁體中文」）繼續用 `config('cohistograph.app.graph.locales')` 的自身語言名稱，不經 UI 翻譯檔。

### 7. Blade / PHP

| 位置 | 做法 |
|------|------|
| Blade 文案 | `{{ __('nav.login') }}`；`@section('title', __('profile.title'))` |
| `MenuService` | `$menu->add(__('nav.login'), ...)`（locale 已在 middleware 設好） |
| Controller flash | `->with('global', __('revision.submitted'))` |
| Form Request `messages()` | 回傳翻譯 key，或刪除 `messages()` 改用 `lang/*/validation.php` |
| 自訂 `Rule` `$fail(...)` | `$fail(__('validation.age_property_name.charset'))` |
| `abort(403, ...)` | 改翻譯 key |
| MailMessage | `->subject(__('mail.verify.subject'))` 等 |
| DataTables `language` | Blade 裡用 `__('datatables.empty_table')` 等組物件 |
| `<html lang>` | `config('cohistograph.ui.locales')[UiLocale::current()]['html_lang']` |

### 8. Vue / Inertia

`HandleInertiaRequests::share()`：

```php
'locale' => UiLocale::current(),
'uiLocales' => /* id + native_label，給切換器若 Vue 也要 */,
'translations' => __('*'), // 或明確載入 §5 的檔，flatten 成 'nav.login' => '…'
```

`resources/js/i18n.js`（新建）提供 `t(key, replace = {})`，從 `usePage().props.translations` 取值，缺 key 回傳 key。**不要**加 `vue-i18n`。

現有寫死的 map（如 `Edit.vue` 的 `actionLabels`）改為 `t('revision.action.create_vertex')`。

`graphLocales` Inertia prop **保留**（圖譜 property 的 locale 標籤），與 UI translations 分開。

非 Inertia 的 Blade 頁仍載入 `app.js`，但不啟動 Inertia；那些頁的字串只走伺服器端 `__()`。

### 9. CMS／使用者產生內容（本文件不做）

以下是**內容**而非介面 chrome，保持單語（現況繁中）：

| 內容 | 儲存 |
|------|------|
| 首頁 tagline | `HomepageConfig` 單一 string |
| FAQ | `faq_items.question` / `answer` 無 locale 欄 |
| VertexType / Property 的 `name`、`description` | schema 定義 |
| ENUM `label` | schema 定義 |
| Vertex / Edge 屬性值 | 已由 graph locale 處理 |

首頁行銷區塊（`index.blade.php`「這是什麼／我能做什麼」）屬於介面文案，**要**進翻譯檔。

### 10. MCP

MCP tool / prompt / instructions 維持英文（給模型看，不是給使用者看的 UI）。**不**納入本文件。

---

## 分階段實作

每一階段獨立 PR，前一階段可合併後再開始下一階段。測試見各階段「驗證」。

### 階段 0 — 本 spec（本 PR）

只加文件與交叉連結，不改行為。

### 階段 1 — 基建

- `config/cohistograph/ui.php`
- `App\Support\UiLocale`
- `SetUiLocale` middleware
- `users.preferred_locale`、`users.fallback_locales` migration + factory
- `POST locale.update` + navbar 切換器（只改介面語言）
- `lang/zh_TW` + `lang/en` 先覆蓋 nav / common / auth 最小集合；`ja` 可先複製 zh_TW 或留空靠 fallback
- 預設 `APP_LOCALE=zh_TW`
- 修正 `<html lang>`
- Inertia share + `t()` helper（即使 Vue 頁尚未抽字）
- `TestCase` 明確 `App::setLocale('zh_TW')`，避免環境 locale 影響現有測試
- **驗證：** 切到 `en` 時 navbar「登入」變 Login；cookie 在登出後仍在；登入使用者寫入 `preferred_locale`；未切換時全站仍為繁中

### 階段 2 — Chrome

`MenuService`、footer「常見問題」、flash 容器無文案、DataTables 預設 `language`、通用按鈕（儲存／取消／刪除／返回）。

### 階段 3 — Auth / Profile / Mail / Validation

登入註冊驗證、重設密碼、`VerifyEmail` MailMessage、Form Request 與自訂 Rule。此階段會修掉密碼重設英文 flash 的既有 bug。

Profile 加上 fallback 可排序清單（§3.1），寫入 `users.fallback_locales`。未儲存時 DB 保持 `null`，解析走站台預設。

### 階段 4 — Graph 瀏覽 + Graph Schema Blade

`graph/*`、`graph-schema/*`、overview。Schema 管理畫面保留 Vertex / Edge 英文術語。

### 階段 5 — Revisions（Blade + Vue）

修訂列表／詳情／審核 Blade，以及 `Revisions/Edit.vue` 與所有 Partials、`GraphSchema/Visualization.vue`。

### 階段 6 — 綁定 graph display locale

呼叫端傳入 `UiLocale::graphLocale()` 與 `graphFallbackLocales()`；將 graph `display_locale_fallback` 預設改為 `zh_tw` → `ja_jp` → `en_us`。

補測試：UI=`en` 且存在 `name_en_us` 時標題為英文；無英文時依預設鏈先繁中再日文（`unique([en, zh_TW, ja])`）。

更新 [`show-property-name.md`](./show-property-name.md)「明確排除」：此項改由本文件階段 6 負責。

---

## 需修改的檔案摘要（實作時；本 PR 僅 spec）

階段 1 核心：

- `config/cohistograph/ui.php`（新建）
- `app/Support/UiLocale.php`（新建）
- `app/Http/Middleware/SetUiLocale.php`（新建）
- `bootstrap/app.php` — web 加入 SetUiLocale（在 Inertia 之前）
- `config/app.php`、`.env.example` — `APP_LOCALE` / `APP_FALLBACK_LOCALE` = `zh_TW`
- `database/migrations/xxxx_add_preferred_locale_and_fallback_locales_to_users_table.php`（新建）
- `app/Models/User.php` — fillable、casts、`HasLocalePreference`
- `database/factories/UserFactory.php` — `preferred_locale` / `fallback_locales` 預設 `null`
- `app/Http/Controllers/LocaleController.php`（新建）
- `routes/web.php` — `POST /locale`
- `resources/views/layouts/app.blade.php` — `html lang`
- `resources/views/components/navbar/navbar.blade.php` — 切換器
- `app/Http/Middleware/HandleInertiaRequests.php` — share locale / translations
- `resources/js/i18n.js`（新建）
- `lang/zh_TW/*.php`、`lang/en/*.php`、`lang/ja/*.php`（新建）
- `tests/TestCase.php` — 預設 `zh_TW`
- `tests/Feature/UiLocaleTest.php`（新建）

後續階段再改各 Blade / Vue / Controller / Rule，不在此一一列完。

---

## 測試重點

### 階段 1 Feature（`tests/Feature/UiLocaleTest.php`）

1. 預設（無 cookie、無偏好）`html lang="zh-Hant-TW"`，navbar 為繁中
2. `POST /locale` `{locale: en}` → 302 back、cookie `ui_locale=en`、再 GET 為 `html lang="en"` 且看到英文 nav
3. 非法 locale → 422 或 redirect 且不改 cookie
4. 訪客切換後 cookie 在後續請求仍生效
5. 登入使用者切換後 `users.preferred_locale` 更新；即使清 cookie，登入後仍用 DB 偏好（解析順序為 cookie 優先，故此測需不帶 cookie）
6. 已有 `preferred_locale=en` 且無 cookie → 介面為英文
7. 未切換時既有頁面（login、home）繁中文案不變

### 階段 3

8. 登入使用者把 fallback 排成 `en` → `ja` → `zh_TW` 後，`users.fallback_locales` 為該順序
9. 非法值（缺一種、重複、未知 key）→ validation error，DB 不變
10. 從未儲存過 fallback（`null`）→ `UiLocale::fallbackLocales()` 回傳 `['zh_TW', 'ja', 'en']`

### 階段 6

11. UI=`en`、Vertex 有 `name_en_us` 與 `name_zh_tw` → 列表／詳情標題用英文
12. UI=`en`、無 `name_en_us`、有 `name_ja_jp` 與 `name_zh_tw`、fallback 為預設 → 標題用繁中（`en` → `zh_TW` → `ja`）
13. UI=`en`、無英文與繁中、有 `name_ja_jp`、fallback 為預設 → 標題用日文
14. 使用者自訂 fallback 為 `ja` → `en` → `zh_TW`，UI=`en`，無英文、有日文與繁中 → 標題用日文（略過已試的 `en` 後先日文）
15. MCP / 未傳 locale 時走 graph config fallback（階段 6 後為 `zh_tw` → `ja_jp` → `en_us`）

### 測試策略（全階段）

- Feature 測試**預設 locale = `zh_TW`**，現有 `assertSee('登入')` 等**不必**一次改完
- 抽完某頁字串的 PR，該頁新測可用 `__('nav.login')` 同時 assert 兩種 locale，或 `App::setLocale('en')` 後 assert 英文
- 不要為了 i18n 刪既有測試

---

## 明確排除

- FAQ、首頁 tagline、schema 欄位名稱／ENUM label 的內容多語系（另開 spec）
- MCP 文案
- `Accept-Language`、locale URL 前綴、`?locale=`
- 新增 npm / composer i18n 套件
- 瀏覽頁屬性列表依 UI locale 過濾（Grouper 維持全 locale）
- 一次抽完全站字串的巨型 PR
- 改 graph locale key 格式（繼續 `zh_tw`）
- 訪客自訂 fallback（僅登入使用者；訪客用站台預設）
- 訪客自訂 fallback（僅登入使用者；訪客用站台預設）

---

## 決策紀錄

| 決策 | 選擇 | 理由 |
|------|------|------|
| UI vs graph locale | 分開的 config 與 key | graph 已在 DB；Laravel 慣例是 `zh_TW` |
| 預設語言 | `zh_TW` | 與現況 UI 一致；減少測試崩壞 |
| 翻譯格式 | PHP 檔 + 穩定英文 key | 文案可改、Vue/Blade 共用 |
| Vue | Inertia share + `t()` | 零新依賴、單一 catalog |
| 訪客狀態 | cookie，不用 session | 登出後仍記住 |
| 自動偵測瀏覽器語言 | 不做 | 繁中優先產品 |
| Vertex 標題 | 目前 UI locale，再走使用者 fallback | 兌現 show-property-name 預留參數 |
| 內容 fallback 預設 | 繁中 → 日文 → 英文 | 產品內容常見優先序；與舊 graph config（繁中→英文→日文）不同 |
| 內容 fallback 誰能改 | 僅登入使用者（Profile 排序） | 訪客用站台預設；navbar 只切介面語言 |
| fallback 可否拿掉語言 | 否，三種都要、只改順序 | 沒值本來就會往下一項 |
| UI chrome 缺譯 | 固定 fallback `zh_TW` | Laravel 單一 fallback；三語鏈只用於圖譜內容 |
| 屬性列表 | 仍顯示所有 locale | 避免藏起未譯資料 |
| 術語 Vertex/Edge | 不翻譯 | 與現況管理介面一致 |
| 日文 UI 完整度 | 允許缺 key 時顯示繁中 | 降低第一波成本；英文須與繁中齊 |
