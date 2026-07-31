# CoHistograph

[![PHP Test](https://github.com/danny50610/CoHistograph/actions/workflows/php.yml/badge.svg)](https://github.com/danny50610/CoHistograph/actions)
[![codecov](https://codecov.io/gh/danny50610/CoHistograph/graph/badge.svg?token=HJII4O74M1)](https://codecov.io/gh/danny50610/CoHistograph)

A collaborative platform for building, managing, and exploring historical event knowledge graphs.  
一個協作式平台，用於構建、管理及探索歷史事件知識圖譜。

## MCP（HTTP）

業務層 MCP Server 透過 HTTP 提供，端點為：

```text
https://<your-domain>/mcp/cohistograph
```

認證使用 Laravel Passport **OAuth 2.1**，scope 為 `mcp:use`。未帶有效 access token 會回 **401**。

部署時請確認已執行 `php artisan migrate` 與 `php artisan passport:keys`（若尚未產生金鑰），且 `APP_URL` 指向實際對外網址。

### Cursor（建議：OAuth）

在專案的 `.cursor/mcp.json` 或使用者目錄的 `~/.cursor/mcp.json` 加入：

```json
{
  "mcpServers": {
    "cohistograph": {
      "url": "https://<your-domain>/mcp/cohistograph"
    }
  }
}
```

儲存後到 Cursor **Settings → Tools & MCP**，對 `cohistograph` 按 Connect；瀏覽器會開啟登入／授權畫面，核准後即可使用 Tools。

本專案支援 OAuth Dynamic Client Registration（`POST /oauth/register`），多數情況下不必預先建立 client。

若客戶端不支援動態註冊、需要固定 Client ID，可改用靜態 OAuth 設定：

```json
{
  "mcpServers": {
    "cohistograph": {
      "url": "https://<your-domain>/mcp/cohistograph",
      "auth": {
        "CLIENT_ID": "${env:COHISTOGRAPH_MCP_CLIENT_ID}",
        "CLIENT_SECRET": "${env:COHISTOGRAPH_MCP_CLIENT_SECRET}",
        "scopes": ["mcp:use"]
      }
    }
  }
}
```

Cursor Desktop 的 OAuth redirect 為 `http://localhost:8787/callback`；Web / Agents 為 `https://www.cursor.com/agents/mcp/oauth/callback`。若使用靜態 client，請將對應 redirect URI 加入允許清單。

### 已有 Bearer token 時

若已透過 Passport 取得 access token，也可直接帶 header（適用不走瀏覽器授權流程的客戶端）：

```json
{
  "mcpServers": {
    "cohistograph": {
      "url": "https://<your-domain>/mcp/cohistograph",
      "headers": {
        "Authorization": "Bearer ${env:COHISTOGRAPH_MCP_TOKEN}"
      }
    }
  }
}
```

token 需包含 scope `mcp:use`。
