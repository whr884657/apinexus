# ApiNexus 5.2.0 发行说明

**版本：** 5.2.0  
**日期：** 2026-07-21  
**类型：** 中版本（登录会话 / 认证 CSRF 加固，**作废此前错误的 5.2.0 / 5.2.1 包**）

## 重要说明

此前推送的 **5.2.0 / 5.2.1** 会话 Cookie 策略有误（默认非 Secure + 登录时清除 Secure Cookie），会导致：

- 登录成功但无法进入后台  
- 或登录后一刷新即退出  

本包为**从 v5.1.1 基线重做**的正确 5.2.0。请删除站点上旧的 5.2.x 包后覆盖安装，并**清一次本站 Cookie** 再登录。

## 保留的加固（相对 5.1.1）

1. 认证页 `Cache-Control` / `CDN-Cache-Control: no-store`，避免 CDN 缓存登录 HTML  
2. CSRF 失败时 `rotateCsrfToken()`；前端 `assets/js/auth-csrf.js`（`VsAuthCsrf`）回填并重试一次  
3. `isHttps()` 兼容 `X-Forwarded-Proto` 多值；退出时 Secure / 非 Secure 会话 Cookie 双清  

## 会话 Cookie（对齐 5.1.1）

- `Secure` **跟随当前请求是否 HTTPS**（`AuthSecurity::sessionCookieSecure()` → `isHttps()`）  
- **禁止**默认 `Secure=false`  
- **禁止**登录成功或每个请求下发「清除 Secure 会话 Cookie」  

## 升级

1. 覆盖代码  
2. 清浏览器本站 Cookie 后重新登录  
3. **无需**数据库结构更新  

## 打包说明（v5.2.0 补发）

- `默认主题参考UI（主题一）/` 仅为本地对照，**不得**进入 Git / 发行 ZIP  
- 本版 ZIP 已用根目录 `pack-release.ps1` 打包并自检排除；若旧包内仍有该目录，请改下本包  

## 数据库变更

无
